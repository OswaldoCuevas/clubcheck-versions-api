<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Services;

use App\Modules\Downloads\Contracts\IDownloadRepository;

final readonly class ReleaseUploadService
{
    public function __construct(
        private IDownloadRepository $repository,
        private string $directory,
    ) {
    }

    public function upload(array $fields, array $files, array $server): array
    {
        $version = trim((string) ($fields['version'] ?? ''));
        if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $version) !== 1) {
            return $this->error('La versión debe tener el formato X.X.X.X (ej: 1.2.3.0)');
        }

        $exe = is_array($files['exeFile'] ?? null) ? $files['exeFile'] : [];
        $setup = is_array($files['setupFile'] ?? null) ? $files['setupFile'] : [];
        if (($exe['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->error('Error al subir el archivo EXE. Es obligatorio.');
        }
        if (($setup['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->error('Error al subir el archivo Setup ZIP. Es obligatorio.');
        }
        if (strtolower(pathinfo((string) $exe['name'], PATHINFO_EXTENSION)) !== 'exe') {
            return $this->error('El archivo ejecutable debe ser .exe');
        }
        if (strtolower(pathinfo((string) $setup['name'], PATHINFO_EXTENSION)) !== 'zip') {
            return $this->error('El archivo Setup debe ser .zip');
        }

        $this->prepareDirectory();
        $exeName = 'ClubCheck-' . $version . '.exe';
        $setupName = 'ClubCheckSetup-' . $version . '.zip';
        $exePath = $this->directory . DIRECTORY_SEPARATOR . $exeName;
        $setupPath = $this->directory . DIRECTORY_SEPARATOR . $setupName;
        $replacement = is_file($exePath) || is_file($setupPath);
        $this->backup($exePath);
        $this->backup($setupPath);

        if (!move_uploaded_file((string) $exe['tmp_name'], $exePath)) {
            return $this->error('Error al guardar el archivo EXE');
        }
        if (!move_uploaded_file((string) $setup['tmp_name'], $setupPath)) {
            @unlink($exePath);

            return $this->error('Error al guardar el archivo Setup');
        }

        $baseUrl = $this->baseUrl($server);
        $saved = $this->repository->saveVersion(
            [
                'name' => $version,
                'url' => $baseUrl . '/uploads/' . $exeName,
                'sha256' => hash_file('sha256', $exePath),
                'setupUrl' => $baseUrl . '/uploads/' . $setupName,
                'setupSha256' => hash_file('sha256', $setupPath),
                'setupFileSize' => filesize($setupPath),
                'mandatory' => isset($fields['mandatory']),
                'releaseNotes' => trim((string) ($fields['releaseNotes'] ?? '')),
                'uploadDate' => date('Y-m-d H:i:s'),
            ],
        );
        if (!$saved) {
            @unlink($exePath);
            @unlink($setupPath);

            return $this->error('Error al guardar la información de versión en la base de datos');
        }

        $this->cleanBackups();
        $message = $replacement
            ? "Versión {$version} reemplazada exitosamente. Los archivos anteriores fueron respaldados automáticamente."
            : "Versión {$version} subida exitosamente (EXE + Setup ZIP)";

        return [
            'message' => $message,
            'type' => 'success',
        ];
    }

    private function error(string $message): array
    {
        return [
            'message' => $message,
            'type' => 'error',
        ];
    }

    private function prepareDirectory(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    private function backup(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $backup = $this->directory
            . DIRECTORY_SEPARATOR
            . 'backup_'
            . time()
            . '_'
            . basename($path);
        copy($path, $backup);
        unlink($path);
    }

    private function cleanBackups(): void
    {
        $backups = glob($this->directory . DIRECTORY_SEPARATOR . 'backup_*') ?: [];
        usort(
            $backups,
            static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left),
        );
        foreach (array_slice($backups, 5) as $backup) {
            unlink($backup);
        }
    }

    private function baseUrl(array $server): string
    {
        $protocol = ($server['HTTPS'] ?? '') === 'on' ? 'https' : 'http';
        $host = (string) ($server['HTTP_HOST'] ?? 'localhost');
        $path = dirname((string) ($server['REQUEST_URI'] ?? '/'));

        return $protocol . '://' . $host . ($path === '/' ? '' : $path);
    }
}
