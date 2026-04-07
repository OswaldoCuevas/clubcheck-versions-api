<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/VersionModel.php';
require_once __DIR__ . '/../Models/DownloadLogModel.php';

use Core\Controller;
use Models\VersionModel;
use Models\DownloadLogModel;

class ApiController extends Controller
{
    private $versionModel;
    private $downloadLogModel;

    public function __construct()
    {
        parent::__construct();
        $this->versionModel = new VersionModel();
        $this->downloadLogModel = new DownloadLogModel();
    }

    public function version()
    {
        // Configurar headers para API
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');

        // Obtener versión desde la base de datos
        $versionData = $this->versionModel->getLatestVersion();

        // Verificar si hay una versión válida
        $hasValidVersion = !empty($versionData['latestVersion']) && $versionData['latestVersion'] !== '0.0.0.0';

        // Agregar información adicional útil para el cliente
        $versionData['hasUpdate'] = $hasValidVersion;

        // Agregar URLs de descarga y verificación
        if ($hasValidVersion) {
            require_once __DIR__ . '/../Core/UrlHelper.php';
            $baseUrl = \Core\UrlHelper::absoluteUrl('');
            
            $versionData['downloadUrl'] = $baseUrl . '/api/download';
            $versionData['downloadUrl'] = str_replace('//api', '/api', $versionData['downloadUrl']); // Asegurar formato correcto
            $versionData['downloadSetupUrl'] = $baseUrl . '/api/download-setup';
            $versionData['downloadSetupUrl'] = str_replace('//api', '/api', $versionData['downloadSetupUrl']); // Asegurar formato correcto
            $versionData['checkUpdateUrl'] = $baseUrl . '/api/check-update';
            $versionData['checkUpdateUrl'] = str_replace('//api', '/api', $versionData['checkUpdateUrl']); // Asegurar formato correcto
            $versionData['directUrl'] = $versionData['url'] ?? ''; // URL directa al archivo
            $versionData['directSetupUrl'] = $versionData['setupUrl'] ?? ''; // URL directa al Setup
            
            // Verificar si el archivo EXE existe físicamente
            require_once __DIR__ . '/../Helpers/FileHelper.php';
            $fileName = getAppFileName($versionData['latestVersion']);
            $filePath = findExistingAppFile($versionData['latestVersion']) ?: (__DIR__ . '/../../uploads/' . $fileName);
            $versionData['fileExists'] = file_exists($filePath);
            
            if ($versionData['fileExists']) {
                $versionData['fileSize'] = filesize($filePath);
                $versionData['fileDate'] = filemtime($filePath);
            }
            
            // Verificar si el archivo Setup existe físicamente
            if (!empty($versionData['setupUrl'])) {
                $setupFileName = "ClubCheckSetup-{$versionData['latestVersion']}.exe";
                $setupFilePath = __DIR__ . '/../../uploads/' . $setupFileName;
                $versionData['setupFileExists'] = file_exists($setupFilePath);
                
                if ($versionData['setupFileExists'] && empty($versionData['setupFileSize'])) {
                    $versionData['setupFileSize'] = filesize($setupFilePath);
                }
            }
        }

        echo json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function checkUpdate()
    {
        // Configurar headers para API
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Content-Type');

        // Obtener versión desde la base de datos
        $versionData = $this->versionModel->getLatestVersion();

        // Obtener la versión actual del cliente
        $clientVersion = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $clientVersion = $input['currentVersion'] ?? '';
        } else {
            $clientVersion = $_GET['version'] ?? $_GET['currentVersion'] ?? '';
        }

        $serverVersion = $versionData['latestVersion'] ?? '0.0.0.0';
        $hasValidVersion = !empty($serverVersion) && $serverVersion !== '0.0.0.0';

        $response = [
            'hasUpdate' => false,
            'serverVersion' => $serverVersion,
            'clientVersion' => $clientVersion,
            'mandatory' => $versionData['mandatory'] ?? false,
            'releaseNotes' => $versionData['releaseNotes'] ?? '',
            'timestamp' => $versionData['timestamp'] ?? time(),
            'lastCheck' => time()
        ];

        // Comparar versiones si hay una versión del cliente
        if (!empty($clientVersion) && $hasValidVersion) {
            $response['hasUpdate'] = version_compare($serverVersion, $clientVersion, '>');
            
            if ($response['hasUpdate']) {
                require_once __DIR__ . '/../Core/UrlHelper.php';
                $baseUrl = \Core\UrlHelper::absoluteUrl('');
                $response['downloadUrl'] = $baseUrl . '/api/download';
                $response['downloadSetupUrl'] = $baseUrl . '/api/download-setup';
                $response['url'] = $versionData['url'] ?? '';
                $response['setupUrl'] = $versionData['setupUrl'] ?? '';
                
                // Información adicional del archivo EXE
                require_once __DIR__ . '/../Helpers/FileHelper.php';
                $fileName = getAppFileName($serverVersion);
                $filePath = findExistingAppFile($serverVersion) ?: (__DIR__ . '/../../uploads/' . $fileName);
                if (file_exists($filePath)) {
                    $response['fileSize'] = filesize($filePath);
                    $response['checksum'] = hash_file('sha256', $filePath);
                }
                
                // Información adicional del archivo Setup
                if (!empty($versionData['setupUrl'])) {
                    $response['setupFileSize'] = $versionData['setupFileSize'] ?? null;
                    $response['setupSha256'] = $versionData['setupSha256'] ?? '';
                    
                    $setupFileName = "ClubCheckSetup-{$serverVersion}.exe";
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

    public function download()
    {
        // Obtener versión desde la base de datos
        $versionData = $this->versionModel->getLatestVersion();

        // Obtener el nombre y ruta del archivo
        require_once __DIR__ . '/../Helpers/FileHelper.php';
        $fileName = getAppFileName($versionData['latestVersion']);
        $filePath = findExistingAppFile($versionData['latestVersion']) ?: (__DIR__ . '/../../uploads/' . $fileName);

        // Verificar que el archivo existe
        if (!file_exists($filePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Archivo no encontrado',
                'message' => 'El archivo ejecutable no existe en el servidor',
                'expectedFile' => $fileName,
                'version' => $versionData['latestVersion']
            ]);
            exit;
        }

        // Verificar si se solicita información en JSON (parámetro info=1)
        $returnInfo = $_GET['info'] ?? false;

        if ($returnInfo) {
            // Retornar información del archivo en JSON
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            
            $fileInfo = [
                'filename' => $fileName,
                'version' => $versionData['latestVersion'],
                'size' => filesize($filePath),
                'checksum' => hash_file('sha256', $filePath),
                'lastModified' => filemtime($filePath),
                'url' => $versionData['url'] ?? '',
                'releaseNotes' => $versionData['releaseNotes'] ?? '',
                'mandatory' => $versionData['mandatory'] ?? false
            ];

            echo json_encode($fileInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        } else {
            // Descarga directa del archivo ejecutable (comportamiento por defecto)
            $fileSize = filesize($filePath);
            
            // Registrar la descarga
            $this->downloadLogModel->logDownload(
                'exe',
                $versionData['latestVersion'],
                $fileName,
                $fileSize
            );
            
            // Headers para descarga de archivo
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Access-Control-Allow-Origin: *');

            // Leer y enviar el archivo en chunks para archivos grandes
            $chunkSize = 8192;
            $handle = fopen($filePath, 'rb');
            
            if ($handle === false) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'No se puede leer el archivo']);
                exit;
            }

            while (!feof($handle)) {
                echo fread($handle, $chunkSize);
                flush();
            }
            
            fclose($handle);
            exit;
        }
    }

    public function downloadSetup()
    {
        // Obtener versión desde la base de datos
        $versionData = $this->versionModel->getLatestVersion();

        // Verificar que haya información de Setup en la base de datos
        if (empty($versionData['setupUrl'])) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Archivo Setup no encontrado',
                'message' => 'No hay archivo Setup disponible para la versión actual',
                'version' => $versionData['latestVersion']
            ]);
            exit;
        }

        // Obtener el nombre y ruta del archivo Setup
        $setupFileName = "ClubCheckSetup-{$versionData['latestVersion']}.exe";
        $setupFilePath = __DIR__ . '/../../uploads/' . $setupFileName;

        // Verificar que el archivo existe
        if (!file_exists($setupFilePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Archivo no encontrado',
                'message' => 'El archivo Setup no existe en el servidor',
                'expectedFile' => $setupFileName,
                'version' => $versionData['latestVersion']
            ]);
            exit;
        }

        // Verificar si se solicita información en JSON (parámetro info=1)
        $returnInfo = $_GET['info'] ?? false;

        if ($returnInfo) {
            // Retornar información del archivo en JSON
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            
            $fileInfo = [
                'filename' => $setupFileName,
                'version' => $versionData['latestVersion'],
                'size' => filesize($setupFilePath),
                'checksum' => hash_file('sha256', $setupFilePath),
                'lastModified' => filemtime($setupFilePath),
                'url' => $versionData['setupUrl'] ?? '',
                'releaseNotes' => $versionData['releaseNotes'] ?? '',
                'mandatory' => $versionData['mandatory'] ?? false
            ];

            echo json_encode($fileInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        } else {
            // Descarga directa del archivo Setup (comportamiento por defecto)
            $fileSize = filesize($setupFilePath);
            
            // Registrar la descarga
            $this->downloadLogModel->logDownload(
                'setup',
                $versionData['latestVersion'],
                $setupFileName,
                $fileSize
            );
            
            // Headers para descarga de archivo Setup
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $setupFileName . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Access-Control-Allow-Origin: *');

            // Leer y enviar el archivo en chunks para archivos grandes
            $chunkSize = 8192;
            $handle = fopen($setupFilePath, 'rb');
            
            if ($handle === false) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'No se puede leer el archivo']);
                exit;
            }

            while (!feof($handle)) {
                echo fread($handle, $chunkSize);
                flush();
            }
            
            fclose($handle);
            exit;
        }
    }
}
