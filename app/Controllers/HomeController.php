<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';

use Core\Controller;
use Exception;

class HomeController extends Controller
{
    private $uploadDir;
    private $versionFile;

    public function __construct()
    {
        parent::__construct();
        
        // Configuración
        $this->uploadDir = __DIR__ . '/../../uploads/';
        $this->versionFile = __DIR__ . '/../../version.json';
        
        // Crear directorio de uploads si no existe
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0777, true)) {
                die('Error: No se pudo crear el directorio de uploads. Verifica los permisos.');
            }
        }
        
        // Inicializar JSON de versión si no existe
        if (!file_exists($this->versionFile)) {
            $initialData = [
                'latestVersion' => '0.0.0.0',
                'url' => '',
                'sha256' => '',
                'mandatory' => false,
                'releaseNotes' => '',
                'uploadDate' => null,
                'timestamp' => null
            ];
            if (file_put_contents($this->versionFile, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
                die('Error: No se pudo crear el archivo version.json. Verifica los permisos.');
            }
        }
    }

    public function index()
    {
        // Verificar autenticación para la vista
        $isAuthenticated = $this->userModel->isAuthenticated();
        $canUpload = $this->userModel->hasPermission('upload_files');
        $currentUser = $this->userModel->getCurrentUser();

        $message = '';
        $messageType = '';

        // Si es una petición POST (subida), verificar permisos
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$isAuthenticated) {
                $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
                $this->redirect('/login');
            }
            
            if (!$canUpload) {
                $message = 'No tienes permisos para subir archivos';
                $messageType = 'error';
            } else {
                // Procesar la subida
                $result = $this->processUpload();
                $message = $result['message'];
                $messageType = $result['type'];
            }
        }

        // Leer versión actual
        $currentVersion = json_decode(file_get_contents($this->versionFile), true);

        // Datos para la vista
        $data = [
            'message' => $message,
            'messageType' => $messageType,
            'isAuthenticated' => $isAuthenticated,
            'canUpload' => $canUpload,
            'currentUser' => $currentUser,
            'currentVersion' => $currentVersion,
            'userModel' => $this->userModel
        ];

        $this->view('home/index', $data);
    }

    private function processUpload()
    {
        // Log completo de POST para debugging
        $logFile = __DIR__ . '/../../storage/logs/app.log';
        $timestamp = date('Y-m-d H:i:s');
        
        
        error_log("DEBUG - POST completo: " . print_r($_POST, true));
        error_log("DEBUG - FILES completo: " . print_r($_FILES, true));
        
        // También loguear en app.log para facilitar revisión
        file_put_contents($logFile, "[$timestamp] [debug] POST: " . json_encode($_POST) . PHP_EOL, FILE_APPEND);
        file_put_contents($logFile, "[$timestamp] [debug] FILES keys: " . json_encode(array_keys($_FILES)) . PHP_EOL, FILE_APPEND);
        
        $version = trim($_POST['version'] ?? '');
        $mandatory = isset($_POST['mandatory']);
        $releaseNotes = trim($_POST['releaseNotes'] ?? '');
        
        // Log para debugging
        error_log("DEBUG - Versión recibida: [" . $version . "] Longitud: " . strlen($version) . " Bytes: " . bin2hex($version));
        file_put_contents($logFile, "[$timestamp] [debug] Versión: [$version] Longitud: " . strlen($version) . " Hex: " . bin2hex($version) . PHP_EOL, FILE_APPEND);
        
        // Validar versión
        if (!preg_match('/^\d+\.\d+\.\d+\.\d+$/', $version)) {
            // Log detallado del error
            error_log("ERROR - Validación de versión falló. Valor: [" . $version . "] Hex: " . bin2hex($version));
            return [
                'message' => 'La versión debe tener el formato X.X.X.X (ej: 1.2.3.0). Valor recibido: "' . htmlspecialchars($version) . '"',
                'type' => 'error'
            ];
        }
        
        if (!isset($_FILES['exeFile']) || $_FILES['exeFile']['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $_FILES['exeFile']['error'] ?? 'FILE_NOT_SET';
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize en php.ini',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede MAX_FILE_SIZE en el formulario',
                UPLOAD_ERR_PARTIAL => 'El archivo solo se subió parcialmente',
                UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal',
                UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en disco',
                UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
            ];
            $errorMsg = $errorMessages[$errorCode] ?? "Error desconocido (código: $errorCode)";
            error_log("ERROR - Subida de archivo: " . $errorMsg);
            return [
                'message' => 'Error al subir el archivo: ' . $errorMsg,
                'type' => 'error'
            ];
        }
        
        $file = $_FILES['exeFile'];
        
        // Validar extensión
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExt !== 'exe') {
            return [
                'message' => 'Solo se permiten archivos .exe',
                'type' => 'error'
            ];
        }
        
        // Generar nombre del archivo usando helper
        require_once __DIR__ . '/../Helpers/FileHelper.php';
        $fileName = getAppFileName($version);
        $filePath = $this->uploadDir . $fileName;
        
        $isReplacement = false;
        
        // Si el archivo ya existe, crear backup y eliminarlo
        if (file_exists($filePath)) {
            $isReplacement = true;
            $backupPath = $this->uploadDir . 'backup_' . time() . '_' . $fileName;
            
            // Crear backup del archivo anterior
            if (copy($filePath, $backupPath)) {
                error_log("Backup creado para {$fileName}: " . basename($backupPath));
            }
            
            // Eliminar archivo existente
            if (!unlink($filePath)) {
                return [
                    'message' => 'No se pudo eliminar el archivo existente',
                    'type' => 'error'
                ];
            } else {
                error_log("Archivo existente eliminado: {$fileName}");
            }
        }
        
        // Mover archivo nuevo
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Calcular SHA256
            $sha256 = hash_file('sha256', $filePath);
            
            // URL del archivo
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host . dirname($_SERVER['REQUEST_URI'] ?? '/');
            $fileUrl = $baseUrl . '/uploads/' . $fileName;
            
            // Actualizar JSON
            $uploadDateTime = date('Y-m-d H:i:s P'); // Con zona horaria formato ISO (+/-HH:MM)
            $versionData = [
                'latestVersion' => $version,
                'url' => $fileUrl,
                'sha256' => $sha256,
                'mandatory' => $mandatory,
                'releaseNotes' => $releaseNotes,
                'uploadDate' => $uploadDateTime,
                'timestamp' => $uploadDateTime
            ];
            
            if (file_put_contents($this->versionFile, json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
                // Limpiar backups antiguos (mantener solo los últimos 5)
                $this->cleanOldBackups();
                
                if ($isReplacement) {
                    return [
                        'message' => "Versión {$version} reemplazada exitosamente. El archivo anterior fue respaldado automáticamente.",
                        'type' => 'success'
                    ];
                } else {
                    return [
                        'message' => "Versión {$version} subida exitosamente",
                        'type' => 'success'
                    ];
                }
            } else {
                return [
                    'message' => 'Error al guardar la información de versión',
                    'type' => 'error'
                ];
            }
        } else {
            return [
                'message' => 'Error al guardar el archivo',
                'type' => 'error'
            ];
        }
    }

    private function cleanOldBackups()
    {
        $backupFiles = glob($this->uploadDir . 'backup_*');
        if (count($backupFiles) > 5) {
            usort($backupFiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $filesToDelete = array_slice($backupFiles, 5);
            foreach ($filesToDelete as $oldBackup) {
                if (unlink($oldBackup)) {
                    error_log("Backup antiguo eliminado: " . basename($oldBackup));
                }
            }
        }
    }
}
