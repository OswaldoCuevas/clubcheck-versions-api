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
                                'billingId' => 'BILL-001',
                                'planCode' => 'PLAN-PLUS',
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
                            'billingId' => 'BILL-001',
                            'planCode' => 'PLAN-PLUS',
                            'name' => 'Club House',
                            'email' => 'admin@clubhouse.mx',
                            'phone' => '+52 55 1234 5678',
                            'deviceName' => 'POS-01',
                            'token' => 'abc123',
                            'privacyAcceptance' => [
                                'documentVersion' => '2025-01',
                                'documentUrl' => 'https://clubcheck.mx/legal/privacidad-2025.pdf',
                                'ipAddress' => '203.0.113.45',
                                'acceptedAt' => '2025-10-10T11:31:22Z',
                                'userAgent' => 'ClubCheck Desktop 2.5.0',
                            ],
                        ],
                        'responseExample' => [
                            'found' => false,
                            'registered' => true,
                            'customer' => [
                                'customerId' => 'CLUB-001',
                                'billingId' => 'BILL-001',
                                'planCode' => 'PLAN-PLUS',
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
                            'accessKey' => 'Q6C7-PR9M-4J2K-Y8ZB',
                        ],
                        'notes' => [
                            'Campos obligatorios: customerId, name, token y el objeto privacyAcceptance.',
                            'privacyAcceptance debe incluir documentVersion, documentUrl, ipAddress y opcionalmente acceptedAt y userAgent.',
                            'Los campos billingId, planCode, email, phone y deviceName son opcionales (se almacenan como null si no se envían).',
                            'El campo planCode admite hasta 50 caracteres; envía null o una cadena vacía para limpiarlo.',
                            'Si el cliente ya existe devuelve HTTP 200 con found=true sin modificar datos.',
                            'La respuesta incluye accessKey únicamente cuando el cliente es nuevo; guárdala porque no se vuelve a mostrar.',
                            'Configura la variable de entorno ACCESS_KEY_SECRET para permitir el hash HMAC-SHA512 de la AccessKey.',
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/login',
                        'description' => 'Permite a la app de escritorio autenticarse con email y AccessKey.',
                        'requestExample' => [
                            'email' => 'admin@clubhouse.mx',
                            'accessKey' => 'Q6C7-PR9M-4J2K-Y8ZB',
                            'deviceName' => 'POS-01',
                        ],
                        'responseExample' => [
                            'status' => 'success',
                            'customer' => [
                                'customerId' => 'CLUB-001',
                                'billingId' => 'BILL-001',
                                'planCode' => 'PLAN-PLUS',
                                'name' => 'Club House',
                                'email' => 'admin@clubhouse.mx',
                                'phone' => '+52 55 1234 5678',
                                'deviceName' => 'POS-01',
                                'token' => 'abc123',
                            ],
                        ],
                        'notes' => [
                            'La AccessKey enviada debe coincidir con la versión en texto plano que se otorgó al registrar el cliente.',
                            'Solo se permite un máximo de 5 intentos fallidos por correo cada 60 minutos (HTTP 429 cuando se supera).',
                            'waitingForToken debe ser true para validar; de lo contrario se devuelve HTTP 409.',
                            'Se recomienda reutilizar deviceName para facilitar el monitoreo del equipo que inicia sesión.',
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/desktop/pull',
                        'description' => 'Descarga los registros sincronizables del cliente indicado agrupados por bulk.',
                        'requestExample' => [
                            'customerApiId' => 'CLUB-001',
                        ],
                        'responseExample' => [
                            'customerApiId' => 'CLUB-001',
                            'bulks' => [
                                'users' => [
                                    [
                                        'UserId' => 1,
                                        'Fullname' => 'Juan Pérez',
                                        'PhoneNumber' => '555-000-1234',
                                        'CustomerApiId' => 'CLUB-001',
                                        'Uuid' => 'a1111111-2222-3333-4444-555555555555',
                                    ],
                                ],
                                'subscriptions' => [],
                                'attendances' => [],
                                'administrators' => [],
                                'sendEmailsAdmin' => [],
                                'historyOperations' => [],
                                'infoMySubscription' => [],
                                'whatsapp' => [],
                                'appSettings' => [],
                                'sentMessages' => [],
                            ],
                        ],
                        'notes' => [
                            'customerApiId es obligatorio y debe existir en la base de datos.',
                            'Cada bulk se entrega como un arreglo independiente; si no hay datos, el arreglo se devuelve vacío.',
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/desktop/push',
                        'description' => 'Recibe cambios por bulk (insert/update) y responde el estatus por UUID.',
                        'requestExample' => [
                            'customerApiId' => 'CLUB-001',
                            'bulks' => [
                                'users' => [
                                    [
                                        'UserId' => 1,
                                        'Fullname' => 'Juan Pérez',
                                        'PhoneNumber' => '555-000-1234',
                                        'Removed' => 0,
                                        'Uuid' => 'a1111111-2222-3333-4444-555555555555',
                                    ],
                                ],
                                'subscriptions' => [],
                                'attendances' => [],
                                'administrators' => [],
                                'sendEmailsAdmin' => [],
                                'historyOperations' => [],
                                'infoMySubscription' => [],
                                'whatsapp' => [],
                                'appSettings' => [
                                    [
                                        'SettingId' => 1,
                                        'EnableLimitNotifications' => 1,
                                        'LimitDays' => 3,
                                        'Uuid' => 'f4444444-5555-6666-7777-888888888888',
                                    ],
                                ],
                                'sentMessages' => [],
                            ],
                        ],
                        'responseExample' => [
                            'customerApiId' => 'CLUB-001',
                            'bulks' => [
                                'users' => [
                                    [
                                        'uuid' => 'a1111111-2222-3333-4444-555555555555',
                                        'success' => true,
                                    ],
                                ],
                                'appSettings' => [
                                    [
                                        'uuid' => 'f4444444-5555-6666-7777-888888888888',
                                        'success' => true,
                                    ],
                                ],
                                'subscriptions' => [],
                                'attendances' => [],
                                'administrators' => [],
                                'sendEmailsAdmin' => [],
                                'historyOperations' => [],
                                'infoMySubscription' => [],
                                'whatsapp' => [],
                                'sentMessages' => [],
                            ],
                        ],
                        'notes' => [
                            'Cada registro debe incluir su UUID; si ya existe se actualiza, de lo contrario se inserta.',
                            'Si ocurre un error en un registro individual, el proceso continúa y ese UUID se marca con success=false.',
                            'Los bulks deben enviarse como arreglos, incluso si están vacíos.',
                        ],
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/api/customers/save',
                        'description' => 'Crea o actualiza atributos de un cliente.',
                        'requestExample' => [
                            'customerId' => 'CLUB-001',
                            'name' => 'Club House',
                            'billingId' => 'BILL-001',
                            'planCode' => 'PLAN-PLUS',
                            'email' => 'admin@clubhouse.mx',
                            'phone' => '+52 55 1234 5678',
                            'deviceName' => 'POS-01',
                            'token' => 'abc123',
                            'isActive' => true,
                            'privacyAcceptance' => [
                                'documentVersion' => '2025-01',
                                'documentUrl' => 'https://clubcheck.mx/legal/privacidad-2025.pdf',
                                'ipAddress' => '203.0.113.45',
                                'acceptedAt' => '2025-10-10T11:31:22Z',
                            ],
                        ],
                        'responseExample' => [
                            'status' => 'created',
                            'customer' => [
                                'customerId' => 'CLUB-001',
                                'billingId' => 'BILL-001',
                                'planCode' => 'PLAN-PLUS',
                                'name' => 'Club House',
                                'email' => 'admin@clubhouse.mx',
                                'phone' => '+52 55 1234 5678',
                                'deviceName' => 'POS-01',
                                'token' => 'abc123',
                                'isActive' => true,
                                'waitingForToken' => false,
                                'createdAt' => 1699900300,
                                'updatedAt' => 1699900300,
                            ],
                            'accessKey' => 'Q6C7-PR9M-4J2K-Y8ZB',
                        ],
                        'notes' => [
                            'Solo se actualizan atributos presentes en el body.',
                            'Incluye planCode para reflejar el plan comercial asignado (usa null o deja vacío para eliminarlo).',
                            'Incluye billingId para enlazar el cliente con tu sistema de facturación.',
                            'Cuando se crea un cliente nuevo (customerId ausente o inexistente) es obligatorio enviar privacyAcceptance.',
                            'acceptedAt puede omitirse para usar la hora del servidor; userAgent es opcional.',
                            'En creaciones exitosas la respuesta incluye accessKey con el valor en texto plano; se almacena hasheada y no se vuelve a exponer.',
                        ],
                    ],
                    [
                        'method' => 'PATCH',
                        'path' => '/api/customers',
                        'description' => 'Actualiza parcialmente los datos básicos de un cliente (planCode, nombre, correo o teléfono).',
                        'requestExample' => [
                            'customerId' => 'CLUB-001',
                            'planCode' => 'PLAN-PRO-2025',
                            'name' => 'Club House Reforma',
                            'email' => 'contacto@clubhouse.mx',
                            'phone' => '+52 55 5555 8888',
                        ],
                        'responseExample' => [
                            'status' => 'updated',
                            'customer' => [
                                'customerId' => 'CLUB-001',
                                'billingId' => 'BILL-001',
                                'planCode' => 'PLAN-PRO-2025',
                                'name' => 'Club House Reforma',
                                'email' => 'contacto@clubhouse.mx',
                                'phone' => '+52 55 5555 8888',
                                'isActive' => true,
                                'waitingForToken' => false,
                                'updatedAt' => 1699900900,
                            ],
                        ],
                        'notes' => [
                            'Incluye los campos que desees actualizar. Si envías planCode o email vacíos se borran del registro.',
                            'El planCode admite máximo 50 caracteres. Verifica que el correo tenga un formato válido.',
                            'Responde HTTP 404 si el customerId no existe.',
                        ],
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
                            'billingId' => 'BILL-001',
                            'planCode' => 'PLAN-PLUS',
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
                                'billingId' => 'BILL-001',
                                'planCode' => 'PLAN-PLUS',
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
