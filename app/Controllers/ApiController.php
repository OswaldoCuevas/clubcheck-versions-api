<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';

use Core\Controller;

class ApiController extends Controller
{
    public function version()
    {
        // Configurar headers para API
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');

        $versionFile = __DIR__ . '/../../version.json';

        // Verificar si el archivo existe
        if (!file_exists($versionFile)) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Version file not found',
                'message' => 'No hay información de versiones disponible'
            ]);
            exit;
        }

        // Leer y retornar el contenido del archivo JSON
        $versionData = file_get_contents($versionFile);
        $decodedData = json_decode($versionData, true);

        if ($decodedData === null) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Invalid JSON format',
                'message' => 'El archivo de versión está corrupto'
            ]);
            exit;
        }

        // Verificar si hay una versión válida
        $hasValidVersion = !empty($decodedData['latestVersion']) && $decodedData['latestVersion'] !== '0.0.0.0';

        // Agregar información adicional útil para el cliente
        $decodedData['lastUpdated'] = filemtime($versionFile);
        $decodedData['hasUpdate'] = $hasValidVersion;

        // Agregar URLs de descarga y verificación
        if ($hasValidVersion) {
            require_once __DIR__ . '/../Core/UrlHelper.php';
            $baseUrl = \Core\UrlHelper::absoluteUrl('');
            
            $decodedData['downloadUrl'] = $baseUrl . '/api/download';
            $decodedData['checkUpdateUrl'] = $baseUrl . '/api/check-update';
            $decodedData['directUrl'] = $decodedData['url'] ?? ''; // URL directa al archivo
            
            // Verificar si el archivo existe físicamente
            require_once __DIR__ . '/../Helpers/FileHelper.php';
            $fileName = getAppFileName($decodedData['latestVersion']);
            $filePath = findExistingAppFile($decodedData['latestVersion']) ?: (__DIR__ . '/../../uploads/' . $fileName);
            $decodedData['fileExists'] = file_exists($filePath);
            
            if ($decodedData['fileExists']) {
                $decodedData['fileSize'] = filesize($filePath);
                $decodedData['fileDate'] = filemtime($filePath);
            }
        }

        echo json_encode($decodedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function checkUpdate()
    {
        // Configurar headers para API
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Content-Type');

        $versionFile = __DIR__ . '/../../version.json';

        // Verificar si el archivo existe
        if (!file_exists($versionFile)) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Version file not found',
                'hasUpdate' => false,
                'message' => 'No hay información de versiones disponible'
            ]);
            exit;
        }

        // Leer información de la versión
        $versionData = json_decode(file_get_contents($versionFile), true);

        if ($versionData === null) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Invalid JSON format',
                'hasUpdate' => false,
                'message' => 'El archivo de versión está corrupto'
            ]);
            exit;
        }

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
                $response['url'] = $versionData['url'] ?? '';
                
                // Información adicional del archivo
                require_once __DIR__ . '/../Helpers/FileHelper.php';
                $fileName = getAppFileName($serverVersion);
                $filePath = findExistingAppFile($serverVersion) ?: (__DIR__ . '/../../uploads/' . $fileName);
                if (file_exists($filePath)) {
                    $response['fileSize'] = filesize($filePath);
                    $response['checksum'] = hash_file('sha256', $filePath);
                }
            }
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function download()
    {
        $versionFile = __DIR__ . '/../../version.json';

        // Verificar si el archivo de versión existe
        if (!file_exists($versionFile)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Version file not found',
                'message' => 'No hay información de versiones disponible'
            ]);
            exit;
        }

        // Leer información de la versión
        $versionData = json_decode(file_get_contents($versionFile), true);

        if ($versionData === null || json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Invalid JSON format',
                'message' => 'El archivo de versión está corrupto'
            ]);
            exit;
        }

        // Verificar que hay una versión válida
        if (empty($versionData['latestVersion']) || $versionData['latestVersion'] === '0.0.0.0') {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'No version available',
                'message' => 'No hay versiones disponibles para descargar'
            ]);
            exit;
        }

        // Construir la ruta del archivo de la última versión
        require_once __DIR__ . '/../Helpers/FileHelper.php';
        $fileName = getAppFileName($versionData['latestVersion']);
        $filePath = findExistingAppFile($versionData['latestVersion']) ?: (__DIR__ . '/../../uploads/' . $fileName);

        // Verificar que el archivo existe
        if (!file_exists($filePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'File not found',
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
                echo json_encode(['error' => 'Cannot read file']);
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
