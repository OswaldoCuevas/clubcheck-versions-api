<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/VersionModel.php';

use Core\Controller;
use Models\VersionModel;

class HomeController extends Controller
{
    private $uploadDir;
    private $versionModel;

    public function __construct()
    {
        parent::__construct();
        
        // Configuración
        $this->uploadDir = __DIR__ . '/../../uploads/';
        $this->versionModel = new VersionModel();
        
        // Crear directorio de uploads si no existe
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0777, true)) {
                die('Error: No se pudo crear el directorio de uploads. Verifica los permisos.');
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

        // Leer versión actual desde la base de datos
        $currentVersion = $this->versionModel->getLatestVersion();

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
        $version = trim($_POST['version'] ?? '');
        $mandatory = isset($_POST['mandatory']);
        $releaseNotes = trim($_POST['releaseNotes'] ?? '');
        
        // Validar versión
        if (!preg_match('/^\d+\.\d+\.\d+\.\d+$/', $version)) {
            return [
                'message' => 'La versión debe tener el formato X.X.X.X (ej: 1.2.3.0)',
                'type' => 'error'
            ];
        }
        
        if (!isset($_FILES['exeFile']) || $_FILES['exeFile']['error'] !== UPLOAD_ERR_OK) {
            return [
                'message' => 'Error al subir el archivo',
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
            
            // Actualizar base de datos
            $uploadDateTime = date('Y-m-d H:i:s'); // Formato MySQL
            
            if ($this->versionModel->saveVersion($version, $fileUrl, $sha256, $mandatory, $releaseNotes, $uploadDateTime)) {
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
                    'message' => 'Error al guardar la información de versión en la base de datos',
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
