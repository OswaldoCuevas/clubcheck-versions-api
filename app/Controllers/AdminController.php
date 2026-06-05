<?php

namespace Controllers;

use Core\Controller;
use Models\CustomerRegistryModel;
use Models\CustomerWebLoginAttemptModel;
use Models\WhatsAppConfigurationModel;
use Models\DownloadLogModel;
use Models\LicenseLogModel;
use App\Services\WhatsAppService;
use App\Services\CustomerStatsService;
use App\Services\StripeService;
use App\Services\LicenseService;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/CustomerRegistryModel.php';
require_once __DIR__ . '/../Models/CustomerWebLoginAttemptModel.php';
require_once __DIR__ . '/../Models/WhatsAppConfigurationModel.php';
require_once __DIR__ . '/../Models/DownloadLogModel.php';
require_once __DIR__ . '/../Models/LicenseLogModel.php';
require_once __DIR__ . '/../Services/WhatsAppService.php';
require_once __DIR__ . '/../Services/CustomerStatsService.php';
require_once __DIR__ . '/../Services/StripeService.php';
require_once __DIR__ . '/../Services/LicenseService.php';

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

    public function customerLoginAttempts()
    {
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();
        $attemptModel = new CustomerWebLoginAttemptModel();

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(10, min(200, (int) $_GET['perPage'])) : 50;
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? 'all',
            'codeAccess' => $_GET['codeAccess'] ?? '',
            'from' => $_GET['from'] ?? '',
            'to' => $_GET['to'] ?? '',
        ];

        $data = [
            'currentUser' => $currentUser,
            'title' => 'Intentos de Login Web - ClubCheck',
            'attempts' => $attemptModel->getAttempts($filters, $page, $perPage),
            'summary' => $attemptModel->getSummary(),
            'filters' => $filters,
            'isAuthenticated' => true,
        ];

        $this->view('admin/customer-login-attempts', $data);
    }

    public function customerLoginAttemptsJson()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $attemptModel = new CustomerWebLoginAttemptModel();
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(10, min(200, (int) $_GET['perPage'])) : 50;
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? 'all',
            'codeAccess' => $_GET['codeAccess'] ?? '',
            'from' => $_GET['from'] ?? '',
            'to' => $_GET['to'] ?? '',
        ];

        $this->json([
            'attempts' => $attemptModel->getAttempts($filters, $page, $perPage),
            'summary' => $attemptModel->getSummary(),
            'generatedAt' => date('Y-m-d H:i:s'),
        ]);
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

    public function regenerateAccessKey()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';

        if ($customerId === '') {
            $this->json(['error' => 'customerId es obligatorio'], 422);
        }

        $registry = new CustomerRegistryModel();
        $result = $registry->regenerateAccessKey($customerId);

        if (!$result) {
            $this->json(['error' => 'Cliente no encontrado'], 404);
        }

        $this->json([
            'status' => 'regenerated',
            'customerId' => $result['customerId'],
            'accessKey' => $result['accessKey'],
            'customer' => $result['customer'],
        ]);
    }

    /**
     * POST|DELETE /admin/api/customers/:customerId
     * API: Elimina un cliente desde el panel administrativo
     */
    public function deleteCustomerJson(string $customerId)
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $customerId = trim((string) $customerId);

        if ($customerId === '') {
            $this->json(['error' => 'customerId es obligatorio'], 422);
        }

        $registry = new CustomerRegistryModel();
        $deleted = $registry->deleteCustomer($customerId);

        if (!$deleted) {
            $this->json(['error' => 'Cliente no encontrado'], 404);
        }

        $this->json([
            'success' => true,
            'message' => 'Cliente eliminado correctamente'
        ]);
    }

    public function saveCustomerJson()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';

        $registry = new CustomerRegistryModel();

        // Validar si el cliente existe
        $existing = $customerId !== '' ? $registry->getCustomer($customerId) : null;

        // Construir atributos a guardar
        $attributes = [];

        if (array_key_exists('name', $payload)) {
            $attributes['name'] = $payload['name'] !== null ? trim((string) $payload['name']) : null;
        }

        if (array_key_exists('email', $payload)) {
            $email = $payload['email'];
            if ($email !== null && $email !== '') {
                $email = trim((string) $email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->json(['error' => 'Formato de correo inválido'], 422);
                }
            } else {
                $email = null;
            }
            $attributes['email'] = $email;
        }

        if (array_key_exists('phone', $payload)) {
            $phone = $payload['phone'];
            $attributes['phone'] = $phone !== null ? trim((string) $phone) : null;
        }

        if (array_key_exists('deviceName', $payload)) {
            $deviceName = $payload['deviceName'];
            $attributes['deviceName'] = $deviceName !== null ? trim((string) $deviceName) : null;
        }

        if (array_key_exists('billingId', $payload)) {
            $billingId = $payload['billingId'];
            if ($billingId !== null) {
                $billingId = trim((string) $billingId);
            }
            $attributes['billingId'] = $billingId === '' ? null : $billingId;
        }

        if (array_key_exists('planCode', $payload)) {
            $planCode = $payload['planCode'];
            if ($planCode !== null && $planCode !== '') {
                $planCode = trim((string) $planCode);
                if (mb_strlen($planCode) > 50) {
                    $this->json(['error' => 'El PlanCode debe tener máximo 50 caracteres'], 422);
                }
            } else {
                $planCode = null;
            }
            $attributes['planCode'] = $planCode;
        }

        if (array_key_exists('token', $payload)) {
            $token = $payload['token'];
            $attributes['token'] = $token !== null ? trim((string) $token) : null;
        }

        if (array_key_exists('isActive', $payload)) {
            $attributes['isActive'] = (bool) $payload['isActive'];
        }

        // Validar que se enviaron atributos
        if (empty($attributes)) {
            $this->json(['error' => 'No se enviaron atributos'], 422);
        }

        // Si es un nuevo cliente, validar campos obligatorios
        if ($existing === null) {
            if (!array_key_exists('name', $attributes) || $attributes['name'] === null || $attributes['name'] === '') {
                $this->json(['error' => 'El nombre es obligatorio al crear un cliente'], 422);
            }

            // Generar ID automático si no se proporcionó
            if ($customerId === '') {
                $customerId = $this->generateCustomerId();
            }
        }

        try {
            if ($existing === null) {
                // Crear nuevo cliente usando upsertCustomer
                $customer = $registry->upsertCustomer($customerId, $attributes);
                
                $response = [
                    'status' => 'created',
                    'customer' => $customer,
                ];

                // El accessKey se genera en upsertCustomer cuando es un cliente nuevo
                // Pero no se devuelve en el resultado del método, así que no podemos mostrarlo aquí
                // El admin deberá usar regenerateAccessKey si lo necesita

                $this->json($response, 201);
            } else {
                // Actualizar cliente existente
                $customer = $registry->upsertCustomer($customerId, $attributes);

                $this->json([
                    'status' => 'updated',
                    'customer' => $customer,
                ]);
            }
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'email_already_registered') {
                $this->json([
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ], 409);
            }

            $this->json([
                'error' => 'Error al guardar el cliente: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function generateCustomerId(): string
    {
        try {
            $random = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $random = hash('sha256', uniqid('', true));
        }

        return 'cus_' . substr($random, 0, 32);
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
                            'includeRemoved' => false,
                        ],
                        'responseExample' => [
                            'customerApiId' => 'CLUB-001',
                            'bulks' => [
                                'users' => [
                                    [
                                        'Id' => '11111111-2222-3333-4444-555555555555',
                                        'Fullname' => 'Juan Pérez',
                                        'PhoneNumber' => '555-000-1234',
                                        'CustomerApiId' => 'CLUB-001',
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
                                'products' => [],
                                'productPrices' => [],
                                'productStock' => [],
                                'cashRegisters' => [],
                                'saleTickets' => [],
                                'saleTicketItems' => [],
                                'subscriptionPeriods' => [],
                                'syncStatus' => [],
                                'accessDevices' => [],
                                'operationsAccessDevices' => [],
                                'userAccessDevices' => [],
                                'migrations' => [],
                                'barcodeLookupCache' => [],
                            ],
                        ],
                        'notes' => [
                            'customerApiId es obligatorio y debe existir en la base de datos.',
                            'Cada bulk se entrega como un arreglo independiente; si no hay datos, el arreglo se devuelve vacío.',
                            'Por defecto solo se devuelven registros con Removed = 0; puedes enviar includeRemoved=true para todos los bulks o includeRemovedByBulk con banderas individuales para obtener también los removidos.',
                            'Bulks activos en sincronizacion: users, subscriptions, attendances, administrators, historyOperations, infoMySubscription, appSettings, products, productPrices, productStock, cashRegisters, saleTickets, saleTicketItems, subscriptionPeriods, syncStatus, accessDevices, operationsAccessDevices, userAccessDevices.',
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
                                        'Id' => '11111111-2222-3333-4444-555555555555',
                                        'Fullname' => 'Juan Pérez',
                                        'PhoneNumber' => '555-000-1234',
                                        'Removed' => 0,
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
                                        'Id' => '22222222-3333-4444-5555-666666666666',
                                        'EnableLimitNotifications' => 1,
                                        'LimitDays' => 3,
                                    ],
                                ],
                                'sentMessages' => [],
                                'products' => [
                                    [
                                        'Id' => '33333333-4444-5555-6666-777777777777',
                                        'Code' => 'PROD-001',
                                        'Name' => 'Membresía Mensual',
                                        'Active' => 1,
                                        'IsDeleted' => 0,
                                    ],
                                ],
                                'productPrices' => [],
                                'productStock' => [],
                                'cashRegisters' => [],
                                'saleTickets' => [],
                                'saleTicketItems' => [],
                                'subscriptionPeriods' => [],
                                'syncStatus' => [],
                                'accessDevices' => [],
                                'operationsAccessDevices' => [],
                                'userAccessDevices' => [],
                                'migrations' => [],
                                'barcodeLookupCache' => [],
                            ],
                        ],
                        'responseExample' => [
                            'customerApiId' => 'CLUB-001',
                            'bulks' => [
                                'users' => [
                                    [
                                        'id' => '11111111-2222-3333-4444-555555555555',
                                        'success' => true,
                                    ],
                                ],
                                'appSettings' => [
                                    [
                                        'id' => '22222222-3333-4444-5555-666666666666',
                                        'success' => true,
                                    ],
                                ],
                                'products' => [
                                    [
                                        'id' => '33333333-4444-5555-6666-777777777777',
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
                                'productPrices' => [],
                                'productStock' => [],
                                'cashRegisters' => [],
                                'saleTickets' => [],
                                'saleTicketItems' => [],
                                'subscriptionPeriods' => [],
                                'syncStatus' => [],
                                'accessDevices' => [],
                                'operationsAccessDevices' => [],
                                'userAccessDevices' => [],
                                'migrations' => [],
                                'barcodeLookupCache' => [],
                            ],
                        ],
                        'notes' => [
                            'Cada registro debe incluir su UUID; si ya existe se actualiza, de lo contrario se inserta.',
                            'Si ocurre un error en un registro individual, el proceso continúa y ese UUID se marca con success=false.',
                            'Los bulks deben enviarse como arreglos, incluso si están vacíos.',
                            'Los modelos con Removed o IsDeleted aceptan 0/1 o true/false para realizar borrados lógicos desde el push.',
                            'Bulks activos en sincronizacion: users, subscriptions, attendances, administrators, historyOperations, infoMySubscription, appSettings, products, productPrices, productStock, cashRegisters, saleTickets, saleTicketItems, subscriptionPeriods, syncStatus, accessDevices, operationsAccessDevices, userAccessDevices.',
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

    // ==================== WHATSAPP CONFIGURATIONS ====================

    /**
     * GET /admin/whatsapp
     * Vista principal del CRUD de configuraciones de WhatsApp
     */
    public function whatsapp()
    {
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();
        $registry = new CustomerRegistryModel();
        $customers = $registry->getCustomers();

        $data = [
            'currentUser' => $currentUser,
            'title' => 'Configuración WhatsApp - ClubCheck',
            'customers' => $customers,
            'isAuthenticated' => true,
        ];

        $this->view('admin/whatsapp', $data);
    }

    /**
     * GET /admin/api/whatsapp
     * API: Lista todas las configuraciones de WhatsApp
     */
    public function whatsappListJson()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $configModel = new WhatsAppConfigurationModel();
        $configs = $configModel->getAllWithCustomerInfo();

        $this->json([
            'success' => true,
            'count' => count($configs),
            'configurations' => $configs,
        ]);
    }

    /**
     * POST /admin/api/whatsapp
     * API: Crea una nueva configuración de WhatsApp
     */
    public function whatsappCreateJson()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $payload = json_decode(file_get_contents('php://input'), true);

        // Validar campos requeridos
        $required = ['customerId', 'phoneNumber', 'phoneNumberId', 'businessName'];
        foreach ($required as $field) {
            if (empty($payload[$field])) {
                $this->json(['error' => "Campo requerido: {$field}"], 422);
            }
        }

        $configModel = new WhatsAppConfigurationModel();

        // Verificar si el customer ya tiene configuración
        if ($configModel->customerHasConfiguration($payload['customerId'])) {
            $this->json(['error' => 'Este cliente ya tiene una configuración de WhatsApp'], 422);
        }

        // Crear configuración
        $result = $configModel->create([
            'CustomerId' => $payload['customerId'],
            'PhoneNumber' => $payload['phoneNumber'],
            'PhoneNumberId' => $payload['phoneNumberId'],
            'AccessToken' => $payload['accessToken'] ?? null,
            'BusinessName' => $payload['businessName'],
            'BusinessAddress' => $payload['address'] ?? null,
            'BusinessDescription' => $payload['description'] ?? null,
            'BusinessEmail' => $payload['email'] ?? null,
            'CreatedBy' => 'admin'
        ]);

        if (!$result['success']) {
            $this->json(['error' => $result['error']], 422);
        }

        // Si se proporcionó accessToken, intentar registrar el número en WhatsApp
        $whatsappRegistered = false;
        $whatsappError = null;

        if (!empty($payload['accessToken']) && !empty($payload['registerInWhatsApp'])) {
            $whatsappService = new WhatsAppService();
            $registerResult = $whatsappService->registerPhoneNumber(
                $payload['phoneNumberId'],
                $payload['accessToken']
            );
            $whatsappRegistered = $registerResult['success'];
            $whatsappError = $registerResult['error'];
        }

        $config = $configModel->findById($result['id']);

        $this->json([
            'success' => true,
            'id' => $result['id'],
            'configuration' => $config,
            'whatsappRegistered' => $whatsappRegistered,
            'whatsappError' => $whatsappError
        ]);
    }

    /**
     * DELETE /admin/api/whatsapp/:id
     * API: Elimina una configuración de WhatsApp
     */
    public function whatsappDeleteJson(string $id)
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        if (empty($id)) {
            $this->json(['error' => 'ID es requerido'], 422);
        }

        $configModel = new WhatsAppConfigurationModel();
        $result = $configModel->delete($id);

        if (!$result['success']) {
            $this->json(['error' => $result['error']], 404);
        }

        $this->json([
            'success' => true,
            'message' => 'Configuración eliminada correctamente'
        ]);
    }

    /**
     * POST /admin/api/whatsapp/:id/register
     * API: Registra el número en WhatsApp API para que aparezca activo
     */
    public function whatsappRegisterJson(string $id)
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $configModel = new WhatsAppConfigurationModel();
        $config = $configModel->findById($id);

        if (!$config) {
            $this->json(['error' => 'Configuración no encontrada'], 404);
        }

        if (empty($config['AccessToken'])) {
            $this->json(['error' => 'Esta configuración no tiene Access Token'], 422);
        }

        $whatsappService = new WhatsAppService();
        $result = $whatsappService->registerPhoneNumber(
            $config['PhoneNumberId'],
            $config['AccessToken']
        );

        if (!$result['success']) {
            $this->json([
                'success' => false,
                'error' => $result['error'],
                'httpCode' => $result['httpCode'] ?? null
            ], 422);
        }

        $this->json([
            'success' => true,
            'message' => 'Número registrado correctamente en WhatsApp',
            'response' => $result['response'] ?? null
        ]);
    }

    /**
     * GET /admin/api/whatsapp/:id/status
     * API: Obtiene el estado del número en WhatsApp
     */
    public function whatsappStatusJson(string $id)
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $configModel = new WhatsAppConfigurationModel();
        $config = $configModel->findById($id);

        if (!$config) {
            $this->json(['error' => 'Configuración no encontrada'], 404);
        }

        if (empty($config['AccessToken'])) {
            $this->json(['error' => 'Esta configuración no tiene Access Token'], 422);
        }

        $whatsappService = new WhatsAppService();
        $result = $whatsappService->getPhoneNumberStatus(
            $config['PhoneNumberId'],
            $config['AccessToken']
        );

        $this->json([
            'success' => $result['success'],
            'status' => $result['status'],
            'data' => $result['data'] ?? null,
            'error' => $result['error']
        ]);
    }

    // ===== Gestión de Tokens JWT =====

    /**
     * GET /admin/jwt-tokens
     * Muestra la página de administración de tokens JWT
     */
    public function jwtTokens()
    {
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();

        $data = [
            'currentUser' => $currentUser,
            'title' => 'Tokens JWT - ClubCheck',
            'isAuthenticated' => true,
        ];

        $this->view('admin/jwt-tokens', $data);
    }

    /**
     * GET /admin/api/jwt-tokens
     * API: Lista todos los clientes con información de tokens JWT e IPs
     */
    public function jwtTokensJson()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        require_once __DIR__ . '/../Models/CustomerIpLogModel.php';
        
        $registry = new CustomerRegistryModel();
        $ipLogModel = new \Models\CustomerIpLogModel();

        // Obtener estadísticas de JWT
        $stats = $registry->getJwtStats();

        // Obtener resumen de clientes con IPs
        $customerSummary = $ipLogModel->getCustomerIpSummary();

        $this->json([
            'success' => true,
            'stats' => $stats,
            'customers' => $customerSummary,
        ]);
    }

    /**
     * POST /admin/api/jwt-tokens/create
     * API: Crea un nuevo token JWT para un cliente
     */
    public function createJwtToken()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';
        $expiresIn = isset($payload['expiresIn']) ? (int) $payload['expiresIn'] : null;

        if ($customerId === '') {
            $this->json(['error' => 'customerId es obligatorio'], 422);
        }

        require_once __DIR__ . '/../Services/CustomerJwtService.php';
        $customerJwtService = new \App\Services\CustomerJwtService();

        try {
            $result = $customerJwtService->renewCustomerToken($customerId, $expiresIn);

            if (!$result) {
                $this->json([
                    'success' => false,
                    'error' => 'No se pudo crear el token. Verifique que el cliente exista, esté activo y tenga un token de máquina registrado.'
                ], 422);
            }

            $this->json([
                'success' => true,
                'message' => 'Token JWT creado exitosamente',
                'data' => [
                    'customerId' => $customerId,
                    'expiresAt' => $result['expiresAt'],
                    'expiresIn' => $result['expiresIn'],
                ]
            ]);
        } catch (\RuntimeException $e) {
            $errorMessages = [
                'customer_not_found' => 'Cliente no encontrado',
                'customer_inactive' => 'El cliente está inactivo',
                'machine_token_mismatch' => 'El token de máquina no coincide',
            ];

            $message = $errorMessages[$e->getMessage()] ?? $e->getMessage();
            $this->json(['success' => false, 'error' => $message], 422);
        }
    }

    /**
     * POST /admin/api/jwt-tokens/revoke
     * API: Revoca el token JWT de un cliente
     */
    public function revokeJwtToken()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';

        if ($customerId === '') {
            $this->json(['error' => 'customerId es obligatorio'], 422);
        }

        $registry = new CustomerRegistryModel();
        $result = $registry->revokeJwtToken($customerId);

        if (!$result) {
            $this->json(['success' => false, 'error' => 'Cliente no encontrado'], 404);
        }

        $this->json([
            'success' => true,
            'message' => 'Token JWT revocado exitosamente'
        ]);
    }

    /**
     * GET /admin/api/jwt-tokens/customer/:customerId/ips
     * API: Obtiene las IPs registradas de un cliente
     */
    public function customerIpsJson(string $customerId)
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $customerId = trim($customerId);

        if ($customerId === '') {
            $this->json(['error' => 'customerId es obligatorio'], 422);
        }

        require_once __DIR__ . '/../Models/CustomerIpLogModel.php';
        $ipLogModel = new \Models\CustomerIpLogModel();

        $ips = $ipLogModel->getCustomerIps($customerId);
        $hasMultiple = $ipLogModel->hasMultipleRecentIps($customerId);

        $this->json([
            'success' => true,
            'customerId' => $customerId,
            'hasMultipleRecentIps' => $hasMultiple,
            'ips' => $ips,
        ]);
    }

    /**
     * POST /admin/api/jwt-tokens/ips/:id/flag
     * API: Marca o desmarca una IP como sospechosa
     */
    public function flagIp(string $id)
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $flagged = isset($payload['flagged']) ? (bool) $payload['flagged'] : true;
        $reason = isset($payload['reason']) ? trim((string) $payload['reason']) : null;

        require_once __DIR__ . '/../Models/CustomerIpLogModel.php';
        $ipLogModel = new \Models\CustomerIpLogModel();

        $result = $ipLogModel->setFlagged($id, $flagged, $reason);

        if (!$result) {
            $this->json(['success' => false, 'error' => 'Registro de IP no encontrado'], 404);
        }

        $this->json([
            'success' => true,
            'message' => $flagged ? 'IP marcada como sospechosa' : 'Marca de IP eliminada'
        ]);
    }

    /**
     * GET /admin/customer-stats
     * Vista: Estadísticas de clientes
     */
    public function customerStats()
    {
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();
        
        try {
            $statsService = new CustomerStatsService();
            $globalStats = $statsService->getGlobalStats();
            $customersStats = $statsService->getAllCustomersStats();
        } catch (\Throwable $e) {
            error_log('CustomerStats error: ' . $e->getMessage());
            $globalStats = [
                'totalCustomers' => 0,
                'activeCustomers' => 0,
                'totalUsers' => 0,
                'totalSubscriptions' => 0,
                'totalActiveSubscriptions' => 0,
                'totalProducts' => 0,
                'totalAttendances' => 0,
                'todayAttendances' => 0,
                'monthlyMessages' => 0,
            ];
            $customersStats = [];
        }

        $data = [
            'currentUser' => $currentUser,
            'title' => 'Estadísticas de Clientes - ClubCheck',
            'globalStats' => $globalStats,
            'customersStats' => $customersStats,
            'isAuthenticated' => true,
        ];

        $this->view('admin/customer-stats', $data);
    }

    /**
     * GET /admin/api/customer-stats
     * API: Obtiene estadísticas de todos los clientes en JSON
     */
    public function customerStatsJson()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        try {
            $statsService = new CustomerStatsService();
            $globalStats = $statsService->getGlobalStats();
            $customersStats = $statsService->getAllCustomersStats();

            $this->json([
                'global' => $globalStats,
                'customers' => $customersStats,
                'generatedAt' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('CustomerStatsJson error: ' . $e->getMessage());
            $this->json([
                'error' => 'Error al obtener estadísticas',
                'debug' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /admin/api/customer-stats/:customerId
     * API: Obtiene estadísticas de un cliente específico
     */
    public function customerStatsDetailJson(string $customerId)
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $customerId = trim($customerId);
        if ($customerId === '') {
            $this->json(['error' => 'customerId es obligatorio'], 422);
        }

        try {
            $registry = new CustomerRegistryModel();
            $customer = $registry->getCustomer($customerId);

            if (!$customer) {
                $this->json(['error' => 'Cliente no encontrado'], 404);
            }

            $statsService = new CustomerStatsService();
            $stats = $statsService->getCustomerStats($customerId);

            $this->json([
                'customer' => $customer,
                'stats' => $stats,
                'generatedAt' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('CustomerStatsDetailJson error: ' . $e->getMessage());
            $this->json([
                'error' => 'Error al obtener estadísticas del cliente',
                'debug' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== HISTORIAL DE DESCARGAS ====================

    /**
     * GET /admin/downloads
     * Vista: Historial de descargas agrupado por IP
     */
    public function downloads()
    {
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();
        $downloadLogModel = new DownloadLogModel();
        
        // Obtener parámetros de paginación y búsqueda
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(10, min(100, (int) $_GET['perPage'])) : 20;
        $searchIp = isset($_GET['ip']) ? trim($_GET['ip']) : null;
        
        // Obtener datos
        $downloads = $downloadLogModel->getDownloadsGroupedByIp($page, $perPage, $searchIp);
        $summary = $downloadLogModel->getDownloadsSummary();

        $data = [
            'currentUser' => $currentUser,
            'title' => 'Historial de Descargas - ClubCheck',
            'downloads' => $downloads,
            'summary' => $summary,
            'searchIp' => $searchIp,
            'isAuthenticated' => true,
        ];

        $this->view('admin/downloads', $data);
    }

    /**
     * GET /admin/api/downloads
     * API: Obtiene el historial de descargas paginado y agrupado por IP
     */
    public function downloadsJson()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $downloadLogModel = new DownloadLogModel();
        
        // Obtener parámetros
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(10, min(100, (int) $_GET['perPage'])) : 20;
        $searchIp = isset($_GET['ip']) ? trim($_GET['ip']) : null;
        
        // Obtener datos
        $downloads = $downloadLogModel->getDownloadsGroupedByIp($page, $perPage, $searchIp ?: null);
        $summary = $downloadLogModel->getDownloadsSummary();

        $this->json([
            'downloads' => $downloads,
            'summary' => $summary,
            'generatedAt' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * GET /admin/api/downloads/ip/:ipAddress
     * API: Obtiene el detalle de descargas de una IP específica
     */
    public function downloadsByIpJson(string $ipAddress)
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $ipAddress = urldecode(trim($ipAddress));

        if ($ipAddress === '') {
            $this->json(['error' => 'IP es obligatoria'], 422);
        }

        $downloadLogModel = new DownloadLogModel();
        $downloads = $downloadLogModel->getDownloadsByIp($ipAddress);

        $this->json([
            'ipAddress' => $ipAddress,
            'count' => count($downloads),
            'downloads' => $downloads,
        ]);
    }

    // ==================== LICENCIAS ====================

    /**
     * GET /admin/licenses
     * Panel de licencias generadas
     */
    public function licenses(): void
    {
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();

        $data = [
            'currentUser'     => $currentUser,
            'title'           => 'Licencias - ClubCheck',
            'isAuthenticated' => true,
        ];

        $this->view('admin/licenses', $data);
    }

    /**
     * GET /admin/api/licenses
     * JSON: lista de todas las licencias generadas
     */
    public function licensesJson(): void
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $logModel = new LicenseLogModel();
        $licenses = $logModel->getAll();

        $this->json([
            'count'    => count($licenses),
            'licenses' => $licenses,
        ]);
    }

    /**
     * POST /admin/api/licenses/generate
     * Genera una licencia para un cliente desde el panel de administración.
     *
     * Body: {
     *   "customerId":     "...",        // ID interno ClubCheck (requerido)
     *   "planLookupKey":  "...",        // opcional: usa la suscripción activa de Stripe si no se indica
     *   "machineToken":   "...",        // opcional: usa el token registrado del cliente si no se indica
     *   "expiresAt":      1780000000   // opcional: solo para planes recurrentes sin suscripción Stripe
     * }
     */
    public function generateLicenseAdmin(): void
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->json(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $payload    = json_decode(file_get_contents('php://input'), true) ?? [];
        $customerId = trim($payload['customerId'] ?? '');

        if ($customerId === '') {
            $this->json(['error' => 'customerId es obligatorio'], 422);
        }

        // Obtener cliente
        $registry = new CustomerRegistryModel();
        $customer = $registry->getCustomer($customerId);
        if (!$customer) {
            $this->json(['error' => 'Cliente no encontrado'], 404);
        }

        $billingId    = $customer['billingId'] ?? null;
        $machineToken = trim($payload['machineToken'] ?? '') ?: ($customer['token'] ?? null);

        // Siempre consultar Stripe: verificar suscripción activa y obtener expiración
        if (!$billingId) {
            $this->json(['error' => 'El cliente no tiene billingId vinculado a Stripe'], 422);
        }

        $config        = require __DIR__ . '/../../config/stripe.php';
        $appMode       = $_ENV['APP_MODE'] ?? 'DEV';
        $testClockId   = ($appMode === 'DEV') ? ($config['test_clock_id'] ?? null) : null;
        $stripeService = new StripeService($config['secret_key'], $testClockId);

        $activeResult = $stripeService->getActiveSubscription($billingId);

        if (!($activeResult['success'] ?? false)) {
            $this->json(['error' => 'Error al consultar Stripe: ' . ($activeResult['error'] ?? '')], 400);
        }

        $pl  = $activeResult['permanent_license'] ?? null;
        $sub = $activeResult['subscription']      ?? null;

        if (!$pl && !($activeResult['has_subscription'] ?? false)) {
            $this->json(['error' => 'El cliente no tiene suscripción activa ni licencia permanente en Stripe. No se puede generar la licencia.'], 400);
        }

        // Determinar plan, nombre, permanente y expiración (expiración siempre desde Stripe)
        $planLookupKey = trim($payload['planLookupKey'] ?? '');
        $isPermanent   = false;
        $expiresAt     = null;
        $planName      = $planLookupKey;

        if ($pl) {
            if ($planLookupKey === '') $planLookupKey = $pl['lookup_key']  ?? 'permanent';
            $planName    = $pl['price_name']  ?? 'Licencia Permanente';
            $isPermanent = true;
            $expiresAt   = null;
        } else {
            if ($planLookupKey === '') $planLookupKey = $sub['lookup_key'] ?? '';
            $planName    = $sub['price_name'] ?? $planLookupKey;
            $isPermanent = false;
            $expiresAt   = $sub['current_period_end'] ?? null;
        }

        // Si se pasó una fecha personalizada y es válida, usarla (incluso para permanentes)
        $customExpiresAt = isset($payload['expiresAt']) ? (int)$payload['expiresAt'] : null;
        if ($customExpiresAt && $customExpiresAt > 0) {
            $expiresAt = $customExpiresAt;
        }

        if (empty($planLookupKey)) {
            $this->json(['error' => 'No se pudo determinar el plan'], 400);
        }

        // Resolver nombre y reglas desde config
        $config = require __DIR__ . '/../../config/stripe.php';
        $rules  = null;
        foreach ($config['plans'] ?? [] as $planCfg) {
            if (($planCfg['lookup_key'] ?? '') === $planLookupKey) {
                $planName    = $planCfg['name']  ?? $planName;
                $isPermanent = ($planCfg['type'] ?? '') === 'permanent' ? true : $isPermanent;
                $rules       = $planCfg['rules'] ?? null;
                break;
            }
        }

        // Instanciar LicenseService
        try {
            $licenseService = new LicenseService();
        } catch (\Exception $e) {
            $this->json(['error' => 'LicenseService no disponible: verifique las claves RSA en .env'], 500);
        }

        $currentUser   = $this->userModel->getCurrentUser();
        $adminUsername = $currentUser['username'] ?? 'admin';
        $db = new \Database();

        $dbRow = $db->fetchOne(
            'SELECT Id, Token, TokenJwt FROM Customers WHERE Id = ? LIMIT 1',
            [$customerId]
        );

        $customerJwt = trim((string)($dbRow['TokenJwt'] ?? ''));
        if ($customerJwt === '' && $dbRow) {
            require_once __DIR__ . '/../Services/JwtService.php';
            $jwtService  = new \App\Services\JwtService();
            $customerJwt = $jwtService->createToken([
                'cid' => $dbRow['Id'],
                'mkt' => $machineToken ?: ($dbRow['Token'] ?? ''),
                'typ' => 'customer',
            ], 0);

            $db->update(
                'Customers',
                [
                    'TokenJwt'          => $customerJwt,
                    'TokenJwtCreatedAt' => date('Y-m-d H:i:s'),
                    'TokenJwtExpiresAt' => null,
                ],
                'Id = ?',
                [$dbRow['Id']]
            );
        }

        try {
            $token = $licenseService->generateLicense(
                $billingId ?? $customerId,
                $customer['name']  ?? '',
                $customer['email'] ?? '',
                $planLookupKey,
                $planName,
                $isPermanent,
                $expiresAt,
                $machineToken,
                $rules,
                $customerJwt !== '' ? $customerJwt : null
            );

            $licenseFile = $licenseService->generateLicenseFile(
                $token,
                $customer['name'] ?? '',
                $planName,
                $isPermanent,
                $expiresAt,
                $machineToken,
                $rules
            );

            // Registrar en LicenseLogs como generado por administrador
            $logModel = new LicenseLogModel();
            $logModel->createLog([
                'CustomerId'    => $customerId,
                'BillingId'     => $billingId,
                'CustomerName'  => $customer['name']  ?? '',
                'CustomerEmail' => $customer['email'] ?? '',
                'PlanLookupKey' => $planLookupKey,
                'PlanName'      => $planName,
                'IsPermanent'   => $isPermanent,
                'ExpiresAt'     => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : null,
                'MachineToken'  => $machineToken,
                'LicenseToken'  => $token,
                'CreatedBy'     => 'admin',
                'AdminUsername' => $adminUsername,
            ]);

            $this->json([
                'success'        => true,
                'license_token'  => $token,
                'license_file'   => $licenseFile,
                'plan_lookup_key'=> $planLookupKey,
                'plan_name'      => $planName,
                'is_permanent'   => $isPermanent,
                'expires_at'     => $expiresAt,
                'customer_name'  => $customer['name']  ?? '',
                'customer_email' => $customer['email'] ?? '',
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => 'Error al generar licencia: ' . $e->getMessage()], 500);
        }
    }
}

