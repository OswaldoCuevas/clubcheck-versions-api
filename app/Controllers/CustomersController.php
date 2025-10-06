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
                'error' => 'Invalid JSON payload',
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
                'error' => 'Invalid metadata format',
                'message' => 'Metadata must be an object'
            ], 422);
        }

        $encoded = json_encode($metadata);
        $maxSize = $this->sessionConfig['max_metadata_size'] ?? 2048;

        if (strlen($encoded) > $maxSize) {
            $this->respond([
                'error' => 'Metadata too large',
                'message' => "Metadata payload exceeds {$maxSize} bytes"
            ], 413);
        }
    }

    public function startSession()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Method not allowed'], 405);
        }

        $payload = $this->getJsonBody();
        $customerId = $payload['customerId'] ?? null;
        $deviceId = $payload['deviceId'] ?? null;
        $metadata = $payload['metadata'] ?? null;

        if (empty($customerId)) {
            $this->respond([
                'error' => 'customerId is required'
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
            $this->respond(['error' => 'Method not allowed'], 405);
        }

        $payload = $this->getJsonBody();
        $sessionId = $payload['sessionId'] ?? null;

        if (empty($sessionId)) {
            $this->respond([
                'error' => 'sessionId is required'
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
                'error' => 'Session not found or expired'
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
            $this->respond(['error' => 'Method not allowed'], 405);
        }

        $payload = $this->getJsonBody();
        $sessionId = $payload['sessionId'] ?? null;

        if (empty($sessionId)) {
            $this->respond([
                'error' => 'sessionId is required'
            ], 422);
        }

        $ended = $this->sessionModel->endSession($sessionId, $payload['reason'] ?? 'app_disconnect');

        if (!$ended) {
            $this->respond([
                'error' => 'Session not found'
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
            $this->respond(['error' => 'Method not allowed'], 405);
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
            $this->respond(['error' => 'Method not allowed'], 405);
        }

        if ($customerId === null) {
            $customerId = isset($_GET['customerId']) ? (string) $_GET['customerId'] : '';
        }

        $customerId = trim(rawurldecode((string) $customerId));

        if ($customerId === '') {
            $this->respond([
                'error' => 'customerId is required'
            ], 422);
        }

        $customer = $this->customerRegistry->getCustomer($customerId);

        if (!$customer) {
            $this->respond([
                'error' => 'Customer not found'
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
            $this->respond(['error' => 'Method not allowed'], 405);
        }

        $payload = $this->getJsonBody();
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';

        if ($customerId === '') {
            $this->respond([
                'error' => 'customerId is required'
            ], 422);
        }

        $existing = $this->customerRegistry->getCustomer($customerId);

        $attributes = [];

        if (array_key_exists('name', $payload)) {
            $attributes['name'] = $payload['name'] !== null ? trim((string) $payload['name']) : null;
        }

        if (array_key_exists('email', $payload)) {
            $email = $payload['email'];
            if ($email !== null && $email !== '') {
                $email = trim((string) $email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->respond([
                        'error' => 'Invalid email format'
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

        if (array_key_exists('token', $payload)) {
            $attributes['token'] = $payload['token'];
        }

        if (array_key_exists('isActive', $payload)) {
            $attributes['isActive'] = (bool) $payload['isActive'];
        }

        if (empty($attributes)) {
            $this->respond([
                'error' => 'No attributes provided'
            ], 422);
        }

        $customer = $this->customerRegistry->upsertCustomer($customerId, $attributes);
        $statusCode = $existing ? 200 : 201;

        $this->respond([
            'status' => $existing ? 'updated' : 'created',
            'customer' => $customer,
        ], $statusCode);
    }

    public function registerCustomer()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Method not allowed'], 405);
        }

        $payload = $this->getJsonBody();

        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';
        $name = isset($payload['name']) ? trim((string) $payload['name']) : '';
        $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
        $phone = array_key_exists('phone', $payload) ? trim((string) $payload['phone']) : null;
        $deviceName = array_key_exists('deviceName', $payload) ? trim((string) $payload['deviceName']) : null;
        $token = isset($payload['token']) ? trim((string) $payload['token']) : '';

        if ($customerId === '' || $name === '' || $token === '') {
            $this->respond([
                'error' => 'customerId, name and token are required'
            ], 422);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond([
                'error' => 'Invalid email format'
            ], 422);
        }

        $phone = $phone !== null && $phone !== '' ? $phone : null;
        $deviceName = $deviceName !== null && $deviceName !== '' ? $deviceName : null;

        $result = $this->customerRegistry->registerCustomerIfAbsent([
            'customerId' => $customerId,
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone,
            'deviceName' => $deviceName,
            'token' => $token,
        ]);

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
        ], 201);
    }

    public function awaitToken()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->respond(['status' => 'ok']);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->respond(['error' => 'Method not allowed'], 405);
        }

        $payload = $this->getJsonBody();
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';

        if ($customerId === '') {
            $this->respond([
                'error' => 'customerId is required'
            ], 422);
        }

        $waiting = array_key_exists('waiting', $payload) ? (bool) $payload['waiting'] : true;

        $customer = $this->customerRegistry->setWaitingForToken($customerId, $waiting);

        if (!$customer) {
            $this->respond([
                'error' => 'Customer not found'
            ], 404);
        }

        $this->respond([
            'customerId' => $customer['customerId'],
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
            $this->respond(['error' => 'Method not allowed'], 405);
        }

        $customerId = isset($_GET['customerId']) ? trim((string) $_GET['customerId']) : '';

        if ($customerId === '') {
            $this->respond([
                'error' => 'customerId is required'
            ], 422);
        }

        $customer = $this->customerRegistry->getCustomer($customerId);

        if (!$customer) {
            $this->respond([
                'error' => 'Customer not found'
            ], 404);
        }

        $this->respond([
            'customerId' => $customer['customerId'],
            'name' => $customer['name'],
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
            $this->respond(['error' => 'Method not allowed'], 405);
        }

        $payload = $this->getJsonBody();
        $customerId = isset($payload['customerId']) ? trim((string) $payload['customerId']) : '';
        $token = isset($payload['token']) ? trim((string) $payload['token']) : '';
        $deviceName = array_key_exists('deviceName', $payload) ? trim((string) $payload['deviceName']) : null;

        if ($customerId === '' || $token === '') {
            $this->respond([
                'error' => 'customerId and token are required'
            ], 422);
        }

        if ($deviceName !== null && $deviceName === '') {
            $deviceName = null;
        }

        $result = $this->customerRegistry->registerToken($customerId, $token, $deviceName);

        if ($result['status'] === 'not_found') {
            $this->respond([
                'error' => 'Customer not found'
            ], 404);
        }

        if ($result['status'] === 'not_waiting') {
            $this->respond([
                'error' => 'Customer is not waiting for a new token',
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
