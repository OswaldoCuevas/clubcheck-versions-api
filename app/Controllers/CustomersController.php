<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/CustomerSessionModel.php';
require_once __DIR__ . '/../Models/CustomerRegistryModel.php';
require_once __DIR__ . '/../Models/UsersDesktopModel.php';
require_once __DIR__ . '/../Models/SubscriptionsDesktopModel.php';
require_once __DIR__ . '/../Models/AttendancesDesktopModel.php';
require_once __DIR__ . '/../Models/AdministratorsDesktopModel.php';
require_once __DIR__ . '/../Models/SendEmailsAdminDesktopModel.php';
require_once __DIR__ . '/../Models/HistoryOperationsDesktopModel.php';
require_once __DIR__ . '/../Models/InfoMySubscriptionDesktopModel.php';
require_once __DIR__ . '/../Models/WhatsAppDesktopModel.php';
require_once __DIR__ . '/../Models/AppSettingsDesktopModel.php';
require_once __DIR__ . '/../Models/SentMessagesDesktopModel.php';
require_once __DIR__ . '/../Models/MessageSentModel.php';
require_once __DIR__ . '/../Models/ProductDesktopModel.php';
require_once __DIR__ . '/../Models/ProductPriceDesktopModel.php';
require_once __DIR__ . '/../Models/ProductStockDesktopModel.php';
require_once __DIR__ . '/../Models/CashRegisterDesktopModel.php';
require_once __DIR__ . '/../Models/SaleTicketDesktopModel.php';
require_once __DIR__ . '/../Models/SaleTicketItemDesktopModel.php';
require_once __DIR__ . '/../Models/SubscriptionPeriodDesktopModel.php';
require_once __DIR__ . '/../Models/SyncStatusDesktopModel.php';
require_once __DIR__ . '/../Models/MigrationsDesktopModel.php';
require_once __DIR__ . '/../Models/BarcodeLookupCacheDesktopModel.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';
require_once __DIR__ . '/../Services/StripeService.php';

use Core\Controller;
use Models\CustomerRegistryModel;
use Models\CustomerSessionModel;
use Models\UsersDesktopModel;
use Models\SubscriptionsDesktopModel;
use Models\AttendancesDesktopModel;
use Models\AdministratorsDesktopModel;
use Models\SendEmailsAdminDesktopModel;
use Models\HistoryOperationsDesktopModel;
use Models\InfoMySubscriptionDesktopModel;
use Models\WhatsAppDesktopModel;
use Models\AppSettingsDesktopModel;
use Models\SentMessagesDesktopModel;
use Models\MessageSentModel;
use Models\ProductDesktopModel;
use Models\ProductPriceDesktopModel;
use Models\ProductStockDesktopModel;
use Models\CashRegisterDesktopModel;
use Models\SaleTicketDesktopModel;
use Models\SaleTicketItemDesktopModel;
use Models\SubscriptionPeriodDesktopModel;
use Models\SyncStatusDesktopModel;
use Models\MigrationsDesktopModel;
use Models\BarcodeLookupCacheDesktopModel;
use App\Services\StripeService;
use ApiHelper;

class CustomersController extends Controller
{
    private CustomerSessionModel $sessionModel;
    private CustomerRegistryModel $customerRegistry;
    private MessageSentModel $messageSentModel;
    private StripeService $stripeService;
    
    private array $desktopModels;

    public function __construct()
    {
        parent::__construct();

        $this->sessionModel = new CustomerSessionModel();
        $this->customerRegistry = new CustomerRegistryModel();
        $this->messageSentModel = new MessageSentModel();
        $this->desktopModels = $this->createDesktopModels();
        
        // Inicializar StripeService
        $stripeConfig = require __DIR__ . '/../../config/stripe.php';
        $appMode = $_ENV['APP_MODE'] ?? 'DEV';
        $testClockId = ($appMode === 'DEV') ? ($stripeConfig['test_clock_id'] ?? null) : null;
        
        $this->stripeService = new StripeService(
            $stripeConfig['secret_key'],
            $testClockId
        );
    }

    private function createDesktopModels(): array
    {
        return [
            'users' => new UsersDesktopModel(),
            'subscriptions' => new SubscriptionsDesktopModel(),
            'attendances' => new AttendancesDesktopModel(),
            'administrators' => new AdministratorsDesktopModel(),
            'sendEmailsAdmin' => new SendEmailsAdminDesktopModel(),
            'historyOperations' => new HistoryOperationsDesktopModel(),
            'infoMySubscription' => new InfoMySubscriptionDesktopModel(),
            'whatsapp' => new WhatsAppDesktopModel(),
            'appSettings' => new AppSettingsDesktopModel(),
            'sentMessages' => new SentMessagesDesktopModel(),
            'products' => new ProductDesktopModel(),
            'productPrices' => new ProductPriceDesktopModel(),
            'productStock' => new ProductStockDesktopModel(),
            'cashRegisters' => new CashRegisterDesktopModel(),
            'saleTickets' => new SaleTicketDesktopModel(),
            'saleTicketItems' => new SaleTicketItemDesktopModel(),
            'subscriptionPeriods' => new SubscriptionPeriodDesktopModel(),
            'syncStatus' => new SyncStatusDesktopModel(),
            'migrations' => new MigrationsDesktopModel(),
            'barcodeLookupCache' => new BarcodeLookupCacheDesktopModel(),
        ];
    }

    private function normalizePrivacyAcceptancePayload($value, bool $required): ?array
    {
        if ($value === null) {
            if ($required) {
                ApiHelper::respond([
                    'error' => 'Debes enviar el objeto privacyAcceptance con la aceptación de privacidad'
                ], 422);
            }

            return null;
        }

        if (!is_array($value)) {
            ApiHelper::respond([
                'error' => 'El campo privacyAcceptance debe ser un objeto con los datos de aceptación'
            ], 422);
        }

        $documentVersion = isset($value['documentVersion']) ? trim((string) $value['documentVersion']) : '';
        if ($documentVersion === '') {
            ApiHelper::respond([
                'error' => 'Debes indicar la versión del documento de privacidad (documentVersion)'
            ], 422);
        }
        if (mb_strlen($documentVersion) > 50) {
            ApiHelper::respond([
                'error' => 'documentVersion no puede superar los 50 caracteres'
            ], 422);
        }

        $documentUrl = isset($value['documentUrl']) ? trim((string) $value['documentUrl']) : '';
        if ($documentUrl === '') {
            ApiHelper::respond([
                'error' => 'Debes indicar la URL del documento aceptado (documentUrl)'
            ], 422);
        }
        if (mb_strlen($documentUrl) > 255) {
            ApiHelper::respond([
                'error' => 'documentUrl no puede superar los 255 caracteres'
            ], 422);
        }

        $ipAddress = isset($value['ipAddress']) ? trim((string) $value['ipAddress']) : '';
        if ($ipAddress === '') {
            ApiHelper::respond([
                'error' => 'Debes indicar la dirección IP del aceptante (ipAddress)'
            ], 422);
        }
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            ApiHelper::respond([
                'error' => 'La dirección IP proporcionada no es válida'
            ], 422);
        }

        $acceptedAtRaw = $value['acceptedAt'] ?? null;
        if ($acceptedAtRaw === null || $acceptedAtRaw === '') {
            $acceptedAt = date('Y-m-d H:i:s');
        } elseif (is_numeric($acceptedAtRaw)) {
            $timestamp = (int) $acceptedAtRaw;
            $acceptedAt = date('Y-m-d H:i:s', $timestamp);
        } else {
            $timestamp = strtotime((string) $acceptedAtRaw);
            if ($timestamp === false) {
                ApiHelper::respond([
                    'error' => 'El campo acceptedAt debe ser una fecha válida'
                ], 422);
            }
            $acceptedAt = date('Y-m-d H:i:s', $timestamp);
        }

        $userAgent = isset($value['userAgent']) ? trim((string) $value['userAgent']) : null;
        if ($userAgent !== null && $userAgent === '') {
            $userAgent = null;
        }
        if ($userAgent !== null && mb_strlen($userAgent) > 255) {
            $userAgent = mb_substr($userAgent, 0, 255);
        }

        return [
            'documentVersion' => $documentVersion,
            'documentUrl' => $documentUrl,
            'ipAddress' => $ipAddress,
            'acceptedAt' => $acceptedAt,
            'userAgent' => $userAgent,
        ];
    }

    private function normalizeSyncFlag($value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            if ($value === 0 || $value === 1) {
                return (bool) $value;
            }
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        ApiHelper::respond([
            'error' => sprintf('El campo %s debe ser booleano (true/false, 1/0)', $field)
        ], 422);

        return false;
    }

    public function startSession()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $customerId = $payload['customerId'] ?? null;
        $deviceId = $payload['deviceId'] ?? null;
        $metadata = $payload['metadata'] ?? null;

        if (empty($customerId)) {
            ApiHelper::respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        ApiHelper::validateMetadata($metadata);

        $this->sessionModel->purgeExpired($this->sessionConfig['grace_period'] ?? 180);

        $existing = $this->sessionModel->findActiveSession(
            $customerId,
            $deviceId,
            $this->sessionConfig['grace_period'] ?? 180
        );

        if ($existing) {
            $grace = $this->sessionConfig['grace_period'] ?? 180;
            $response = [
                'sessionId' => $existing['sessionId'],
                'customerId' => $existing['customerId'],
                'status' => $existing['status'],
                'startedAt' => $existing['startedAt'],
                'lastSeen' => $existing['lastSeen'],
                'heartbeatInterval' => $this->sessionConfig['heartbeat_interval'] ?? 60,
                'expiresAt' => $existing['lastSeen'] + $grace,
                'reused' => true,
            ];

            if (!empty($existing['deviceId'])) {
                $response['deviceId'] = $existing['deviceId'];
            }

            ApiHelper::respond($response, 200);
        }

        $session = $this->sessionModel->startSession([
            'customerId' => $customerId,
            'deviceId' => $deviceId,
            'appVersion' => $payload['appVersion'] ?? null,
            'metadata' => $metadata,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $grace = $this->sessionConfig['grace_period'] ?? 180;
        $response = [
            'sessionId' => $session['sessionId'],
            'customerId' => $session['customerId'],
            'status' => $session['status'],
            'startedAt' => $session['startedAt'],
            'lastSeen' => $session['lastSeen'],
            'heartbeatInterval' => $this->sessionConfig['heartbeat_interval'] ?? 60,
            'expiresAt' => $session['lastSeen'] + $grace,
        ];

        ApiHelper::respond($response, 201);
    }

    public function heartbeat()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $sessionId = $payload['sessionId'] ?? null;

        if (empty($sessionId)) {
            ApiHelper::respond([
                'error' => 'El campo sessionId es obligatorio'
            ], 422);
        }

        ApiHelper::validateMetadata($payload['metadata'] ?? null);

        $session = $this->sessionModel->heartbeat($sessionId, [
            'metadata' => $payload['metadata'] ?? null,
            'appVersion' => $payload['appVersion'] ?? null,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        if (!$session) {
            ApiHelper::respond([
                'error' => 'Sesión no encontrada o expirada'
            ], 404);
        }

        $grace = $this->sessionConfig['grace_period'] ?? 180;
        $response = [
            'sessionId' => $session['sessionId'],
            'status' => $session['status'],
            'lastSeen' => $session['lastSeen'],
            'expiresAt' => $session['lastSeen'] + $grace,
        ];

        ApiHelper::respond($response);
    }

    public function endSession()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $sessionId = $payload['sessionId'] ?? null;

        if (empty($sessionId)) {
            ApiHelper::respond([
                'error' => 'El campo sessionId es obligatorio'
            ], 422);
        }

        $ended = $this->sessionModel->endSession($sessionId, $payload['reason'] ?? 'app_disconnect');

        if (!$ended) {
            ApiHelper::respond([
                'error' => 'Sesión no encontrada'
            ], 404);
        }

        ApiHelper::respond([
            'sessionId' => $sessionId,
            'status' => 'inactive'
        ]);
    }

    public function activeSessions()
    {
        ApiHelper::respondIfOptions();

       ApiHelper::allowedMethodsGet();

        $this->sessionModel->purgeExpired($this->sessionConfig['grace_period'] ?? 180);

        $filters = [];
        if (!empty($_GET['customerId'])) {
            $filters['customerId'] = $_GET['customerId'];
        }

        $sessions = $this->sessionModel->getSessions($filters, $this->sessionConfig['grace_period'] ?? 180);

        ApiHelper::respond([
            'count' => count($sessions),
            'sessions' => $sessions,
            'heartbeatInterval' => $this->sessionConfig['heartbeat_interval'] ?? 60,
            'gracePeriod' => $this->sessionConfig['grace_period'] ?? 180,
        ]);
    }

    public function getCustomer($customerId = null)
    {
        ApiHelper::respondIfOptions();

       ApiHelper::allowedMethodsGet();

        if ($customerId === null) {
            $customerId = isset($_GET['customerId']) ? (string) $_GET['customerId'] : '';
        }

        $customerId = trim(rawurldecode((string) $customerId));

        if ($customerId === '') {
            ApiHelper::respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        $customer = $this->customerRegistry->getCustomer($customerId);

        if (!$customer) {
            ApiHelper::respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        ApiHelper::respond([
            'customer' => $customer,
        ]);
    }

    public function pullDesktop()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $customerApiId = isset($payload['customerApiId']) ? trim((string) $payload['customerApiId']) : '';

        if ($customerApiId === '' && isset($payload['customerIdApi'])) {
            $customerApiId = trim((string) $payload['customerIdApi']);
        }

        if ($customerApiId === '') {
            ApiHelper::respond([
                'error' => 'El campo customerApiId es obligatorio'
            ], 422);
        }

        $includeRemoved = false;
        if (array_key_exists('includeRemoved', $payload)) {
            $includeRemoved = $this->normalizeSyncFlag($payload['includeRemoved'], 'includeRemoved');
        }

        $includeRemovedByBulk = [];
        if (array_key_exists('includeRemovedByBulk', $payload)) {
            $bulkFlags = $payload['includeRemovedByBulk'];
            if (!is_array($bulkFlags)) {
                ApiHelper::respond([
                    'error' => 'El campo includeRemovedByBulk debe ser un objeto con banderas por bulk'
                ], 422);
            }

            foreach ($bulkFlags as $bulkName => $flagValue) {
                $includeRemovedByBulk[$bulkName] = $this->normalizeSyncFlag(
                    $flagValue,
                    sprintf('includeRemovedByBulk.%s', $bulkName)
                );
            }
        }

        $bulks = [];

        foreach ($this->desktopModels as $bulk => $model) {
            $includeFlag = $includeRemovedByBulk[$bulk] ?? $includeRemoved;
            $bulks[$bulk] = $model->pull($customerApiId, $includeFlag);
        }

        ApiHelper::respond([
            'customerApiId' => $customerApiId,
            'bulks' => $bulks,
        ]);
    }

    public function pushDesktop()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $customerApiId = isset($payload['customerApiId']) ? trim((string) $payload['customerApiId']) : '';

        if ($customerApiId === '' && isset($payload['customerIdApi'])) {
            $customerApiId = trim((string) $payload['customerIdApi']);
        }

        if ($customerApiId === '') {
            ApiHelper::respond([
                'error' => 'El campo customerApiId es obligatorio'
            ], 422);
        }

        $bulksPayload = $payload['bulks'] ?? $payload['data'] ?? null;

        if ($bulksPayload === null || !is_array($bulksPayload)) {
            ApiHelper::respond([
                'error' => 'Debes enviar el objeto bulks con la información a sincronizar'
            ], 422);
        }

        $results = [];

        foreach ($this->desktopModels as $bulk => $model) {
            $records = $bulksPayload[$bulk] ?? [];

            if (!is_array($records)) {
                ApiHelper::respond([
                    'error' => sprintf('El bulk %s debe ser un arreglo de registros', $bulk)
                ], 422);
            }

            $results[$bulk] = $model->push($customerApiId, $records);
        }

        ApiHelper::respond([
            'customerApiId' => $customerApiId,
            'bulks' => $results,
        ]);
    }

    public function saveCustomer()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';

        $existing = $customerId !== '' ? $this->customerRegistry->getCustomer($customerId) : null;

        $attributes = [];
        $privacyAcceptanceInput = array_key_exists('privacyAcceptance', $payload) ? $payload['privacyAcceptance'] : null;

        if (array_key_exists('name', $payload)) {
            $attributes['name'] = $payload['name'] !== null ? trim((string) $payload['name']) : null;
        }

        if (array_key_exists('email', $payload)) {
            $email = $payload['email'];
            if ($email !== null && $email !== '') {
                $email = trim((string) $email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    ApiHelper::respond([
                        'error' => 'Formato de correo inválido'
                    ], 422);
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
                    ApiHelper::respond([
                        'error' => 'El PlanCode debe tener máximo 50 caracteres'
                    ], 422);
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

        if (empty($attributes)) {
            ApiHelper::respond([
                'error' => 'No se enviaron atributos'
            ], 422);
        }

        if ($existing === null) {
            if (!array_key_exists('name', $attributes) || $attributes['name'] === null || $attributes['name'] === '') {
                ApiHelper::respond([
                    'error' => 'El nombre es obligatorio al crear un cliente'
                ], 422);
            }

            $privacyAcceptance = $this->normalizePrivacyAcceptancePayload($privacyAcceptanceInput, true);

            // Si no se proporciona billingId, crear cliente en Stripe
            if (!array_key_exists('billingId', $attributes) || $attributes['billingId'] === null) {
                // Validar que tengamos email para crear en Stripe
                if (!array_key_exists('email', $attributes) || $attributes['email'] === null || $attributes['email'] === '') {
                    ApiHelper::respond([
                        'error' => 'El email es obligatorio'
                    ], 422);
                }

                // Crear cliente en Stripe
                $stripeResult = $this->stripeService->createCustomer(
                    $attributes['name'],
                    $attributes['email'],
                    $attributes['phone'] ?? null
                );

                if (!$stripeResult['success']) {
                    ApiHelper::respond([
                        'error' => 'No se pudo crear el cliente en Stripe: ' . ($stripeResult['error'] ?? 'Error desconocido'),
                        'code' => 'stripe_customer_creation_failed'
                    ], 500);
                }

                // Usar el ID de Stripe como billingId
                $attributes['billingId'] = $stripeResult['customer']->id;
            }

            try {
                $result = $this->customerRegistry->registerCustomerIfAbsent([
                    'customerId' => $customerId !== '' ? $customerId : null,
                    'name' => $attributes['name'],
                    'billingId' => $attributes['billingId'] ?? null,
                    'planCode' => $attributes['planCode'] ?? null,
                    'email' => $attributes['email'] ?? null,
                    'phone' => $attributes['phone'] ?? null,
                    'deviceName' => $attributes['deviceName'] ?? null,
                    'token' => $attributes['token'] ?? null,
                    'isActive' => $attributes['isActive'] ?? true,
                    'privacyAcceptance' => $privacyAcceptance,
                ]);
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'email_already_registered') {
                    ApiHelper::respond([
                        'error' => 'El correo ya está registrado para otro cliente',
                        'code' => 'email_conflict',
                    ], 409);
                }

                if ($e->getMessage() === 'access_key_secret_missing') {
                    ApiHelper::respond([
                        'error' => 'No se pudo generar la AccessKey. Falta configurar ACCESS_KEY_SECRET.',
                        'code' => 'access_key_generation_failed',
                    ], 500);
                }

                throw $e;
            }

            $response = [
                'status' => $result['found'] ? 'updated' : 'created',
                'customer' => $result['customer'],
            ];

            if (isset($result['accessKey'])) {
                $response['accessKey'] = $result['accessKey'];
            }

            ApiHelper::respond($response, $result['found'] ? 200 : 201);
        }

        try {
            $customer = $this->customerRegistry->upsertCustomer($customerId, $attributes);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'email_already_registered') {
                ApiHelper::respond([
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ], 409);
            }

            throw $e;
        }
        ApiHelper::respond([
            'status' => 'updated',
            'customer' => $customer,
        ], 200);
    }

    public function registerCustomer()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();

        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';
        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $billingId = array_key_exists('billingId', $payload) ? trim((string) $payload['billingId']) : null;
        $planCode = array_key_exists('planCode', $payload) ? trim((string) $payload['planCode']) : null;
        $phone = array_key_exists('phone', $payload) ? trim((string) $payload['phone']) : null;
        $deviceName = array_key_exists('deviceName', $payload) ? trim((string) $payload['deviceName']) : null;
        $token = isset($payload['token']) ? trim((string) $payload['token']) : '';
        $privacyAcceptanceInput = array_key_exists('privacyAcceptance', $payload) ? $payload['privacyAcceptance'] : null;

        if ($name === '' || $token === '') {
            ApiHelper::respond([
                'error' => 'El nombre y el token son obligatorios'
            ], 422);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond([
                'error' => 'Formato de correo inválido'
            ], 422);
        }

        if ($planCode !== null && $planCode !== '') {
            if (mb_strlen($planCode) > 50) {
                ApiHelper::respond([
                    'error' => 'El PlanCode debe tener máximo 50 caracteres'
                ], 422);
            }
        }

        $planCode = ($planCode === '') ? null : $planCode;

        $phone = $phone !== null && $phone !== '' ? $phone : null;
        $deviceName = $deviceName !== null && $deviceName !== '' ? $deviceName : null;
        $privacyAcceptance = $this->normalizePrivacyAcceptancePayload($privacyAcceptanceInput, true);

        // Verificar si el cliente ya existe localmente
        $existingCustomer = $customerId !== '' ? $this->customerRegistry->getCustomer($customerId) : null;
        
        // Si el cliente no existe, crear en Stripe primero
        if ($existingCustomer === null) {
            // Si no se proporcionó billingId, crear cliente en Stripe
            if ($billingId === null || $billingId === '') {
                // Validar que tengamos email para crear en Stripe
                if ($email === '') {
                    ApiHelper::respond([
                        'error' => 'El email es obligatorio para crear un cliente en Stripe'
                    ], 422);
                }

                // Crear cliente en Stripe
                $stripeResult = $this->stripeService->createCustomer(
                    $name,
                    $email,
                    $phone
                );

                if (!$stripeResult['success']) {
                    ApiHelper::respond([
                        'error' => 'No se pudo crear el cliente en Stripe: ' . ($stripeResult['error'] ?? 'Error desconocido'),
                        'code' => 'stripe_customer_creation_failed'
                    ], 500);
                }

                // Usar el ID de Stripe como billingId
                $billingId = $stripeResult['customer']->id;
            }
        } else {
            // Cliente ya existe, usar su billingId actual si no se proporciona uno nuevo
            if ($billingId === null || $billingId === '') {
                $billingId = $existingCustomer['billingId'] ?? null;
            }
        }

        try {
            $result = $this->customerRegistry->registerCustomerIfAbsent([
                'customerId' => $customerId,
                'name' => $name,
                'billingId' => $billingId,
                'planCode' => $planCode,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone,
                'deviceName' => $deviceName,
                'token' => $token,
                'privacyAcceptance' => $privacyAcceptance,
            ]);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'email_already_registered') {
                ApiHelper::respond([
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ], 409);
            }

            if ($e->getMessage() === 'access_key_secret_missing') {
                ApiHelper::respond([
                    'error' => 'No se pudo generar la AccessKey. Falta configurar ACCESS_KEY_SECRET.',
                    'code' => 'access_key_generation_failed',
                ], 500);
            }

            throw $e;
        }

        if ($result['found'] === true) {
            ApiHelper::respond([
                'found' => true,
                'registered' => false,
                'customer' => $result['customer'],
            ], 200);
        }

        ApiHelper::respond([
            'found' => false,
            'registered' => true,
            'customer' => $result['customer'],
            'accessKey' => $result['accessKey'] ?? null,
        ], 201);
    }

    public function loginCustomer()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $accessKey = isset($payload['accessKey']) ? trim((string) $payload['accessKey']) : '';
        $deviceName = array_key_exists('deviceName', $payload) ? trim((string) $payload['deviceName']) : '';
        $token = isset($payload['token']) ? trim((string) $payload['token']) : '';

        if ($email === '' || $accessKey === '' || $deviceName === '' || $token === '') {
            ApiHelper::respond([
                'error' => 'Debes proporcionar email, accessKey, deviceName y token'
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond([
                'error' => 'Formato de correo inválido'
            ], 422);
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $customer = $this->customerRegistry->loginWithAccessKey($email, $accessKey, $deviceName, $ipAddress, $token);
        } catch (\InvalidArgumentException $e) {
            ApiHelper::respond([
                'error' => 'Debes proporcionar email y accessKey válidos'
            ], 422);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if ($message === 'too_many_attempts') {
                ApiHelper::respond([
                    'error' => 'Demasiados intentos fallidos. Intenta nuevamente en una hora.'
                ], 429);
            }

            if ($message === 'invalid_credentials') {
                ApiHelper::respond([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            if ($message === 'customer_not_waiting') {
                ApiHelper::respond([
                    'error' => 'El cliente no está esperando un nuevo token'
                ], 409);
            }

            if ($message === 'access_key_secret_missing') {
                ApiHelper::respond([
                    'error' => 'No se puede validar la AccessKey. Falta configurar ACCESS_KEY_SECRET.'
                ], 500);
            }

            throw $e;
        }

        ApiHelper::respond([
            'status' => 'success',
            'customer' => [
                'customerId' => $customer['customerId'],
                'name' => $customer['name'],
                'phone' => $customer['phone'],
                'email' => $customer['email'],
                'billingId' => $customer['billingId'],
                'token' => $customer['token'],
            ],
        ]);
    }

    public function patchCustomer()
    {
        ApiHelper::respondIfOptions();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PATCH'], true)) {
            ApiHelper::respond(['error' => 'Método no permitido'], 405);
        }

        $payload = ApiHelper::getJsonBody();

        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';
        if ($customerId === '') {
            ApiHelper::respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        $attributes = [];

        if (array_key_exists('planCode', $payload)) {
            $planCode = $payload['planCode'];
            if ($planCode !== null && $planCode !== '') {
                $planCode = trim((string) $planCode);
                if (mb_strlen($planCode) > 50) {
                    ApiHelper::respond([
                        'error' => 'El PlanCode debe tener máximo 50 caracteres'
                    ], 422);
                }
            } else {
                $planCode = null;
            }
            $attributes['planCode'] = $planCode;
        }

        if (array_key_exists('name', $payload)) {
            $name = $payload['name'];
            if ($name !== null) {
                $name = trim((string) $name);
            }

            if ($name === null || $name === '') {
                ApiHelper::respond([
                    'error' => 'El nombre no puede estar vacío'
                ], 422);
            }

            $attributes['name'] = $name;
        }

        if (array_key_exists('email', $payload)) {
            $email = $payload['email'];
            if ($email !== null && $email !== '') {
                $email = trim((string) $email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    ApiHelper::respond([
                        'error' => 'Formato de correo inválido'
                    ], 422);
                }
            } else {
                $email = null;
            }

            $attributes['email'] = $email;
        }

        if (array_key_exists('phone', $payload)) {
            $phone = $payload['phone'];
            if ($phone !== null) {
                $phone = trim((string) $phone);
            }
            $attributes['phone'] = ($phone === null || $phone === '') ? null : $phone;
        }

        if (empty($attributes)) {
            ApiHelper::respond([
                'error' => 'Debes proporcionar al menos un atributo para actualizar'
            ], 422);
        }

        $customer = $this->customerRegistry->patchCustomerAttributes($customerId, $attributes);

        if ($customer === null) {
            ApiHelper::respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        ApiHelper::respond([
            'status' => 'updated',
            'customer' => $customer,
        ]);
    }

    public function validateCustomer()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();

        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';
        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $billingId = array_key_exists('billingId', $payload) ? trim((string) $payload['billingId']) : null;
        $planCode = array_key_exists('planCode', $payload) ? trim((string) $payload['planCode']) : null;
        $phone = array_key_exists('phone', $payload) ? trim((string) $payload['phone']) : null;
        $deviceName = array_key_exists('deviceName', $payload) ? trim((string) $payload['deviceName']) : null;
        $token = isset($payload['token']) ? trim((string) $payload['token']) : '';
        $privacyAcceptanceInput = array_key_exists('privacyAcceptance', $payload) ? $payload['privacyAcceptance'] : null;

        if ($name === '' || $token === '') {
            ApiHelper::respond([
                'error' => 'El nombre y el token son obligatorios'
            ], 422);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond([
                'error' => 'Formato de correo inválido'
            ], 422);
        }

        if ($planCode !== null && $planCode !== '') {
            if (mb_strlen($planCode) > 50) {
                ApiHelper::respond([
                    'error' => 'El PlanCode debe tener máximo 50 caracteres'
                ], 422);
            }
        }

        $phone = $phone !== null && $phone !== '' ? $phone : null;
        $deviceName = $deviceName !== null && $deviceName !== '' ? $deviceName : null;
        $normalizedBillingId = $billingId !== null && $billingId !== '' ? $billingId : null;
        $normalizedEmail = $email !== '' ? $email : null;
        $normalizedPlanCode = ($planCode !== null && $planCode !== '') ? $planCode : null;

        $existing = null;
        if ($customerId !== '') {
            $existing = $this->customerRegistry->getCustomer($customerId);
        }

        if ($existing === null) {
            $emailAvailable = $this->customerRegistry->isEmailAvailable($normalizedEmail);
            if (!$emailAvailable) {
                ApiHelper::respond([
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ], 409);
            }
        }

        $privacyAcceptance = $this->normalizePrivacyAcceptancePayload($privacyAcceptanceInput, $existing === null);

        ApiHelper::respond([
            'valid' => true,
            'found' => $existing !== null,
            'customerId' => $existing['customerId'] ?? ($customerId !== '' ? $customerId : null),
            'normalized' => [
                'customerId' => $existing['customerId'] ?? ($customerId !== '' ? $customerId : null),
                'name' => $name,
                'billingId' => $existing['billingId'] ?? $normalizedBillingId,
                'planCode' => $existing['planCode'] ?? $normalizedPlanCode,
                'email' => $normalizedEmail,
                'phone' => $phone,
                'deviceName' => $deviceName,
                'token' => $token,
                'privacyAcceptance' => $privacyAcceptance,
            ],
            'customer' => $existing,
        ]);
    }

    public function awaitToken()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';

        if ($customerId === '') {
            ApiHelper::respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        $waiting = array_key_exists('waiting', $payload) ? (bool) $payload['waiting'] : true;

        $customer = $this->customerRegistry->setWaitingForToken($customerId, $waiting);

        if (!$customer) {
            ApiHelper::respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        ApiHelper::respond([
            'customerId' => $customer['customerId'],
            'billingId' => $customer['billingId'] ?? null,
            'planCode' => $customer['planCode'] ?? null,
            'waitingForToken' => $customer['waitingForToken'],
            'waitingSince' => $customer['waitingSince'],
        ]);
    }

    public function customerToken()
    {
        ApiHelper::respondIfOptions();

       ApiHelper::allowedMethodsGet();

        $customerId = isset($_GET['customerId']) ? trim((string) $_GET['customerId']) : '';

        if ($customerId === '') {
            ApiHelper::respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        $customer = $this->customerRegistry->getCustomer($customerId);

        if (!$customer) {
            ApiHelper::respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        ApiHelper::respond([
            'customerId' => $customer['customerId'],
            'name' => $customer['name'],
            'billingId' => $customer['billingId'] ?? null,
            'planCode' => $customer['planCode'] ?? null,
            'token' => $customer['token'] ?? null,
            'waitingForToken' => $customer['waitingForToken'] ?? false,
            'isActive' => $customer['isActive'] ?? true,
            'tokenUpdatedAt' => $customer['tokenUpdatedAt'] ?? null,
            'waitingSince' => $customer['waitingSince'] ?? null,
        ]);
    }

    public function registerToken()
    {
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';
        $token = isset($payload['token']) ? trim((string) $payload['token']) : '';
        $deviceName = array_key_exists('deviceName', $payload) ? trim((string) $payload['deviceName']) : null;

        if ($customerId === '' || $token === '') {
            ApiHelper::respond([
                'error' => 'Los campos customerId y token son obligatorios'
            ], 422);
        }

        if ($deviceName !== null && $deviceName === '') {
            $deviceName = null;
        }

        $result = $this->customerRegistry->registerToken($customerId, $token, $deviceName);

        if ($result['status'] === 'not_found') {
            ApiHelper::respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        if ($result['status'] === 'not_waiting') {
            ApiHelper::respond([
                'error' => 'El cliente no está esperando un nuevo token',
                'waitingForToken' => $result['customer']['waitingForToken'] ?? false,
            ], 409);
        }

        $customer = $result['customer'];

        ApiHelper::respond([
            'status' => 'updated',
            'customer' => $customer,
        ]);
    }

    public function getMessagesSendsAtMonth($customerId = null){
        ApiHelper::respondIfOptions();

        ApiHelper::allowedMethodsGet();

        $customerId = $customerId ?? (isset($_GET['customerId']) ? trim((string) $_GET['customerId']) : '');

        if ($customerId === '') {
            ApiHelper::respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        // Obtener mes y año actual
        $now = new \DateTime('now', new \DateTimeZone('America/Mexico_City'));
        $month = (int) $now->format('m');
        $year = (int) $now->format('Y');

        // Consultar mensajes exitosos del mes usando MessageSentModel
        $sendsAtMonth = $this->messageSentModel->countSuccessfulByMonth($customerId, $month, $year);

        ApiHelper::respond([
            'customerId' => $customerId,
            'total' => $sendsAtMonth,
            'month' => $now->format('Y-m'),
        ]);
    }

    /**
     * POST /api/customers/jwt/validate
     * Valida un token JWT de cliente y retorna información sobre su validez
     * 
     * Request body:
     *   - token (string, opcional si viene en Authorization header): Token JWT a validar
     * 
     * Headers:
     *   - Authorization: Bearer <token> (opcional si viene en body)
     * 
     * Response:
     *   - valid (bool): Si el token es válido
     *   - customerId (string|null): ID del cliente si el token es válido
     *   - error (string|null): Mensaje de error si el token no es válido
     *   - errorCode (string|null): Código de error
     *   - customer (object|null): Información del cliente si el token es válido
     */
    public function validateJwtToken()
    {
        ApiHelper::respondIfOptions();

        // Permitir GET y POST
        $method = $_SERVER['REQUEST_METHOD'];
        if (!in_array($method, ['GET', 'POST'])) {
            ApiHelper::respond(['error' => 'Method not allowed'], 405);
        }

        // Extraer token del Authorization header o del body
        $token = null;
        
        // Intentar obtener del header Authorization
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        // Si no hay token en header, intentar del body
        if (!$token) {
            $payload = ApiHelper::getJsonBody();
            $token = isset($payload['token']) ? trim((string) $payload['token']) : '';
        }
        
        // También permitir token como query parameter (para GET requests)
        if (!$token && isset($_GET['token'])) {
            $token = trim((string) $_GET['token']);
        }

        if (!$token) {
            ApiHelper::respond([
                'valid' => false,
                'error' => 'Token no proporcionado',
                'errorCode' => 'TOKEN_MISSING',
                'customerId' => null,
                'customer' => null,
            ], 400);
        }

        // Validar el token usando CustomerJwtService
        require_once __DIR__ . '/../Services/CustomerJwtService.php';
        $customerJwtService = new \App\Services\CustomerJwtService();
        
        // Obtener IP y user agent para logging
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $result = $customerJwtService->validateCustomerToken(
            $token,
            $ipAddress,
            null, // deviceName
            $userAgent
        );

        if ($result['valid']) {
            // Token válido, obtener información del cliente
            $customerId = $result['customerId'];
            $customer = $this->customerRegistry->getCustomer($customerId);
            
            ApiHelper::respond([
                'valid' => true,
                'customerId' => $customerId,
                'customer' => $customer ? [
                    'id' => $customer['customerId'],
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'deviceName' => $customer['deviceName'],
                    'isActive' => (bool) $customer['isActive'],
                ] : null,
                'error' => null,
                'errorCode' => null,
            ]);
        } else {
            // Token inválido
            $statusCode = 401; // Unauthorized
            
            // Ajustar código de estado según el tipo de error
            if (in_array($result['errorCode'], ['CUSTOMER_NOT_FOUND', 'CUSTOMER_INACTIVE'])) {
                $statusCode = 403; // Forbidden
            }
            
            ApiHelper::respond([
                'valid' => false,
                'customerId' => $result['customerId'],
                'error' => $this->mapperErrorJwt($result['errorCode']),
                'errorCode' => $result['errorCode'],
                'customer' => null,
            ], $statusCode);
        }
    }

    private function mapperErrorJwt($errorCode)
    {
        $mapping = [
            'TOKEN_EXPIRED' => 'El token ha expirado',
            'TOKEN_INVALID' => 'El token es inválido',
            'CUSTOMER_NOT_FOUND' => 'Cliente no encontrado',
            'CUSTOMER_INACTIVE' => 'Cliente inactivo',
            'TOKEN_MISSING' => 'Token no proporcionado',
        ];

        return $mapping[$errorCode] ?? 'Error desconocido';
    }
}
