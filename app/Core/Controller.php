<?php

namespace Core;

class Controller
{
    protected $userModel;

    public function __construct()
    {
        // Incluir modelo de usuario y URL helper
        require_once __DIR__ . '/../Models/UserModel.php';
        require_once __DIR__ . '/UrlHelper.php';
        $this->userModel = new \Models\UserModel();
    }

    protected function view($viewName, $data = [])
    {
        // Extraer variables para la vista
        extract($data);

        // Incluir la vista
        $viewPath = __DIR__ . "/../Views/{$viewName}.php";
        
        if (!file_exists($viewPath)) {
            throw new \Exception("View {$viewName} not found");
        }

        include $viewPath;
    }

    protected function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    protected function redirect($url)
    {
        // Usar UrlHelper para generar URLs correctas
        $fullUrl = UrlHelper::url($url);
        header("Location: {$fullUrl}");
        exit;
    }

    protected function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function requireAuth()
    {
        if (!$this->userModel->isAuthenticated()) {
            // Guardar la ruta actual sin el subdirectorio base
            $currentPath = UrlHelper::getCurrentPath();
            $_SESSION['redirect_after_login'] = $currentPath;
            UrlHelper::redirect('/login');
        }
    }

    protected function requirePermission($permission)
    {
        $this->requireAuth();
        
        if (!$this->userModel->hasPermission($permission)) {
            http_response_code(403);
            echo "403 - Acceso denegado";
            exit;
        }
    }
}
