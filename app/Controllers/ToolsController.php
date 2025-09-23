<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';

use Core\Controller;

class ToolsController extends Controller
{
    public function passwordGenerator()
    {
        // Requerir permisos de administrador
        $this->requirePermission('admin_access');
        
        $currentUser = $this->userModel->getCurrentUser();
        $message = '';
        $messageType = '';
        $generatedData = null;
        
        // Procesar formulario
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'hash_password':
                    $password = trim($_POST['password'] ?? '');
                    if (empty($password)) {
                        $message = 'La contraseña no puede estar vacía';
                        $messageType = 'error';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $generatedData = [
                            'type' => 'hash',
                            'password' => $password,
                            'hash' => $hash
                        ];
                        $message = 'Hash generado correctamente';
                        $messageType = 'success';
                    }
                    break;
                    
                case 'generate_random':
                    $length = (int)($_POST['length'] ?? 12);
                    $length = max(4, min(50, $length)); // Límites de seguridad
                    
                    $includeUpper = isset($_POST['include_upper']);
                    $includeLower = isset($_POST['include_lower']);
                    $includeNumbers = isset($_POST['include_numbers']);
                    $includeSymbols = isset($_POST['include_symbols']);
                    
                    if (!$includeUpper && !$includeLower && !$includeNumbers && !$includeSymbols) {
                        $message = 'Debe seleccionar al menos un tipo de carácter';
                        $messageType = 'error';
                    } else {
                        $password = $this->generateRandomPassword($length, $includeUpper, $includeLower, $includeNumbers, $includeSymbols);
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        $generatedData = [
                            'type' => 'random',
                            'password' => $password,
                            'hash' => $hash,
                            'length' => $length
                        ];
                        $message = 'Contraseña generada correctamente';
                        $messageType = 'success';
                    }
                    break;
            }
        }
        
        $data = [
            'currentUser' => $currentUser,
            'message' => $message,
            'messageType' => $messageType,
            'generatedData' => $generatedData,
            'title' => 'Generador de Contraseñas - ClubCheck'
        ];
        
        $this->view('tools/password-generator', $data);
    }

    private function generateRandomPassword($length, $includeUpper, $includeLower, $includeNumbers, $includeSymbols)
    {
        $chars = '';
        
        if ($includeUpper) $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($includeLower) $chars .= 'abcdefghijklmnopqrstuvwxyz';
        if ($includeNumbers) $chars .= '0123456789';
        if ($includeSymbols) $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $password = '';
        $charsLength = strlen($chars);
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }
        
        return $password;
    }

    public function quickHash()
    {
        // Esta función podría manejar quick-hash.php si existe
        $this->requirePermission('admin_access');
        
        $data = [
            'title' => 'Hash Rápido - ClubCheck'
        ];
        
        $this->view('tools/quick-hash', $data);
    }

    public function generatePassword()
    {
        // Esta función podría manejar generate-password.php CLI si existe
        $this->requirePermission('admin_access');
        
        $data = [
            'title' => 'Generador CLI - ClubCheck'
        ];
        
        $this->view('tools/generate-password', $data);
    }
}
