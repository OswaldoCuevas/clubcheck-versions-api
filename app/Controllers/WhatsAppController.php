<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Services/WhatsAppService.php';
require_once __DIR__ . '/../Models/MessageSentModel.php';
require_once __DIR__ . '/../Models/WhatsAppConfigurationModel.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';

use Core\Controller;
use App\Services\WhatsAppService;
use Models\MessageSentModel;
use Models\WhatsAppConfigurationModel;
use ApiHelper;

/**
 * Controlador de WhatsApp
 * 
 * Maneja los endpoints de envío de mensajes de WhatsApp
 */
class WhatsAppController extends Controller
{
    private WhatsAppService $whatsAppService;
    private MessageSentModel $messageSentModel;
    private WhatsAppConfigurationModel $configModel;

    public function __construct()
    {
        parent::__construct();
        $this->whatsAppService = new WhatsAppService(); // Servicio default (config global)
        $this->messageSentModel = new MessageSentModel();
        $this->configModel = new WhatsAppConfigurationModel();
    }

    /**
     * Obtiene una instancia de WhatsAppService para un customer específico.
     * Si el customer tiene configuración propia, la usará; si no, usará la global.
     */
    private function getServiceForCustomer(string $customerId): WhatsAppService
    {
        return new WhatsAppService($customerId);
    }

    /**
     * GET /api/customers/whatsapp/status
     * 
     * Verifica el estado de configuración del servicio de WhatsApp
     */
    public function status(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        ApiHelper::respond([
            'configured' => $this->whatsAppService->isConfigured(),
            'timestamp' => time(),
        ]);
    }

    /**
     * GET /api/customers/whatsapp/monthly-count/:customerApiId
     * 
     * Obtiene el conteo de mensajes enviados exitosamente en el mes actual
     */
    public function monthlyCount(string $customerApiId): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        if (empty($customerApiId)) {
            ApiHelper::respond(['error' => 'customerApiId es requerido'], 422);
        }

        $service = $this->getServiceForCustomer($customerApiId);
        $count = $service->getMonthlyCount($customerApiId);

        ApiHelper::respond([
            'customerApiId' => $customerApiId,
            'count' => $count,
            'month' => date('Y-m'),
        ]);
    }

    /**
     * GET /api/customers/whatsapp/messages/:customerApiId
     * 
     * Lista los mensajes enviados con filtros avanzados
     * 
     * Query params:
     * - page: Página actual (default: 1)
     * - perPage: Registros por página (default: 50, max: 500)
     * - startDate: Fecha inicio YYYY-MM-DD (opcional)
     * - endDate: Fecha fin YYYY-MM-DD (opcional)
     * - status: success|failed (opcional)
     * - search: Buscar en teléfono, mensaje o error (opcional)
     * 
     * Ejemplo: /api/customers/whatsapp/messages/CLUB-001?page=1&perPage=20&status=failed&search=5512345678
     */
    public function listMessages(string $customerApiId): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        if (empty($customerApiId)) {
            ApiHelper::respond(['error' => 'customerApiId es requerido'], 422);
        }

        // Obtener parámetros de paginación
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(500, (int) ($_GET['perPage'] ?? 50)));

        // Construir filtros
        $filters = [];

        if (!empty($_GET['startDate'])) {
            $filters['startDate'] = trim($_GET['startDate']);
        }

        if (!empty($_GET['endDate'])) {
            $filters['endDate'] = trim($_GET['endDate']);
        }

        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $filters['status'] = trim($_GET['status']);
        }

        if (!empty($_GET['search'])) {
            $filters['search'] = trim($_GET['search']);
        }

        // Ejecutar búsqueda
        $result = $this->messageSentModel->searchMessages($customerApiId, $filters, $page, $perPage);

        ApiHelper::respond([
            'customerApiId' => $customerApiId,
            'messages' => $result['data'],
            'pagination' => $result['pagination'],
            'filters' => $filters,
        ]);
    }

    /**
     * POST /api/customers/whatsapp/send/subscription
     * 
     * Envía un mensaje de nueva suscripción (bienvenida)
     * 
     * Body:
     * {
     *   "customerApiId": "xxx",
     *   "subscriptionId": "xxx",
     *   "phone": "5512345678",
     *   "userId": "xxx" (opcional),
     *   "firstName": "Juan",
     *   "clubName": "Mi Club",
     *   "startDate": "10/03/2026",
     *   "endDate": "10/04/2026"
     * }
     */
    public function sendSubscription(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $this->validateRequired($payload, ['customerApiId', 'subscriptionId', 'phone']);

        $service = $this->getServiceForCustomer($payload['customerApiId']);
        $result = $service->sendSubscriptionTemplate(
            $payload['phone'],
            $payload['firstName'] ?? 'Cliente',
            $payload['clubName'] ?? 'tu club',
            $payload['startDate'] ?? '',
            $payload['endDate'] ?? '',
            $payload['customerApiId'],
            $payload['userId'] ?? null,
            $payload['subscriptionId'],
            $payload['username'] ?? null
        );

        $this->respondResult($result);
    }

    /**
     * POST /api/customers/whatsapp/send/warning
     * 
     * Envía un mensaje de advertencia de vencimiento próximo
     * 
     * Body:
     * {
     *   "customerApiId": "xxx",
     *   "subscriptionId": "xxx",
     *   "phone": "5512345678",
     *   "userId": "xxx" (opcional),
     *   "clubName": "Mi Club",
     *   "days": 3
     * }
     */
    public function sendWarning(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $this->validateRequired($payload, ['customerApiId', 'subscriptionId', 'phone']);

        $days = $payload['days'] ?? 3;
        $daysText = $days == 1 ? 'un día' : "{$days} días";

        $service = $this->getServiceForCustomer($payload['customerApiId']);
        $result = $service->sendWarningTemplate(
            $payload['phone'],
            $payload['clubName'] ?? 'tu club',
            $daysText,
            $payload['customerApiId'],
            $payload['userId'] ?? null,
            $payload['subscriptionId'],
            $payload['username'] ?? null
        );

        $this->respondResult($result);
    }

    /**
     * POST /api/customers/whatsapp/send/finalized
     * 
     * Envía un mensaje de membresía finalizada
     * 
     * Body:
     * {
     *   "customerApiId": "xxx",
     *   "subscriptionId": "xxx",
     *   "phone": "5512345678",
     *   "userId": "xxx" (opcional),
     *   "clubName": "Mi Club"
     * }
     */
    public function sendFinalized(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $this->validateRequired($payload, ['customerApiId', 'subscriptionId', 'phone']);

        $service = $this->getServiceForCustomer($payload['customerApiId']);
        $result = $service->sendFinalizedTemplate(
            $payload['phone'],
            $payload['clubName'] ?? 'tu club',
            $payload['customerApiId'],
            $payload['userId'] ?? null,
            $payload['subscriptionId'],
            $payload['username'] ?? null
        );

        $this->respondResult($result);
    }

    /**
     * POST /api/customers/whatsapp/send/last-day
     * 
     * Envía un mensaje de último día de membresía
     * 
     * Body:
     * {
     *   "customerApiId": "xxx",
     *   "subscriptionId": "xxx",
     *   "phone": "5512345678",
     *   "userId": "xxx" (opcional),
     *   "clubName": "Mi Club"
     * }
     */
    public function sendLastDay(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $this->validateRequired($payload, ['customerApiId', 'subscriptionId', 'phone']);

        $service = $this->getServiceForCustomer($payload['customerApiId']);
        $result = $service->sendLastDayTemplate(
            $payload['phone'],
            $payload['clubName'] ?? 'tu club',
            $payload['customerApiId'],
            $payload['userId'] ?? null,
            $payload['subscriptionId'],
            $payload['username'] ?? null
        );

        $this->respondResult($result);
    }

    /**
     * POST /api/customers/whatsapp/send/bulk
     * 
     * Envía mensajes en bulk y retorna los subscriptionId exitosos
     * 
     * Body:
     * {
     *   "customerApiId": "xxx",
     *   "clubName": "Mi Club",
     *   "items": [
     *     {
     *       "template": "subscription|warning|finalized|last_day",
     *       "subscriptionId": "xxx",
     *       "phone": "5512345678",
     *       "userId": "xxx" (opcional),
     *       "parameters": {
     *         "days": 3,
     *         "startDate": "10/03/2026",
     *         "endDate": "10/04/2026",
     *         "firstName": "Juan"
     *       }
     *     }
     *   ]
     * }
     * 
     * Response:
     * {
     *   "success": [{ "subscriptionId": "xxx", "messageId": "xxx" }],
     *   "failed": [{ "subscriptionId": "xxx", "error": "xxx" }],
     *   "total": 10,
     *   "successCount": 8,
     *   "failedCount": 2
     * }
     */
    public function sendBulk(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $this->validateRequired($payload, ['customerApiId', 'items']);

        $items = $payload['items'] ?? [];
        
        if (!is_array($items)) {
            ApiHelper::respond(['error' => 'items debe ser un array'], 422);
        }

        if (empty($items)) {
            ApiHelper::respond([
                'success' => [],
                'failed' => [],
                'total' => 0,
                'successCount' => 0,
                'failedCount' => 0,
            ]);
        }

        $clubName = $payload['clubName'] ?? 'tu club';

        // Agregar clubName a cada item si no lo tiene
        $items = array_map(function ($item) use ($clubName) {
            if (!isset($item['parameters'])) {
                $item['parameters'] = [];
            }
            if (!isset($item['parameters']['clubName'])) {
                $item['parameters']['clubName'] = $clubName;
            }
            return $item;
        }, $items);

        $service = $this->getServiceForCustomer($payload['customerApiId']);
        $result = $service->sendBulk($items, $payload['customerApiId']);

        ApiHelper::respond($result);
    }

    // ==================== PERFIL DEL NEGOCIO ====================

    /**
     * POST /api/customers/whatsapp/business-profile/:customerId
     * 
     * Crea o actualiza la configuración de WhatsApp del customer.
     * Cada customer solo puede tener UNA configuración.
     * Si ya existe, se actualiza. Si no, se crea.
     * 
     * Body JSON:
     * {
     *   "phoneNumber": "+521234567890",
     *   "phoneNumberId": "123456789012345",
     *   "accessToken": "EAAxxxxxx...",
     *   "businessName": "Mi Gimnasio",
     *   "logo": "base64_encoded_image", // Opcional
     *   "logoFilename": "logo.jpg", // Opcional
     *   "address": "Calle Principal 123", // Opcional
     *   "description": "El mejor gimnasio de la ciudad", // Opcional
     *   "email": "contacto@migimnasio.com", // Opcional
     *   "vertical": "HEALTH", // Opcional
     *   "websites": ["https://migimnasio.com"] // Opcional
     * }
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "id": "uuid",
     *     "created": true,
     *     "config": { ... },
     *     "profileUpdated": true,
     *     "logoUploaded": true
     *   }
     * }
     */
    public function registerBusinessProfile(string $customerId): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        if (empty($customerId)) {
            ApiHelper::respond(['error' => 'customerId es requerido'], 422);
        }

        $payload = ApiHelper::getJsonBody();
        $this->validateRequired($payload, ['phoneNumber', 'phoneNumberId', 'businessName']);

        // Preparar datos para guardar en BD
        $configData = [
            'CustomerId' => $customerId,
            'PhoneNumber' => $payload['phoneNumber'],
            'PhoneNumberId' => $payload['phoneNumberId'],
            'AccessToken' => $payload['accessToken'] ?? null,
            'BusinessName' => $payload['businessName'],
            'BusinessAddress' => $payload['address'] ?? null,
            'BusinessDescription' => $payload['description'] ?? null,
            'BusinessEmail' => $payload['email'] ?? null,
            'BusinessVertical' => $payload['vertical'] ?? null,
            'BusinessWebsites' => $payload['websites'] ?? null,
            'CreatedBy' => $customerId
        ];

        // Crear o actualizar configuración en BD (upsert por CustomerId)
        $dbResult = $this->configModel->upsert($configData);

        if (!$dbResult['success']) {
            ApiHelper::respond([
                'success' => false,
                'error' => $dbResult['error']
            ], 422);
        }

        // Intentar actualizar perfil en WhatsApp API
        $logoUploaded = false;
        $profileUpdated = false;
        $logoPath = null;

        // Si hay logo en base64, guardarlo temporalmente
        if (isset($payload['logo']) && !empty($payload['logo'])) {
            $logoPath = $this->saveTempLogo($payload['logo'], $payload['logoFilename'] ?? null);
        }

        // Preparar datos adicionales del perfil para la API de WhatsApp
        $additionalData = [];
        if (!empty($payload['address'])) {
            $additionalData['address'] = $payload['address'];
        }
        if (!empty($payload['description'])) {
            $additionalData['description'] = $payload['description'];
        }
        if (!empty($payload['email'])) {
            $additionalData['email'] = $payload['email'];
        }
        if (!empty($payload['vertical'])) {
            $additionalData['vertical'] = $payload['vertical'];
        }
        if (!empty($payload['websites'])) {
            $additionalData['websites'] = $payload['websites'];
        }

        // Registrar perfil en WhatsApp API usando la configuración del customer
        // (que ya fue guardada en BD, así que el servicio la cargará)
        $service = $this->getServiceForCustomer($customerId);
        $whatsappResult = $service->registerBusinessProfile(
            $payload['businessName'],
            $logoPath,
            $additionalData
        );

        $profileUpdated = $whatsappResult['success'];
        $logoUploaded = $logoPath !== null && $whatsappResult['success'];

        // Eliminar archivo temporal si existe
        if ($logoPath && file_exists($logoPath)) {
            unlink($logoPath);
        }

        // Obtener la configuración creada/actualizada
        $config = $this->configModel->findById($dbResult['id']);

        ApiHelper::respond([
            'success' => true,
            'data' => [
                'id' => $dbResult['id'],
                'created' => $dbResult['created'],
                'config' => $this->formatConfig($config),
                'profileUpdated' => $profileUpdated,
                'logoUploaded' => $logoUploaded,
                'whatsappError' => $whatsappResult['success'] ? null : $whatsappResult['error']
            ]
        ]);
    }

    /**
     * GET /api/customers/whatsapp/business-profile/:customerId
     * 
     * Obtiene la configuración de WhatsApp de un customer.
     * Cada customer tiene una sola configuración.
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": { ... }
     * }
     */
    public function getBusinessProfile(string $customerId): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        if (empty($customerId)) {
            ApiHelper::respond(['error' => 'customerId es requerido'], 422);
        }

        $config = $this->configModel->findByCustomerId($customerId);

        if (!$config) {
            ApiHelper::respond([
                'success' => false,
                'error' => 'No hay configuración de WhatsApp para este customer'
            ], 404);
        }

        ApiHelper::respond([
            'success' => true,
            'data' => $this->formatConfig($config)
        ]);
    }

    // ==================== HELPERS ====================

    /**
     * Formatea una configuración para la respuesta de la API
     */
    private function formatConfig(?array $config): ?array
    {
        if (!$config) {
            return null;
        }

        return [
            'id' => $config['Id'],
            'customerId' => $config['CustomerId'],
            'phoneNumber' => $config['PhoneNumber'],
            'phoneNumberId' => $config['PhoneNumberId'],
            'hasAccessToken' => !empty($config['AccessToken']),
            'businessName' => $config['BusinessName'],
            'address' => $config['BusinessAddress'],
            'description' => $config['BusinessDescription'],
            'email' => $config['BusinessEmail'],
            'vertical' => $config['BusinessVertical'],
            'websites' => $config['BusinessWebsites'] ? json_decode($config['BusinessWebsites'], true) : null,
            'profilePictureUrl' => $config['ProfilePictureUrl'],
            'isActive' => (bool) $config['IsActive'],
            'createdAt' => $config['CreatedAt'],
            'updatedAt' => $config['UpdatedAt']
        ];
    }

    /**
     * Guarda un logo en base64 como archivo temporal
     */
    private function saveTempLogo(string $logoBase64, ?string $filename = null): ?string
    {
        $logoData = $logoBase64;
        
        // Remover el prefijo data:image si existe
        if (preg_match('/^data:image\/(\w+);base64,/', $logoData, $matches)) {
            $logoData = substr($logoData, strpos($logoData, ',') + 1);
            $extension = $matches[1];
        } else {
            $extension = 'jpg';
        }

        // Decodificar base64
        $decodedLogo = base64_decode($logoData);
        
        if ($decodedLogo === false) {
            return null;
        }

        // Guardar temporalmente
        $filename = $filename ?? 'logo.' . $extension;
        $tempFile = sys_get_temp_dir() . '/' . uniqid('whatsapp_logo_') . '_' . $filename;
        
        if (file_put_contents($tempFile, $decodedLogo) === false) {
            return null;
        }

        return $tempFile;
    }

    /**
     * Valida que los campos requeridos estén presentes
     */
    private function validateRequired(array $payload, array $required): void
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($payload[$field]) || (is_string($payload[$field]) && trim($payload[$field]) === '')) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            ApiHelper::respond([
                'error' => 'Campos requeridos faltantes: ' . implode(', ', $missing),
                'missing' => $missing,
            ], 422);
        }
    }

    /**
     * Responde con el resultado del envío
     */
    private function respondResult(array $result): void
    {
        $statusCode = $result['success'] ? 200 : 422;
        ApiHelper::respond([
            'success' => $result['success'],
            'subscriptionId' => $result['subscriptionId'],
            'messageId' => $result['messageId'],
            'error' => $result['errorMessage'],
        ], $statusCode);
    }
}
