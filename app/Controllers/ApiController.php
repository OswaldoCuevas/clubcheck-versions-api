<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/VersionModel.php';
require_once __DIR__ . '/../Models/DownloadLogModel.php';
require_once __DIR__ . '/../Models/ApplicationModel.php';

use Core\Controller;
use Models\ApplicationModel;
use Models\DownloadLogModel;
use Models\VersionModel;

class ApiController extends Controller
{
    private VersionModel $versionModel;
    private DownloadLogModel $downloadLogModel;

    public function __construct()
    {
        parent::__construct();
        $this->versionModel = new VersionModel();
        $this->downloadLogModel = new DownloadLogModel();
    }

    private function requestAppId(?array $payload = null): string
    {
        $appPayload = array_merge($_GET, $payload ?? []);
        return (new ApplicationModel())->resolveFromPayload($appPayload);
    }

    private function withAppQuery(string $url, string $appId): string
    {
        $app = (new ApplicationModel())->find($appId);
        if (!$app || empty($app['slug'])) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . 'app=' . urlencode($app['slug']);
    }

    private function latestVersion(string $appId): array
    {
        // Las versiones se resuelven por app; si no existe la migracion usa el catalogo global anterior.
        return $this->versionModel->getLatestVersion($appId);
    }

    public function version(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');

        $appId = $this->requestAppId();
        $versionData = $this->latestVersion($appId);
        $hasValidVersion = !empty($versionData['latestVersion']) && $versionData['latestVersion'] !== '0.0.0.0';

        $versionData['hasUpdate'] = $hasValidVersion;

        if ($hasValidVersion) {
            require_once __DIR__ . '/../Core/UrlHelper.php';
            require_once __DIR__ . '/../Helpers/FileHelper.php';

            $baseUrl = \Core\UrlHelper::absoluteUrl('');
            $versionData['downloadUrl'] = str_replace('//api', '/api', $this->withAppQuery($baseUrl . '/api/download', $appId));
            $versionData['downloadSetupUrl'] = str_replace('//api', '/api', $this->withAppQuery($baseUrl . '/api/download-setup', $appId));
            $versionData['downloadZipUrl'] = str_replace('//api', '/api', $this->withAppQuery($baseUrl . '/api/download-zip', $appId));
            $versionData['checkUpdateUrl'] = str_replace('//api', '/api', $this->withAppQuery($baseUrl . '/api/check-update', $appId));
            $versionData['directUrl'] = $versionData['url'] ?? '';
            $versionData['directSetupUrl'] = $versionData['setupUrl'] ?? '';
            $versionData['directZipUrl'] = $versionData['setupUrl'] ?? '';

            $fileName = getAppFileName($versionData['latestVersion']);
            $filePath = findExistingAppFile($versionData['latestVersion']) ?: (__DIR__ . '/../../uploads/' . $fileName);
            $versionData['fileExists'] = file_exists($filePath);
            if ($versionData['fileExists']) {
                $versionData['fileSize'] = filesize($filePath);
                $versionData['fileDate'] = filemtime($filePath);
            }

            if (!empty($versionData['setupUrl'])) {
                $setupFileName = getSetupFileName($versionData['latestVersion']);
                $setupFilePath = __DIR__ . '/../../uploads/' . $setupFileName;
                $versionData['setupFileExists'] = file_exists($setupFilePath);
                $versionData['zipFileExists'] = $versionData['setupFileExists'];
                if ($versionData['setupFileExists'] && empty($versionData['setupFileSize'])) {
                    $versionData['setupFileSize'] = filesize($setupFilePath);
                }
            }
        }

        echo json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function checkUpdate(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Content-Type');

        $input = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
        }

        $clientVersion = $_SERVER['REQUEST_METHOD'] === 'POST'
            ? ($input['currentVersion'] ?? '')
            : ($_GET['version'] ?? $_GET['currentVersion'] ?? '');

        $appId = $this->requestAppId($input);
        $versionData = $this->latestVersion($appId);
        $serverVersion = $versionData['latestVersion'] ?? '0.0.0.0';
        $hasValidVersion = !empty($serverVersion) && $serverVersion !== '0.0.0.0';

        $response = [
            'hasUpdate' => false,
            'serverVersion' => $serverVersion,
            'clientVersion' => $clientVersion,
            'mandatory' => $versionData['mandatory'] ?? false,
            'releaseNotes' => $versionData['releaseNotes'] ?? '',
            'timestamp' => $versionData['timestamp'] ?? time(),
            'lastCheck' => time(),
        ];

        if (!empty($clientVersion) && $hasValidVersion) {
            $response['hasUpdate'] = version_compare($serverVersion, $clientVersion, '>');

            if ($response['hasUpdate']) {
                require_once __DIR__ . '/../Core/UrlHelper.php';
                require_once __DIR__ . '/../Helpers/FileHelper.php';

                $baseUrl = \Core\UrlHelper::absoluteUrl('');
                $response['downloadUrl'] = $this->withAppQuery($baseUrl . '/api/download', $appId);
                $response['downloadSetupUrl'] = $this->withAppQuery($baseUrl . '/api/download-setup', $appId);
                $response['downloadZipUrl'] = $this->withAppQuery($baseUrl . '/api/download-zip', $appId);
                $response['url'] = $versionData['url'] ?? '';
                $response['setupUrl'] = $versionData['setupUrl'] ?? '';
                $response['zipUrl'] = $versionData['setupUrl'] ?? '';

                $fileName = getAppFileName($serverVersion);
                $filePath = findExistingAppFile($serverVersion) ?: (__DIR__ . '/../../uploads/' . $fileName);
                if (file_exists($filePath)) {
                    $response['fileSize'] = filesize($filePath);
                    $response['checksum'] = hash_file('sha256', $filePath);
                }

                if (!empty($versionData['setupUrl'])) {
                    $response['setupFileSize'] = $versionData['setupFileSize'] ?? null;
                    $response['setupSha256'] = $versionData['setupSha256'] ?? '';

                    $setupFileName = getSetupFileName($serverVersion);
                    $setupFilePath = __DIR__ . '/../../uploads/' . $setupFileName;
                    if (file_exists($setupFilePath)) {
                        if (empty($response['setupFileSize'])) {
                            $response['setupFileSize'] = filesize($setupFilePath);
                        }
                        if (empty($response['setupSha256'])) {
                            $response['setupSha256'] = hash_file('sha256', $setupFilePath);
                        }
                    }
                }
            }
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function download(): void
    {
        $appId = $this->requestAppId();
        $versionData = $this->latestVersion($appId);

        require_once __DIR__ . '/../Helpers/FileHelper.php';
        $fileName = getAppFileName($versionData['latestVersion']);
        $filePath = findExistingAppFile($versionData['latestVersion']) ?: (__DIR__ . '/../../uploads/' . $fileName);

        if (!file_exists($filePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Archivo no encontrado',
                'message' => 'El archivo ejecutable no existe en el servidor',
                'expectedFile' => $fileName,
                'version' => $versionData['latestVersion'],
            ]);
            exit;
        }

        $returnInfo = $_GET['info'] ?? false;
        if ($returnInfo) {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            echo json_encode([
                'filename' => $fileName,
                'version' => $versionData['latestVersion'],
                'size' => filesize($filePath),
                'checksum' => hash_file('sha256', $filePath),
                'lastModified' => filemtime($filePath),
                'url' => $versionData['url'] ?? '',
                'releaseNotes' => $versionData['releaseNotes'] ?? '',
                'mandatory' => $versionData['mandatory'] ?? false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $this->sendDownloadFile($filePath, $fileName, 'application/octet-stream', 'exe', $versionData['latestVersion'], $appId);
    }

    public function downloadSetup(): void
    {
        $appId = $this->requestAppId();
        $versionData = $this->latestVersion($appId);

        if (empty($versionData['setupUrl'])) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Archivo Setup no encontrado',
                'message' => 'No hay archivo Setup disponible para la version actual',
                'version' => $versionData['latestVersion'],
            ]);
            exit;
        }

        require_once __DIR__ . '/../Helpers/FileHelper.php';
        $setupFileName = getSetupFileName($versionData['latestVersion']);
        $setupFilePath = __DIR__ . '/../../uploads/' . $setupFileName;

        if (!file_exists($setupFilePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Archivo no encontrado',
                'message' => 'El archivo Setup no existe en el servidor',
                'expectedFile' => $setupFileName,
                'version' => $versionData['latestVersion'],
            ]);
            exit;
        }

        $returnInfo = $_GET['info'] ?? false;
        if ($returnInfo) {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            echo json_encode([
                'filename' => $setupFileName,
                'version' => $versionData['latestVersion'],
                'size' => filesize($setupFilePath),
                'checksum' => hash_file('sha256', $setupFilePath),
                'lastModified' => filemtime($setupFilePath),
                'url' => $versionData['setupUrl'] ?? '',
                'releaseNotes' => $versionData['releaseNotes'] ?? '',
                'mandatory' => $versionData['mandatory'] ?? false,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $this->sendDownloadFile($setupFilePath, $setupFileName, 'application/zip', 'setup', $versionData['latestVersion'], $appId);
    }

    public function downloadZip(): void
    {
        $this->downloadSetup();
    }

    private function sendDownloadFile(string $filePath, string $fileName, string $contentType, string $downloadType, string $version, string $appId): void
    {
        $fileSize = filesize($filePath);

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Access-Control-Allow-Origin: *');

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No se puede leer el archivo']);
            exit;
        }

        $bytesSent = 0;
        while (!feof($handle) && connection_status() === CONNECTION_NORMAL) {
            $buffer = fread($handle, 8192);
            $bytesSent += strlen($buffer);
            echo $buffer;
            flush();
        }

        fclose($handle);

        if ($bytesSent >= $fileSize && connection_status() === CONNECTION_NORMAL) {
            $this->downloadLogModel->logDownload($downloadType, $version, $fileName, $fileSize, $appId);
        }
        exit;
    }

    public function timestamp(): void
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $utcTimestamp = $now->format('Y-m-d\TH:i:s\Z');
        $unixTimestamp = $now->getTimestamp();
        $microSeconds = (int) $now->format('u');
        $secondsSinceNetEpoch = $unixTimestamp + 62135596800;
        $utcTicks = ($secondsSinceNetEpoch * 10000000) + ($microSeconds * 10);

        echo json_encode([
            'utcTimestamp' => $utcTimestamp,
            'utcTicks' => $utcTicks,
            'timeZone' => date_default_timezone_get(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
