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
        
        // Validar archivo EXE
        if (!isset($_FILES['exeFile']) || $_FILES['exeFile']['error'] !== UPLOAD_ERR_OK) {
            return [
                'message' => 'Error al subir el archivo EXE. Es obligatorio.',
                'type' => 'error'
            ];
        }
        
        // Validar archivo Setup (segundo EXE)
        if (!isset($_FILES['setupFile']) || $_FILES['setupFile']['error'] !== UPLOAD_ERR_OK) {
            return [
                'message' => 'Error al subir el archivo Setup. Es obligatorio.',
                'type' => 'error'
            ];
        }
        
        $exeFile = $_FILES['exeFile'];
        $setupFile = $_FILES['setupFile'];
        
        // Validar extensión EXE
        $exeExt = strtolower(pathinfo($exeFile['name'], PATHINFO_EXTENSION));
        if ($exeExt !== 'exe') {
            return [
                'message' => 'El archivo ejecutable debe ser .exe',
                'type' => 'error'
            ];
        }
        
        // Validar extensión Setup (también debe ser EXE)
        $setupExt = strtolower(pathinfo($setupFile['name'], PATHINFO_EXTENSION));
        if ($setupExt !== 'exe') {
            return [
                'message' => 'El archivo Setup debe ser .exe',
                'type' => 'error'
            ];
        }
        
        // Generar nombres de archivos
        require_once __DIR__ . '/../Helpers/FileHelper.php';
        $exeFileName = getAppFileName($version);
        $setupFileName = "ClubCheckSetup-{$version}.exe";
        
        $exeFilePath = $this->uploadDir . $exeFileName;
        $setupFilePath = $this->uploadDir . $setupFileName;
        
        $isReplacement = false;
        
        // Si los archivos ya existen, crear backups y eliminarlos
        if (file_exists($exeFilePath) || file_exists($setupFilePath)) {
            $isReplacement = true;
            $timestamp = time();
            
            // Backup del EXE si existe
            if (file_exists($exeFilePath)) {
                $backupPath = $this->uploadDir . 'backup_' . $timestamp . '_' . $exeFileName;
                if (copy($exeFilePath, $backupPath)) {
                    error_log("Backup creado para {$exeFileName}: " . basename($backupPath));
                }
                if (!unlink($exeFilePath)) {
                    return [
                        'message' => 'No se pudo eliminar el archivo EXE existente',
                        'type' => 'error'
                    ];
                }
            }
            
            // Backup del Setup si existe
            if (file_exists($setupFilePath)) {
                $backupPath = $this->uploadDir . 'backup_' . $timestamp . '_' . $setupFileName;
                if (copy($setupFilePath, $backupPath)) {
                    error_log("Backup creado para {$setupFileName}: " . basename($backupPath));
                }
                if (!unlink($setupFilePath)) {
                    return [
                        'message' => 'No se pudo eliminar el archivo Setup existente',
                        'type' => 'error'
                    ];
                }
            }
        }
        
        // Mover archivo EXE
        if (!move_uploaded_file($exeFile['tmp_name'], $exeFilePath)) {
            return [
                'message' => 'Error al guardar el archivo EXE',
                'type' => 'error'
            ];
        }
        
        // Mover archivo Setup
        if (!move_uploaded_file($setupFile['tmp_name'], $setupFilePath)) {
            // Si falla el Setup, eliminar el EXE ya subido
            unlink($exeFilePath);
            return [
                'message' => 'Error al guardar el archivo Setup',
                'type' => 'error'
            ];
        }
        
        // Calcular SHA256 de ambos archivos
        $exeSha256 = hash_file('sha256', $exeFilePath);
        $setupSha256 = hash_file('sha256', $setupFilePath);
        $setupFileSize = filesize($setupFilePath);
        
        // Generar URLs de los archivos
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host . dirname($_SERVER['REQUEST_URI'] ?? '/');
        $exeFileUrl = $baseUrl . '/uploads/' . $exeFileName;
        $setupFileUrl = $baseUrl . '/uploads/' . $setupFileName;
        
        // Actualizar base de datos
        $uploadDateTime = date('Y-m-d H:i:s'); // Formato MySQL
        
        if ($this->versionModel->saveVersion($version, $exeFileUrl, $exeSha256, $mandatory, $releaseNotes, $uploadDateTime, $setupFileUrl, $setupSha256, $setupFileSize)) {
            // Limpiar backups antiguos (mantener solo los últimos 5)
            $this->cleanOldBackups();
            
            if ($isReplacement) {
                return [
                    'message' => "Versión {$version} reemplazada exitosamente. Los archivos anteriores fueron respaldados automáticamente.",
                    'type' => 'success'
                ];
            } else {
                return [
                    'message' => "Versión {$version} subida exitosamente (EXE + Setup)",
                    'type' => 'success'
                ];
            }
        } else {
            // Si falla la BD, eliminar los archivos subidos
            unlink($exeFilePath);
            unlink($setupFilePath);
            return [
                'message' => 'Error al guardar la información de versión en la base de datos',
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
