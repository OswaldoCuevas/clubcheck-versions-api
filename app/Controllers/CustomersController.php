<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/CustomerSessionModel.php';
require_once __DIR__ . '/../Models/CustomerRegistryModel.php';

use Core\Controller;
use Models\CustomerRegistryModel;
use Models\CustomerSessionModel;

class CustomersController extends Controller
{
    private CustomerSessionModel $sessionModel;
    private CustomerRegistryModel $customerRegistry;
    private array $sessionConfig;

    public function __construct()
    {
        parent::__construct();

        $this->sessionModel = new CustomerSessionModel();
        $this->customerRegistry = new CustomerRegistryModel();
        $appConfig = require __DIR__ . '/../../config/app.php';
        $this->sessionConfig = $appConfig['customerSessions'] ?? [
            'heartbeat_interval' => 60,
            'grace_period' => 180,
            'max_metadata_size' => 2048,
        ];
    }

    private function respond(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');

        if (empty($raw)) {
            return [];
        }

        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->respond([
                'error' => 'Carga JSON inválida',
                'message' => json_last_error_msg()
            ], 400);
        }

        return $data ?? [];
    }

    private function validateMetadata($metadata): void
    {
        if ($metadata === null) {
            return;
        }

        if (!is_array($metadata)) {
            $this->respond([
                'error' => 'Formato de metadatos inválido',
                'message' => 'Metadata debe ser un objeto'
            ], 422);
        }

        $encoded = json_encode($metadata);
        $maxSize = $this->sessionConfig['max_metadata_size'] ?? 2048;

        if (strlen($encoded) > $maxSize) {
            $this->respond([
                'error' => 'Metadatos demasiado grandes',
                'message' => "El contenido de metadata excede {$maxSize} bytes"
            ], 413);
        }
    }

    private function normalizePrivacyAcceptancePayload($value, bool $required): ?array
    {
        if ($value === null) {
            if ($required) {
                $this->respond([
                    'error' => 'Debes enviar el objeto privacyAcceptance con la aceptación de privacidad'
                ], 422);
            }

            return null;
        }

        if (!is_array($value)) {
            $this->respond([
                'error' => 'El campo privacyAcceptance debe ser un objeto con los datos de aceptación'
            ], 422);
        }

        $documentVersion = isset($value['documentVersion']) ? trim((string) $value['documentVersion']) : '';
        if ($documentVersion === '') {
            $this->respond([
                'error' => 'Debes indicar la versión del documento de privacidad (documentVersion)'
            ], 422);
        }
        if (mb_strlen($documentVersion) > 50) {
            $this->respond([
                'error' => 'documentVersion no puede superar los 50 caracteres'
            ], 422);
        }

        $documentUrl = isset($value['documentUrl']) ? trim((string) $value['documentUrl']) : '';
        if ($documentUrl === '') {
            $this->respond([
                'error' => 'Debes indicar la URL del documento aceptado (documentUrl)'
            ], 422);
        }
        if (mb_strlen($documentUrl) > 255) {
            $this->respond([
                'error' => 'documentUrl no puede superar los 255 caracteres'
            ], 422);
        }

        $ipAddress = isset($value['ipAddress']) ? trim((string) $value['ipAddress']) : '';
        if ($ipAddress === '') {
            $this->respond([
                'error' => 'Debes indicar la dirección IP del aceptante (ipAddress)'
            ], 422);
        }
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            $this->respond([
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
                $this->respond([
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

    public function startSession()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $payload = $this->getJsonBody();
        $customerId = $payload['customerId'] ?? null;
        $deviceId = $payload['deviceId'] ?? null;
        $metadata = $payload['metadata'] ?? null;

        if (empty($customerId)) {
            $this->respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        $this->validateMetadata($metadata);

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

            $this->respond($response, 200);
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

        $this->respond($response, 201);
    }

    public function heartbeat()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $payload = $this->getJsonBody();
        $sessionId = $payload['sessionId'] ?? null;

        if (empty($sessionId)) {
            $this->respond([
                'error' => 'El campo sessionId es obligatorio'
            ], 422);
        }

        $this->validateMetadata($payload['metadata'] ?? null);

        $session = $this->sessionModel->heartbeat($sessionId, [
            'metadata' => $payload['metadata'] ?? null,
            'appVersion' => $payload['appVersion'] ?? null,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        if (!$session) {
            $this->respond([
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

        $this->respond($response);
    }

    public function endSession()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $payload = $this->getJsonBody();
        $sessionId = $payload['sessionId'] ?? null;

        if (empty($sessionId)) {
            $this->respond([
                'error' => 'El campo sessionId es obligatorio'
            ], 422);
        }

        $ended = $this->sessionModel->endSession($sessionId, $payload['reason'] ?? 'app_disconnect');

        if (!$ended) {
            $this->respond([
                'error' => 'Sesión no encontrada'
            ], 404);
        }

        $this->respond([
            'sessionId' => $sessionId,
            'status' => 'inactive'
        ]);
    }

    public function activeSessions()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $this->sessionModel->purgeExpired($this->sessionConfig['grace_period'] ?? 180);

        $filters = [];
        if (!empty($_GET['customerId'])) {
            $filters['customerId'] = $_GET['customerId'];
        }

        $sessions = $this->sessionModel->getSessions($filters, $this->sessionConfig['grace_period'] ?? 180);

        $this->respond([
            'count' => count($sessions),
            'sessions' => $sessions,
            'heartbeatInterval' => $this->sessionConfig['heartbeat_interval'] ?? 60,
            'gracePeriod' => $this->sessionConfig['grace_period'] ?? 180,
        ]);
    }

    public function getCustomer($customerId = null)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        if ($customerId === null) {
            $customerId = isset($_GET['customerId']) ? (string) $_GET['customerId'] : '';
        }

        $customerId = trim(rawurldecode((string) $customerId));

        if ($customerId === '') {
            $this->respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        $customer = $this->customerRegistry->getCustomer($customerId);

        if (!$customer) {
            $this->respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        $this->respond([
            'customer' => $customer,
        ]);
    }

    public function saveCustomer()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

    $payload = $this->getJsonBody();
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
                    $this->respond([
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
                    $this->respond([
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
            $this->respond([
                'error' => 'No se enviaron atributos'
            ], 422);
        }

        if ($existing === null) {
            if (!array_key_exists('name', $attributes) || $attributes['name'] === null || $attributes['name'] === '') {
                $this->respond([
                    'error' => 'El nombre es obligatorio al crear un cliente'
                ], 422);
            }

            $privacyAcceptance = $this->normalizePrivacyAcceptancePayload($privacyAcceptanceInput, true);

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
                    $this->respond([
                        'error' => 'El correo ya está registrado para otro cliente',
                        'code' => 'email_conflict',
                    ], 409);
                }

                if ($e->getMessage() === 'access_key_secret_missing') {
                    $this->respond([
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

            $this->respond($response, $result['found'] ? 200 : 201);
        }

        try {
            $customer = $this->customerRegistry->upsertCustomer($customerId, $attributes);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'email_already_registered') {
                $this->respond([
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ], 409);
            }

            throw $e;
        }
        $this->respond([
            'status' => 'updated',
            'customer' => $customer,
        ], 200);
    }

    public function registerCustomer()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $payload = $this->getJsonBody();

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
            $this->respond([
                'error' => 'El nombre y el token son obligatorios'
            ], 422);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond([
                'error' => 'Formato de correo inválido'
            ], 422);
        }

        if ($planCode !== null && $planCode !== '') {
            if (mb_strlen($planCode) > 50) {
                $this->respond([
                    'error' => 'El PlanCode debe tener máximo 50 caracteres'
                ], 422);
            }
        }

        $planCode = ($planCode === '') ? null : $planCode;

        $phone = $phone !== null && $phone !== '' ? $phone : null;
        $deviceName = $deviceName !== null && $deviceName !== '' ? $deviceName : null;
        $privacyAcceptance = $this->normalizePrivacyAcceptancePayload($privacyAcceptanceInput, true);

        try {
            $result = $this->customerRegistry->registerCustomerIfAbsent([
                'customerId' => $customerId,
                'name' => $name,
                'billingId' => $billingId !== '' ? $billingId : null,
                'planCode' => $planCode,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone,
                'deviceName' => $deviceName,
                'token' => $token,
                'privacyAcceptance' => $privacyAcceptance,
            ]);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'email_already_registered') {
                $this->respond([
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ], 409);
            }

            if ($e->getMessage() === 'access_key_secret_missing') {
                $this->respond([
                    'error' => 'No se pudo generar la AccessKey. Falta configurar ACCESS_KEY_SECRET.',
                    'code' => 'access_key_generation_failed',
                ], 500);
            }

            throw $e;
        }

        if ($result['found'] === true) {
            $this->respond([
                'found' => true,
                'registered' => false,
                'customer' => $result['customer'],
            ], 200);
        }

        $this->respond([
            'found' => false,
            'registered' => true,
            'customer' => $result['customer'],
            'accessKey' => $result['accessKey'] ?? null,
        ], 201);
    }

    public function loginCustomer()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $payload = $this->getJsonBody();

        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $accessKey = isset($payload['accessKey']) ? trim((string) $payload['accessKey']) : '';
        $deviceName = array_key_exists('deviceName', $payload) ? trim((string) $payload['deviceName']) : '';
        $token = isset($payload['token']) ? trim((string) $payload['token']) : '';

        if ($email === '' || $accessKey === '' || $deviceName === '' || $token === '') {
            $this->respond([
                'error' => 'Debes proporcionar email, accessKey, deviceName y token'
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond([
                'error' => 'Formato de correo inválido'
            ], 422);
        }

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $customer = $this->customerRegistry->loginWithAccessKey($email, $accessKey, $deviceName, $ipAddress, $token);
        } catch (\InvalidArgumentException $e) {
            $this->respond([
                'error' => 'Debes proporcionar email y accessKey válidos'
            ], 422);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if ($message === 'too_many_attempts') {
                $this->respond([
                    'error' => 'Demasiados intentos fallidos. Intenta nuevamente en una hora.'
                ], 429);
            }

            if ($message === 'invalid_credentials') {
                $this->respond([
                    'error' => 'Credenciales inválidas'
                ], 401);
            }

            if ($message === 'customer_not_waiting') {
                $this->respond([
                    'error' => 'El cliente no está esperando un nuevo token'
                ], 409);
            }

            if ($message === 'access_key_secret_missing') {
                $this->respond([
                    'error' => 'No se puede validar la AccessKey. Falta configurar ACCESS_KEY_SECRET.'
                ], 500);
            }

            throw $e;
        }

        $this->respond([
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
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PATCH'], true)) {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $payload = $this->getJsonBody();

        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';
        if ($customerId === '') {
            $this->respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        $attributes = [];

        if (array_key_exists('planCode', $payload)) {
            $planCode = $payload['planCode'];
            if ($planCode !== null && $planCode !== '') {
                $planCode = trim((string) $planCode);
                if (mb_strlen($planCode) > 50) {
                    $this->respond([
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
                $this->respond([
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
                    $this->respond([
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
            $this->respond([
                'error' => 'Debes proporcionar al menos un atributo para actualizar'
            ], 422);
        }

        $customer = $this->customerRegistry->patchCustomerAttributes($customerId, $attributes);

        if ($customer === null) {
            $this->respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        $this->respond([
            'status' => 'updated',
            'customer' => $customer,
        ]);
    }

    public function validateCustomer()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $payload = $this->getJsonBody();

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
            $this->respond([
                'error' => 'El nombre y el token son obligatorios'
            ], 422);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond([
                'error' => 'Formato de correo inválido'
            ], 422);
        }

        if ($planCode !== null && $planCode !== '') {
            if (mb_strlen($planCode) > 50) {
                $this->respond([
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
                $this->respond([
                    'error' => 'El correo ya está registrado para otro cliente',
                    'code' => 'email_conflict',
                ], 409);
            }
        }

        $privacyAcceptance = $this->normalizePrivacyAcceptancePayload($privacyAcceptanceInput, $existing === null);

        $this->respond([
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
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $payload = $this->getJsonBody();
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';

        if ($customerId === '') {
            $this->respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        $waiting = array_key_exists('waiting', $payload) ? (bool) $payload['waiting'] : true;

        $customer = $this->customerRegistry->setWaitingForToken($customerId, $waiting);

        if (!$customer) {
            $this->respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        $this->respond([
            'customerId' => $customer['customerId'],
            'billingId' => $customer['billingId'] ?? null,
            'planCode' => $customer['planCode'] ?? null,
            'waitingForToken' => $customer['waitingForToken'],
            'waitingSince' => $customer['waitingSince'],
        ]);
    }

    public function customerToken()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $customerId = isset($_GET['customerId']) ? trim((string) $_GET['customerId']) : '';

        if ($customerId === '') {
            $this->respond([
                'error' => 'El campo customerId es obligatorio'
            ], 422);
        }

        $customer = $this->customerRegistry->getCustomer($customerId);

        if (!$customer) {
            $this->respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        $this->respond([
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
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Método no permitido'], 405);
        }

        $payload = $this->getJsonBody();
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';
        $token = isset($payload['token']) ? trim((string) $payload['token']) : '';
        $deviceName = array_key_exists('deviceName', $payload) ? trim((string) $payload['deviceName']) : null;

        if ($customerId === '' || $token === '') {
            $this->respond([
                'error' => 'Los campos customerId y token son obligatorios'
            ], 422);
        }

        if ($deviceName !== null && $deviceName === '') {
            $deviceName = null;
        }

        $result = $this->customerRegistry->registerToken($customerId, $token, $deviceName);

        if ($result['status'] === 'not_found') {
            $this->respond([
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        if ($result['status'] === 'not_waiting') {
            $this->respond([
                'error' => 'El cliente no está esperando un nuevo token',
                'waitingForToken' => $result['customer']['waitingForToken'] ?? false,
            ], 409);
        }

        $customer = $result['customer'];

        $this->respond([
            'status' => 'updated',
            'customer' => $customer,
        ]);
    }
}
