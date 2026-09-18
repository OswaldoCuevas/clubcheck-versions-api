<?php

namespace Controllers;

use Core\Controller;

class AuthController extends Controller
{
    private function getRedirectAfterLogin(): string
    {
        unset($_SESSION['redirect_after_login']);

        return '/admin/dashboard';
    }

    public function login()
    {
        if ($this->userModel->isAuthenticated()) {
            $this->redirect('/admin/dashboard');
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!$this->userModel->validateCsrfToken($csrfToken)) {
                $error = 'Token de seguridad invalido';
            } else {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $rememberMe = isset($_POST['remember']);

                if ($username === '' || $password === '') {
                    $error = 'Por favor complete todos los campos';
                } elseif ($this->userModel->authenticate($username, $password, $rememberMe)) {
                    $this->redirect($this->getRedirectAfterLogin());
                } else {
                    $error = 'Usuario o contrasena incorrectos';
                }
            }
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $data = [
            'title' => 'Iniciar Sesion - ClubCheck',
            'error' => $error,
            'csrf_token' => $_SESSION['csrf_token'],
        ];

        $this->view('auth/login', $data);
    }

    public function logout()
    {
        $this->userModel->logout();
        $this->redirect('/login');
    }

    public function requireAuth()
    {
        if (!$this->userModel->isAuthenticated()) {
            $this->redirect('/login');
        }
    }

    public function isAuthenticated()
    {
        return $this->userModel->isAuthenticated();
    }

    public function getCurrentUser()
    {
        return $this->userModel->getCurrentUser();
    }
}
