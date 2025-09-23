<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';

use Core\Controller;

class AdminController extends Controller
{
    public function index()
    {
        // Requerir permisos de administrador
        $this->requirePermission('admin_access');
        
        $currentUser = $this->userModel->getCurrentUser();
        
        $data = [
            'currentUser' => $currentUser,
            'title' => 'Panel Administrativo - ClubCheck'
        ];
        
        $this->view('admin/index', $data);
    }
}
