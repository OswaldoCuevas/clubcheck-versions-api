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
