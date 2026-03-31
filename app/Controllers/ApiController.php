<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/VersionModel.php';

use Core\Controller;
use Models\VersionModel;

class ApiController extends Controller
{
    private $versionModel;

    public function __construct()
    {
        parent::__construct();
        $this->versionModel = new VersionModel();
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
            $versionData['downloadZipUrl'] = $baseUrl . '/api/download-zip';
            $versionData['downloadZipUrl'] = str_replace('//api', '/api', $versionData['downloadZipUrl']); // Asegurar formato correcto
            $versionData['checkUpdateUrl'] = $baseUrl . '/api/check-update';
            $versionData['checkUpdateUrl'] = str_replace('//api', '/api', $versionData['checkUpdateUrl']); // Asegurar formato correcto
            $versionData['directUrl'] = $versionData['url'] ?? ''; // URL directa al archivo
            $versionData['directZipUrl'] = $versionData['zipUrl'] ?? ''; // URL directa al ZIP
            
            // Verificar si el archivo EXE existe físicamente
            require_once __DIR__ . '/../Helpers/FileHelper.php';
            $fileName = getAppFileName($versionData['latestVersion']);
            $filePath = findExistingAppFile($versionData['latestVersion']) ?: (__DIR__ . '/../../uploads/' . $fileName);
            $versionData['fileExists'] = file_exists($filePath);
            
            if ($versionData['fileExists']) {
                $versionData['fileSize'] = filesize($filePath);
                $versionData['fileDate'] = filemtime($filePath);
            }
            
            // Verificar si el archivo ZIP existe físicamente
            if (!empty($versionData['zipUrl'])) {
                $zipFileName = "ClubCheck-{$versionData['latestVersion']}.zip";
                $zipFilePath = __DIR__ . '/../../uploads/' . $zipFileName;
                $versionData['zipFileExists'] = file_exists($zipFilePath);
                
                if ($versionData['zipFileExists'] && empty($versionData['zipFileSize'])) {
                    $versionData['zipFileSize'] = filesize($zipFilePath);
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
                $response['downloadZipUrl'] = $baseUrl . '/api/download-zip';
                $response['url'] = $versionData['url'] ?? '';
                $response['zipUrl'] = $versionData['zipUrl'] ?? '';
                
                // Información adicional del archivo EXE
                require_once __DIR__ . '/../Helpers/FileHelper.php';
                $fileName = getAppFileName($serverVersion);
                $filePath = findExistingAppFile($serverVersion) ?: (__DIR__ . '/../../uploads/' . $fileName);
                if (file_exists($filePath)) {
                    $response['fileSize'] = filesize($filePath);
                    $response['checksum'] = hash_file('sha256', $filePath);
                }
                
                // Información adicional del archivo ZIP
                if (!empty($versionData['zipUrl'])) {
                    $response['zipFileSize'] = $versionData['zipFileSize'] ?? null;
                    $response['zipSha256'] = $versionData['zipSha256'] ?? '';
                    
                    $zipFileName = "ClubCheck-{$serverVersion}.zip";
                    $zipFilePath = __DIR__ . '/../../uploads/' . $zipFileName;
                    if (file_exists($zipFilePath)) {
                        if (empty($response['zipFileSize'])) {
                            $response['zipFileSize'] = filesize($zipFilePath);
                        }
                        if (empty($response['zipSha256'])) {
                            $response['zipSha256'] = hash_file('sha256', $zipFilePath);
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

    public function downloadZip()
    {
        // Obtener versión desde la base de datos
        $versionData = $this->versionModel->getLatestVersion();

        // Verificar que haya información de ZIP en la base de datos
        if (empty($versionData['zipUrl'])) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Archivo ZIP no encontrado',
                'message' => 'No hay archivo ZIP disponible para la versión actual',
                'version' => $versionData['latestVersion']
            ]);
            exit;
        }

        // Obtener el nombre y ruta del archivo ZIP
        $zipFileName = "ClubCheck-{$versionData['latestVersion']}.zip";
        $zipFilePath = __DIR__ . '/../../uploads/' . $zipFileName;

        // Verificar que el archivo existe
        if (!file_exists($zipFilePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Archivo no encontrado',
                'message' => 'El archivo ZIP no existe en el servidor',
                'expectedFile' => $zipFileName,
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
                'filename' => $zipFileName,
                'version' => $versionData['latestVersion'],
                'size' => filesize($zipFilePath),
                'checksum' => hash_file('sha256', $zipFilePath),
                'lastModified' => filemtime($zipFilePath),
                'url' => $versionData['zipUrl'] ?? '',
                'releaseNotes' => $versionData['releaseNotes'] ?? '',
                'mandatory' => $versionData['mandatory'] ?? false
            ];

            echo json_encode($fileInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        } else {
            // Descarga directa del archivo ZIP (comportamiento por defecto)
            $fileSize = filesize($zipFilePath);
            
            // Headers para descarga de archivo ZIP
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Access-Control-Allow-Origin: *');

            // Leer y enviar el archivo en chunks para archivos grandes
            $chunkSize = 8192;
            $handle = fopen($zipFilePath, 'rb');
            
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
