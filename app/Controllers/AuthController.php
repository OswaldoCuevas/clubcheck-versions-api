<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';

use Core\Controller;

class AuthController extends Controller
{
    public function login()
    {
        // Si ya está autenticado, redirigir
        if ($this->userModel->isAuthenticated()) {
            $this->redirect('/');
        }

        $error = '';

        // Procesar login si es POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar CSRF token
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!$this->userModel->validateCsrfToken($csrfToken)) {
                $error = 'Token de seguridad inválido';
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $rememberMe = isset($_POST['remember']);
                
                if (empty($username) || empty($password)) {
                    $error = 'Por favor complete todos los campos';
                } else {
                    if ($this->userModel->authenticate($username, $password, $rememberMe)) {
                        // Redirigir después del login exitoso
                        $redirect = $_SESSION['redirect_after_login'] ?? '/';
                        unset($_SESSION['redirect_after_login']);
                        $this->redirect($redirect);
                    } else {
                        $error = 'Usuario o contraseña incorrectos';
                    }
                }
            }
        }

        // Generar token CSRF
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $data = [
            'title' => 'Iniciar Sesión - ClubCheck',
            'error' => $error,
            'csrf_token' => $_SESSION['csrf_token']
        ];

        $this->view('auth/login', $data);
    }

    public function logout()
    {
        $this->userModel->logout();
        $this->redirect('/login');
    }

    /**
     * Middleware para requerir autenticación
     */
    public function requireAuth()
    {
        if (!$this->userModel->isAuthenticated()) {
            $this->redirect('/login');
        }
    }

    /**
     * Verificar si está autenticado
     */
    public function isAuthenticated()
    {
        return $this->userModel->isAuthenticated();
    }

    /**
     * Obtener usuario actual
     */
    public function getCurrentUser()
    {
        return $this->userModel->getCurrentUser();
    }
}