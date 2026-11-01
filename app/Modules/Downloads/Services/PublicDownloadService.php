<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Services;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\DownloadResult;
use App\Modules\Downloads\Contracts\IDownloadRepository;

final readonly class PublicDownloadService
{
    public function __construct(
        private IDownloadRepository $downloads,
        private DownloadFileLocator $files,
    ) {
    }

    /** @return array<string, mixed> */
    public function version(string $baseUrl): array
    {
        $version = $this->downloads->latestVersion();
        $versionNumber = (string) ($version['latestVersion'] ?? '0.0.0.0');
        $hasValidVersion = $versionNumber !== '' && $versionNumber !== '0.0.0.0';
        $version['hasUpdate'] = $hasValidVersion;

        if (!$hasValidVersion) {
            return $version;
        }

        $version['downloadUrl'] = $baseUrl . '/api/download';
        $version['downloadSetupUrl'] = $baseUrl . '/api/download-setup';
        $version['downloadZipUrl'] = $baseUrl . '/api/download-zip';
        $version['checkUpdateUrl'] = $baseUrl . '/api/check-update';
        $version['directUrl'] = $version['url'] ?? '';
        $version['directSetupUrl'] = $version['setupUrl'] ?? '';
        $version['directZipUrl'] = $version['setupUrl'] ?? '';

        $executable = $this->files->executable($versionNumber);
        $version['fileExists'] = is_file($executable['path']);

        if ($version['fileExists']) {
            $version['fileSize'] = filesize($executable['path']);
            $version['fileDate'] = filemtime($executable['path']);
        }

        if (!empty($version['setupUrl'])) {
            $setup = $this->files->setup($versionNumber);
            $version['setupFileExists'] = is_file($setup['path']);
            $version['zipFileExists'] = $version['setupFileExists'];

            if ($version['setupFileExists'] && empty($version['setupFileSize'])) {
                $version['setupFileSize'] = filesize($setup['path']);
            }
        }

        return $version;
    }

    /** @param array<string, mixed> $parameters @return array<string, mixed> */
    public function checkUpdate(array $parameters, string $baseUrl): array
    {
        $version = $this->downloads->latestVersion();
        $clientVersion = (string) (
            $parameters['version']
                ?? $parameters['currentVersion']
                ?? ''
        );
        $serverVersion = (string) ($version['latestVersion'] ?? '0.0.0.0');
        $hasValidVersion = $serverVersion !== '' && $serverVersion !== '0.0.0.0';
        $hasUpdate = $clientVersion !== ''
            && $hasValidVersion
            && version_compare($serverVersion, $clientVersion, '>');
        $response = [
            'hasUpdate' => $hasUpdate,
            'serverVersion' => $serverVersion,
            'clientVersion' => $clientVersion,
            'mandatory' => $version['mandatory'] ?? false,
            'releaseNotes' => $version['releaseNotes'] ?? '',
            'timestamp' => $version['timestamp'] ?? time(),
            'lastCheck' => time(),
        ];

        if (!$hasUpdate) {
            return $response;
        }

        $response['downloadUrl'] = $baseUrl . '/api/download';
        $response['downloadSetupUrl'] = $baseUrl . '/api/download-setup';
        $response['downloadZipUrl'] = $baseUrl . '/api/download-zip';
        $response['url'] = $version['url'] ?? '';
        $response['setupUrl'] = $version['setupUrl'] ?? '';
        $response['zipUrl'] = $version['setupUrl'] ?? '';
        $executable = $this->files->executable($serverVersion);

        if (is_file($executable['path'])) {
            $response['fileSize'] = filesize($executable['path']);
            $response['checksum'] = hash_file('sha256', $executable['path']);
        }

        if (!empty($version['setupUrl'])) {
            $response['setupFileSize'] = $version['setupFileSize'] ?? null;
            $response['setupSha256'] = $version['setupSha256'] ?? '';
            $setup = $this->files->setup($serverVersion);

            if (is_file($setup['path'])) {
                $response['setupFileSize'] = $response['setupFileSize']
                    ?: filesize($setup['path']);
                $response['setupSha256'] = $response['setupSha256']
                    ?: hash_file('sha256', $setup['path']);
            }
        }

        return $response;
    }

    /** @param array<string, mixed> $request */
    public function downloadExecutable(array $request): DownloadResult
    {
        return $this->download('exe', $request);
    }

    /** @param array<string, mixed> $request */
    public function downloadSetup(array $request): DownloadResult
    {
        return $this->download('setup', $request);
    }

    /** @param array<string, mixed> $request */
    private function download(string $type, array $request): DownloadResult
    {
        $version = $this->downloads->latestVersion();
        $versionNumber = (string) ($version['latestVersion'] ?? '0.0.0.0');

        if ($type === 'setup' && empty($version['setupUrl'])) {
            throw ApiException::preservingResponse(
                ApiErrorCode::NotFound,
                [
                    'error' => 'Archivo Setup no encontrado',
                    'message' => 'No hay archivo Setup disponible para la versión actual',
                    'version' => $versionNumber,
                ],
            );
        }

        $file = $type === 'exe'
            ? $this->files->executable($versionNumber)
            : $this->files->setup($versionNumber);

        if (!is_file($file['path'])) {
            throw ApiException::preservingResponse(
                ApiErrorCode::NotFound,
                [
                    'error' => 'Archivo no encontrado',
                    'message' => $type === 'exe'
                        ? 'El archivo ejecutable no existe en el servidor'
                        : 'El archivo Setup no existe en el servidor',
                    'expectedFile' => $file['filename'],
                    'version' => $versionNumber,
                ],
            );
        }

        $information = null;

        if (!empty($request['info'])) {
            $information = [
                'filename' => $file['filename'],
                'version' => $versionNumber,
                'size' => filesize($file['path']),
                'checksum' => hash_file('sha256', $file['path']),
                'lastModified' => filemtime($file['path']),
                'url' => $type === 'exe'
                    ? ($version['url'] ?? '')
                    : ($version['setupUrl'] ?? ''),
                'releaseNotes' => $version['releaseNotes'] ?? '',
                'mandatory' => $version['mandatory'] ?? false,
            ];
        } else {
            $this->downloads->recordDownload([
                'type' => $type,
                'version' => $versionNumber,
                'filename' => $file['filename'],
                'ipAddress' => $request['ipAddress'] ?? 'unknown',
                'userAgent' => $this->truncate($request['userAgent'] ?? null),
                'referrer' => $this->truncate($request['referrer'] ?? null),
                'fileSize' => filesize($file['path']),
            ]);
        }

        return new DownloadResult(
            $file['path'],
            $file['filename'],
            $type === 'exe' ? 'application/octet-stream' : 'application/zip',
            $information,
        );
    }

    private function truncate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, 512);
    }
}
