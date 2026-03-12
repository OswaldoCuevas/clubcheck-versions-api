<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Services/WhatsAppService.php';
require_once __DIR__ . '/../Models/MessageSentModel.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';

use Core\Controller;
use App\Services\WhatsAppService;
use Models\MessageSentModel;
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

    public function __construct()
    {
        parent::__construct();
        $this->whatsAppService = new WhatsAppService();
        $this->messageSentModel = new MessageSentModel();
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

        $count = $this->whatsAppService->getMonthlyCount($customerApiId);

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

        $result = $this->whatsAppService->sendSubscriptionTemplate(
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

        $result = $this->whatsAppService->sendWarningTemplate(
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

        $result = $this->whatsAppService->sendFinalizedTemplate(
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

        $result = $this->whatsAppService->sendLastDayTemplate(
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

        $result = $this->whatsAppService->sendBulk($items, $payload['customerApiId']);

        ApiHelper::respond($result);
    }

    // ==================== PERFIL DEL NEGOCIO ====================

    /**
     * POST /api/customers/whatsapp/business-profile/register
     * 
     * Registra o actualiza el perfil del negocio de WhatsApp con nombre y logo
     * 
     * Body JSON:
     * {
     *   "businessName": "Mi Gimnasio",
     *   "logo": "base64_encoded_image", // Opcional
     *   "logoFilename": "logo.jpg", // Opcional, nombre del archivo
     *   "address": "Calle Principal 123", // Opcional
     *   "description": "El mejor gimnasio de la ciudad", // Opcional
     *   "email": "contacto@migimnasio.com", // Opcional
     *   "vertical": "HEALTH", // Opcional (AUTO, BEAUTY, APPAREL, EDU, ENTERTAIN, etc.)
     *   "websites": ["https://migimnasio.com"] // Opcional
     * }
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "businessName": "Mi Gimnasio",
     *     "profileUpdated": true,
     *     "logoUploaded": true
     *   }
     * }
     */
    public function registerBusinessProfile(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $payload = ApiHelper::getJsonBody();
        $this->validateRequired($payload, ['businessName']);

        $businessName = $payload['businessName'];
        $logoPath = null;
        $tempFile = null;

        // Si hay logo en base64, guardarlo temporalmente
        if (isset($payload['logo']) && !empty($payload['logo'])) {
            $logoData = $payload['logo'];
            
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
                ApiHelper::respond(['error' => 'El logo no es un base64 válido'], 422);
            }

            // Guardar temporalmente
            $filename = $payload['logoFilename'] ?? 'logo.' . $extension;
            $tempFile = sys_get_temp_dir() . '/' . uniqid('whatsapp_logo_') . '_' . $filename;
            
            if (file_put_contents($tempFile, $decodedLogo) === false) {
                ApiHelper::respond(['error' => 'No se pudo guardar el logo temporalmente'], 500);
            }

            $logoPath = $tempFile;
        }

        // Preparar datos adicionales del perfil
        $additionalData = [];
        $optionalFields = ['address', 'description', 'email', 'vertical', 'websites'];
        
        foreach ($optionalFields as $field) {
            if (isset($payload[$field]) && !empty($payload[$field])) {
                $additionalData[$field] = $payload[$field];
            }
        }

        // Registrar perfil
        $result = $this->whatsAppService->registerBusinessProfile(
            $businessName,
            $logoPath,
            $additionalData
        );

        // Eliminar archivo temporal si existe
        if ($tempFile && file_exists($tempFile)) {
            unlink($tempFile);
        }

        if ($result['success']) {
            ApiHelper::respond([
                'success' => true,
                'data' => $result['data']
            ]);
        } else {
            ApiHelper::respond([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    /**
     * GET /api/customers/whatsapp/business-profile
     * 
     * Obtiene el perfil actual del negocio de WhatsApp
     * 
     * Response:
     * {
     *   "success": true,
     *   "profile": {
     *     "about": "Mi Gimnasio",
     *     "address": "Calle Principal 123",
     *     "description": "El mejor gimnasio",
     *     "email": "contacto@migimnasio.com",
     *     "profile_picture_url": "https://...",
     *     "websites": ["https://migimnasio.com"],
     *     "vertical": "HEALTH"
     *   }
     * }
     */
    public function getBusinessProfile(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $result = $this->whatsAppService->getBusinessProfile();

        if ($result['success']) {
            ApiHelper::respond([
                'success' => true,
                'profile' => $result['profile']
            ]);
        } else {
            ApiHelper::respond([
                'success' => false,
                'error' => $result['error']
            ], 422);
        }
    }

    // ==================== HELPERS ====================

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
