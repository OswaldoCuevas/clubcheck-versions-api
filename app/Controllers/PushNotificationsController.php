<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';
require_once __DIR__ . '/../Models/CustomerPushTokenModel.php';
require_once __DIR__ . '/../Models/CustomerRegistryModel.php';
require_once __DIR__ . '/../Services/FirebasePushService.php';

use ApiHelper;
use App\Services\FirebasePushService;
use Core\Controller;
use Models\CustomerPushTokenModel;
use Models\CustomerRegistryModel;

class PushNotificationsController extends Controller
{
    public function page(): void
    {
        $this->requirePermission('admin_access');
        $this->view('admin/push-notifications', [
            'title' => 'Notificaciones push - ClubCheck',
            'isAuthenticated' => true,
            'currentUser' => $this->userModel->getCurrentUser(),
            'customers' => (new CustomerPushTokenModel())->customersWithDeviceCounts(),
        ]);
    }

    public function register(): void
    {
        ApiHelper::allowedMethodsPost();
        $customerId = $GLOBALS['push_jwt_customer_id'] ?? null;
        if (!$customerId) {
            ApiHelper::respond(['error' => 'Cliente no autenticado'], 401);
        }

        $input = ApiHelper::getJsonBody();
        $token = trim((string) ($input['token'] ?? ''));
        $platform = strtolower(trim((string) ($input['platform'] ?? '')));
        if ($token === '' || strlen($token) > 4096 || preg_match('/\s/', $token)) {
            ApiHelper::respond(['error' => 'Token FCM inválido'], 422);
        }
        if ($platform !== '' && !in_array($platform, ['web', 'android', 'ios', 'desktop'], true)) {
            ApiHelper::respond(['error' => 'Plataforma inválida'], 422);
        }

        (new CustomerPushTokenModel())->register($customerId, $token, $platform ?: null);
        ApiHelper::respond(['success' => true]);
    }

    public function unregister(): void
    {
        ApiHelper::allowedMethodsPost();
        $customerId = $GLOBALS['push_jwt_customer_id'] ?? null;
        if (!$customerId) {
            ApiHelper::respond(['error' => 'Cliente no autenticado'], 401);
        }

        $token = trim((string) (ApiHelper::getJsonBody()['token'] ?? ''));
        if ($token === '' || strlen($token) > 4096) {
            ApiHelper::respond(['error' => 'Token FCM inválido'], 422);
        }

        $removed = (new CustomerPushTokenModel())->remove($customerId, $token);
        ApiHelper::respond(['success' => true, 'removed' => $removed]);
    }

    /** Envía a todos los dispositivos registrados de un cliente de ClubCheck. */
    public function send(): void
    {
        $this->requirePermission('admin_access');
        ApiHelper::allowedMethodsPost();
        $input = ApiHelper::getJsonBody();
        $customerId = trim((string) ($input['customerId'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        $data = $input['data'] ?? [];
        $webOptions = [];
        foreach (['iconUrl', 'imageUrl', 'link'] as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                if (!is_string($input[$field]) || strlen($input[$field]) > 2048
                    || !filter_var($input[$field], FILTER_VALIDATE_URL)
                    || strtolower((string) parse_url($input[$field], PHP_URL_SCHEME)) !== 'https') {
                    ApiHelper::respond(['error' => $field . ' debe ser una URL HTTPS válida'], 422);
                }
                $webOptions[$field] = $input[$field];
            }
        }

        if ($customerId === '' || strlen($customerId) > 64 || $title === '' || $body === ''
            || mb_strlen($title) > 150 || mb_strlen($body) > 1000 || !is_array($data)
            || (array_is_list($data) && $data !== [])) {
            ApiHelper::respond(['error' => 'customerId, title, body o data inválidos'], 422);
        }
        foreach ($data as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                ApiHelper::respond(['error' => 'data debe contener pares de texto'], 422);
            }
        }
        if (strlen(json_encode($data)) > 3500) {
            ApiHelper::respond(['error' => 'data excede el tamaño permitido'], 422);
        }

        $customer = (new CustomerRegistryModel())->getCustomer($customerId);
        if (!$customer) {
            ApiHelper::respond(['error' => 'Cliente no encontrado'], 404);
        }

        $tokens = new CustomerPushTokenModel();
        $devices = $tokens->forCustomer($customerId);
        if ($devices === []) {
            ApiHelper::respond(['success' => true, 'sent' => 0, 'failed' => 0, 'firebaseResponses' => [], 'message' => 'El cliente no tiene dispositivos registrados']);
        }

        $push = new FirebasePushService();
        $sent = 0;
        $failed = 0;
        $errors = [];
        $firebaseResponses = [];
        try {
            foreach ($devices as $device) {
                $result = $push->send($device['Token'], $title, $body, $data, $webOptions);
                $firebaseResponses[] = [
                    'deviceId' => $device['Id'],
                    'httpStatus' => $result['httpStatus'],
                    'response' => $result['firebaseResponse'],
                ];
                if ($result['success']) {
                    ++$sent;
                } else {
                    ++$failed;
                    $code = (string) ($result['errorCode'] ?? 'UNKNOWN');
                    $errors[$code] = ($errors[$code] ?? 0) + 1;
                    if ($code === 'UNREGISTERED') {
                        $tokens->removeInvalid($device['Id'], $device['Token']);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('Firebase push: ' . $e->getMessage());
            ApiHelper::respond(['error' => 'No se pudo completar el envío a Firebase', 'sent' => $sent, 'failed' => $failed, 'firebaseResponses' => $firebaseResponses], 502);
        }

        ApiHelper::respond(['success' => $failed === 0, 'sent' => $sent, 'failed' => $failed, 'errors' => $errors, 'firebaseResponses' => $firebaseResponses]);
    }
}
