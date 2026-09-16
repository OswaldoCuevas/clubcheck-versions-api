<?php

namespace Controllers;


use Core\Controller;

class AuthController extends Controller
{
    private function getSafeRedirectAfterLogin(): string
    {
        $redirect = $_SESSION['redirect_after_login'] ?? '/admin';
        unset($_SESSION['redirect_after_login']);

        if (!is_string($redirect) || $redirect === '') {
            return '/admin';
        }

        $path = parse_url($redirect, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path[0] !== '/') {
            return '/admin';
        }

        $blockedPaths = [
            '/favicon.ico',
            '/favicon.png',
            '/apple-touch-icon.png',
            '/apple-touch-icon-precomposed.png',
            '/login',
            '/logout',
        ];

        if (in_array($path, $blockedPaths, true) || strpos($path, '/public/assets/') === 0 || strpos($path, '/assets/') === 0) {
            return '/admin';
        }

        return $redirect;
    }

    public function login()
    {
        // Si ya está autenticado, redirigir
        if ($this->userModel->isAuthenticated()) {
            $this->redirect('/admin');
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
                        $redirect = $this->getSafeRedirectAfterLogin();
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
