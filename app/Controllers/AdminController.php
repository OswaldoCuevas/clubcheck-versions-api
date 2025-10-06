<?php

namespace Controllers;

use Core\Controller;
use Models\CustomerRegistryModel;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/CustomerRegistryModel.php';

class AdminController extends Controller
{
    public function index()
    {
        // Requerir permisos de administrador
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();

        $data = [
            'currentUser' => $currentUser,
            'title' => 'Panel Administrativo - ClubCheck',
            'isAuthenticated' => true,
        ];

        $this->view('admin/index', $data);
    }

    public function customers()
    {
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();
        $registry = new CustomerRegistryModel();
        $customers = $registry->getCustomers();

        $data = [
            'currentUser' => $currentUser,
            'title' => 'Clientes - ClubCheck',
            'customers' => $customers,
            'isAuthenticated' => true,
        ];

        $this->view('admin/customers', $data);
    }

    public function customersJson()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $registry = new CustomerRegistryModel();
        $customers = $registry->getCustomers();

        $this->json([
            'count' => count($customers),
            'customers' => $customers,
        ]);
    }

    public function apiDocs()
    {
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();

        $sections = [
            [
                'title' => 'Clientes y tokens',
                'description' => 'Administración de registros persistentes para cada instalación del escritorio ClubCheck.',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/api/customers/:customerId',
                        'description' => 'Obtiene los datos de un cliente específico usando su ID en la URL.',
                        'responseExample' => [
                            'customer' => [
                                'customerId' => 'CLUB-001',
                                'name' => 'Club House',
                                'email' => 'admin@clubhouse.mx',
                                'phone' => '+52 55 1234 5678',
                                'deviceName' => 'POS-01',
                                'token' => 'abc123',
                                'isActive' => true,
                                'waitingForToken' => false,
                                'waitingSince' => null,
                                'tokenUpdatedAt' => 1699900000,
                                'lastSeen' => 1699900500,
                            ],
                        ],
                        'notes' => [
                            'Invoca la URL como /api/customers/CLUB-001 (reemplaza por el ID real).',
                            'También se acepta la variante con query string ?customerId=CLUB-001.',
                            'Devuelve HTTP 404 si el cliente no existe.',
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/register',
                        'description' => 'Crea un nuevo cliente solo si el ID no existe.',
                        'requestExample' => [
                            'customerId' => 'CLUB-001',
                            'name' => 'Club House',
                            'email' => 'admin@clubhouse.mx',
                            'phone' => '+52 55 1234 5678',
                            'deviceName' => 'POS-01',
                            'token' => 'abc123',
                        ],
                        'responseExample' => [
                            'found' => false,
                            'registered' => true,
                            'customer' => [
                                'customerId' => 'CLUB-001',
                                'name' => 'Club House',
                                'email' => 'admin@clubhouse.mx',
                                'phone' => '+52 55 1234 5678',
                                'deviceName' => 'POS-01',
                                'token' => 'abc123',
                                'isActive' => true,
                                'waitingForToken' => false,
                                'createdAt' => 1699900000,
                                'updatedAt' => 1699900000,
                            ],
                        ],
                        'notes' => [
                            'Campos obligatorios: customerId, name y token.',
                            'Los campos email, phone y deviceName son opcionales (se almacenan como null si no se envían).',
                            'Si el cliente ya existe devuelve HTTP 200 con found=true sin modificar datos.',
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/save',
                        'description' => 'Crea o actualiza atributos de un cliente.',
                        'requestExample' => [
                            'customerId' => 'CLUB-001',
                            'name' => 'Club House',
                            'email' => 'admin@clubhouse.mx',
                            'phone' => '+52 55 1234 5678',
                            'deviceName' => 'POS-01',
                            'token' => 'abc123',
                            'isActive' => true,
                        ],
                        'responseExample' => [
                            'status' => 'updated',
                            'customer' => [
                                'customerId' => 'CLUB-001',
                                'name' => 'Club House',
                                'email' => 'admin@clubhouse.mx',
                                'phone' => '+52 55 1234 5678',
                                'deviceName' => 'POS-01',
                                'token' => 'abc123',
                                'isActive' => true,
                                'waitingForToken' => false,
                                'updatedAt' => 1699900300,
                            ],
                        ],
                        'notes' => ['Solo se actualizan atributos presentes en el body.'],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/token/await',
                        'description' => 'Marca si el cliente espera un nuevo token.',
                        'requestExample' => [
                            'customerId' => 'CLUB-001',
                            'waiting' => true,
                        ],
                        'responseExample' => [
                            'customerId' => 'CLUB-001',
                            'waitingForToken' => true,
                            'waitingSince' => 1699900600,
                        ],
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/customers/token',
                        'description' => 'Recupera el token actual de un cliente.',
                        'query' => ['customerId' => 'CLUB-001'],
                        'responseExample' => [
                            'customerId' => 'CLUB-001',
                            'name' => 'Club House',
                            'deviceName' => 'POS-01',
                            'token' => 'abc123',
                            'isActive' => true,
                            'waitingForToken' => false,
                            'tokenUpdatedAt' => 1699900000,
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/token/register',
                        'description' => 'Registra un token nuevo desde el cliente de escritorio.',
                        'requestExample' => [
                            'customerId' => 'CLUB-001',
                            'token' => 'token-desde-app',
                            'deviceName' => 'POS-01',
                        ],
                        'responseExample' => [
                            'status' => 'updated',
                            'customer' => [
                                'customerId' => 'CLUB-001',
                                'token' => 'token-desde-app',
                                'deviceName' => 'POS-01',
                                'waitingForToken' => false,
                                'waitingSince' => null,
                                'isActive' => true,
                                'tokenUpdatedAt' => 1699900900,
                                'updatedAt' => 1699900900,
                            ],
                        ],
                        'notes' => [
                            'Requiere que waitingForToken sea true.',
                            'El campo deviceName es opcional; si se envía se normaliza y actualiza en el registro del cliente.',
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Sesiones de cliente (heartbeat)',
                'description' => 'Endpoints opcionales para monitorear la presencia en línea del escritorio.',
                'endpoints' => [
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/sessions/start',
                        'description' => 'Registra o reutiliza una sesión activa.',
                        'requestExample' => [
                            'customerId' => 'CLUB-001',
                            'deviceId' => 'POS-01',
                            'appVersion' => '2.5.0',
                            'metadata' => [
                                'os' => 'Windows 11',
                                'workstationLocation' => 'Mostrador principal',
                            ],
                        ],
                        'responseExample' => [
                            'sessionId' => 'f4d9a8b5c0e1d2f3a4b5c6d7e8f9a0b1',
                            'customerId' => 'CLUB-001',
                            'status' => 'active',
                            'heartbeatInterval' => 60,
                            'expiresAt' => 1699900960,
                            'reused' => false,
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/sessions/heartbeat',
                        'description' => 'Renueva la sesión existente.',
                        'requestExample' => [
                            'sessionId' => 'f4d9a8b5c0e1d2f3a4b5c6d7e8f9a0b1',
                            'metadata' => [
                                'message' => 'still-alive',
                            ],
                        ],
                        'responseExample' => [
                            'sessionId' => 'f4d9a8b5c0e1d2f3a4b5c6d7e8f9a0b1',
                            'status' => 'active',
                            'lastSeen' => 1699900680,
                            'expiresAt' => 1699900860,
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/sessions/end',
                        'description' => 'Termina una sesión explícitamente.',
                        'requestExample' => [
                            'sessionId' => 'f4d9a8b5c0e1d2f3a4b5c6d7e8f9a0b1',
                            'reason' => 'user_logout',
                        ],
                        'responseExample' => [
                            'sessionId' => 'f4d9a8b5c0e1d2f3a4b5c6d7e8f9a0b1',
                            'status' => 'inactive',
                        ],
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/api/customers/sessions/active',
                        'description' => 'Consulta las sesiones activas (opcionalmente filtradas por cliente).',
                        'query' => ['customerId' => 'CLUB-001'],
                        'responseExample' => [
                            'count' => 1,
                            'sessions' => [
                                [
                                    'sessionId' => 'f4d9a8b5c0e1d2f3a4b5c6d7e8f9a0b1',
                                    'customerId' => 'CLUB-001',
                                    'deviceId' => 'POS-01',
                                    'status' => 'active',
                                    'lastSeen' => 1699900680,
                                ],
                            ],
                            'heartbeatInterval' => 60,
                            'gracePeriod' => 180,
                        ],
                    ],
                ],
            ],
        ];

        $data = [
            'currentUser' => $currentUser,
            'title' => 'API Endpoints - ClubCheck',
            'sections' => $sections,
            'isAuthenticated' => true,
        ];

        $this->view('admin/api-docs', $data);
    }
}
