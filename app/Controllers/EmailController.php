<?php

namespace Controllers;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Services/EmailService.php';
require_once __DIR__ . '/../Models/EmailTypeModel.php';
require_once __DIR__ . '/../Models/EmailCodeModel.php';
require_once __DIR__ . '/../Helpers/ApiHelper.php';

use Core\Controller;
use App\Services\EmailService;
use Models\EmailTypeModel;
use Models\EmailCodeModel;
use ApiHelper;

/**
 * Controlador de Emails
 * 
 * Maneja los endpoints de envío de correos electrónicos y verificación de códigos
 */
class EmailController extends Controller
{
    private EmailService $emailService;
    private EmailTypeModel $emailTypeModel;
    private EmailCodeModel $emailCodeModel;

    public function __construct()
    {
        parent::__construct();
        $this->emailService = new EmailService();
        $this->emailTypeModel = new EmailTypeModel();
        $this->emailCodeModel = new EmailCodeModel();
    }

    // ==================== STATUS ====================

    /**
     * GET /api/email/status
     * 
     * Verifica el estado de configuración del servicio de email
     */
    public function status(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        ApiHelper::respond([
            'configured' => $this->emailService->isConfigured(),
            'timestamp' => time(),
        ]);
    }

    /**
     * GET /api/email/types
     * 
     * Obtiene los tipos de email disponibles
     */
    public function types(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $types = $this->emailTypeModel->getActiveTypes();

        ApiHelper::respond([
            'types' => array_map(fn($type) => [
                'id' => (int) $type['Id'],
                'code' => $type['Code'],
                'name' => $type['Name'],
                'description' => $type['Description'],
                'requiresCode' => (bool) $type['RequiresCode'],
                'codeExpirationMinutes' => $type['CodeExpirationMinutes'] ? (int) $type['CodeExpirationMinutes'] : null,
                'maxAttempts' => $type['MaxAttempts'] ? (int) $type['MaxAttempts'] : null,
            ], $types),
        ]);
    }

    // ==================== ENVÍO DE CORREOS ====================

    /**
     * POST /api/email/send
     * 
     * Envía un correo electrónico normal
     * 
     * Body:
     * - email: string (requerido) - Dirección de correo del destinatario
     * - subject: string (requerido) - Asunto del correo
     * - body: string (requerido) - Cuerpo del correo (HTML permitido)
     * - toName: string (opcional) - Nombre del destinatario
     * - customerApiId: string (opcional) - ID del customer relacionado
     * - metadata: object (opcional) - Datos adicionales
     * - attachments: array (opcional) - Adjuntos en base64
     */
    public function send(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $data = ApiHelper::getJsonBody();

        // Validar campos requeridos
        if (empty($data['email'])) {
            ApiHelper::respond(['error' => 'El campo email es requerido'], 422);
        }
        if (empty($data['subject'])) {
            ApiHelper::respond(['error' => 'El campo subject es requerido'], 422);
        }
        if (empty($data['body'])) {
            ApiHelper::respond(['error' => 'El campo body es requerido'], 422);
        }

        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond(['error' => 'El formato del email no es válido'], 422);
        }

        $result = $this->emailService->send(
            $data['email'],
            $data['subject'],
            $data['body'],
            'NORMAL',
            [
                'toName' => $data['toName'] ?? null,
                'customerApiId' => $data['customerApiId'] ?? null,
                'adminId' => $data['adminId'] ?? null,
                'userId' => $data['userId'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'attachments' => $this->normalizeAttachments($data['attachments'] ?? []),
            ]
        );

        if ($result['success']) {
            ApiHelper::respond([
                'success' => true,
                'message' => $result['message'],
                'emailCodeId' => $result['emailCodeId'],
            ]);
        } else {
            ApiHelper::respond([
                'success' => false,
                'error' => $result['message'],
            ], 500);
        }
    }

    /**
     * POST /api/email/send/password-reset
     * 
     * Envía un correo de restablecimiento de contraseña
     * 
     * Body:
     * - email: string (requerido) - Dirección de correo del destinatario
     * - toName: string (opcional) - Nombre del destinatario
     * - userName: string (opcional) - Nombre de usuario para personalizar el mensaje
     * - customerApiId: string (opcional) - ID del customer relacionado
     * - userId: int (opcional) - ID del usuario relacionado
     * - metadata: object (opcional) - Datos adicionales
     */
    public function sendPasswordReset(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $data = ApiHelper::getJsonBody();

        // Validar campo email
        if (empty($data['email'])) {
            ApiHelper::respond(['error' => 'El campo email es requerido'], 422);
        }

        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond(['error' => 'El formato del email no es válido'], 422);
        }

        $result = $this->emailService->sendPasswordReset(
            $data['email'],
            [
                'toName' => $data['toName'] ?? null,
                'userName' => $data['userName'] ?? null,
                'customerApiId' => $data['customerApiId'] ?? null,
                'adminId' => $data['adminId'] ?? null,
                'userId' => $data['userId'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]
        );

        if ($result['success']) {
            // En producción, no devolver el código
            $response = [
                'success' => true,
                'message' => $result['message'],
                'emailCodeId' => $result['emailCodeId'],
                'expiresAt' => $result['data']['expiresAt'] ?? null,
                'expirationMinutes' => $result['data']['expirationMinutes'] ?? null,
            ];

            // Solo en modo desarrollo, incluir el código para pruebas
            $appMode = getenv('APP_MODE') ?: 'production';
            if ($appMode === 'development') {
                $response['code'] = $result['code'];
            }

            ApiHelper::respond($response);
        } else {
            ApiHelper::respond([
                'success' => false,
                'error' => $result['message'],
            ], 429); // Too Many Requests si es rate limiting
        }
    }

    /**
     * POST /api/email/send/email-confirmation
     * 
     * Envía un correo de confirmación de email
     * 
     * Body:
     * - email: string (requerido) - Dirección de correo a confirmar
     * - toName: string (opcional) - Nombre del destinatario
     * - userName: string (opcional) - Nombre de usuario para personalizar el mensaje
     * - customerApiId: string (opcional) - ID del customer relacionado
     * - userId: int (opcional) - ID del usuario relacionado
     * - metadata: object (opcional) - Datos adicionales
     */
    public function sendEmailConfirmation(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $data = ApiHelper::getJsonBody();

        // Validar campo email
        if (empty($data['email'])) {
            ApiHelper::respond(['error' => 'El campo email es requerido'], 422);
        }

        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond(['error' => 'El formato del email no es válido'], 422);
        }

        $result = $this->emailService->sendEmailConfirmation(
            $data['email'],
            [
                'toName' => $data['toName'] ?? null,
                'userName' => $data['userName'] ?? null,
                'customerApiId' => $data['customerApiId'] ?? null,
                'adminId' => $data['adminId'] ?? null,
                'userId' => $data['userId'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]
        );

        if ($result['success']) {
            $response = [
                'success' => true,
                'message' => $result['message'],
                'emailCodeId' => $result['emailCodeId'],
                'expiresAt' => $result['data']['expiresAt'] ?? null,
                'expirationMinutes' => $result['data']['expirationMinutes'] ?? null,
            ];

            // Solo en modo desarrollo, incluir el código para pruebas
            $appMode = getenv('APP_MODE') ?: 'production';
            if ($appMode === 'development') {
                $response['code'] = $result['code'];
            }

            ApiHelper::respond($response);
        } else {
            ApiHelper::respond([
                'success' => false,
                'error' => $result['message'],
            ], 429);
        }
    }

    /**
     * POST /api/email/send/welcome
     * 
     * Envía un correo de bienvenida
     * 
     * Body:
     * - email: string (requerido) - Dirección de correo del destinatario
     * - toName: string (opcional) - Nombre del destinatario
     * - userName: string (opcional) - Nombre de usuario para personalizar el mensaje
     * - companyName: string (opcional) - Nombre de la empresa
     * - customerApiId: string (opcional) - ID del customer relacionado
     * - metadata: object (opcional) - Datos adicionales
     * - attachments: array (opcional) - Adjuntos en base64
     */
    public function sendWelcome(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $data = ApiHelper::getJsonBody();

        // Validar campo email
        if (empty($data['email'])) {
            ApiHelper::respond(['error' => 'El campo email es requerido'], 422);
        }

        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond(['error' => 'El formato del email no es válido'], 422);
        }

        $result = $this->emailService->sendWelcome(
            $data['email'],
            [
                'toName' => $data['toName'] ?? null,
                'userName' => $data['userName'] ?? null,
                'companyName' => $data['companyName'] ?? null,
                'customerApiId' => $data['customerApiId'] ?? null,
                'adminId' => $data['adminId'] ?? null,
                'userId' => $data['userId'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'attachments' => $this->normalizeAttachments($data['attachments'] ?? []),
            ]
        );

        if ($result['success']) {
            ApiHelper::respond([
                'success' => true,
                'message' => $result['message'],
                'emailCodeId' => $result['emailCodeId'],
            ]);
        } else {
            ApiHelper::respond([
                'success' => false,
                'error' => $result['message'],
            ], 500);
        }
    }

    /**
     * POST /api/email/send/notification
     * 
     * Envía una notificación por correo
     * 
     * Body:
     * - email: string (requerido) - Dirección de correo del destinatario
     * - subject: string (requerido) - Asunto del correo
     * - body: string (requerido) - Cuerpo del correo (HTML permitido)
     * - toName: string (opcional) - Nombre del destinatario
     * - customerApiId: string (opcional) - ID del customer relacionado
     * - metadata: object (opcional) - Datos adicionales
     * - attachments: array (opcional) - Adjuntos en base64
     */
    public function sendNotification(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $data = ApiHelper::getJsonBody();

        // Validar campos requeridos
        if (empty($data['email'])) {
            ApiHelper::respond(['error' => 'El campo email es requerido'], 422);
        }
        if (empty($data['subject'])) {
            ApiHelper::respond(['error' => 'El campo subject es requerido'], 422);
        }
        if (empty($data['body'])) {
            ApiHelper::respond(['error' => 'El campo body es requerido'], 422);
        }

        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond(['error' => 'El formato del email no es válido'], 422);
        }

        $result = $this->emailService->sendNotification(
            $data['email'],
            $data['subject'],
            $data['body'],
            [
                'toName' => $data['toName'] ?? null,
                'customerApiId' => $data['customerApiId'] ?? null,
                'adminId' => $data['adminId'] ?? null,
                'userId' => $data['userId'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'attachments' => $this->normalizeAttachments($data['attachments'] ?? []),
            ]
        );

        if ($result['success']) {
            ApiHelper::respond([
                'success' => true,
                'message' => $result['message'],
                'emailCodeId' => $result['emailCodeId'],
            ]);
        } else {
            ApiHelper::respond([
                'success' => false,
                'error' => $result['message'],
            ], 500);
        }
    }

    // ==================== VERIFICACIÓN DE CÓDIGOS ====================

    /**
     * POST /api/email/verify
     * 
     * Verifica un código de verificación genérico
     * 
     * Body:
     * - email: string (requerido) - Dirección de correo
     * - code: string (requerido) - Código de verificación (6 dígitos)
     * - type: string (requerido) - Tipo de código (PASSWORD_RESET, EMAIL_CONFIRMATION)
     */
    public function verify(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $data = ApiHelper::getJsonBody();

        // Validar campos requeridos
        if (empty($data['email'])) {
            ApiHelper::respond(['error' => 'El campo email es requerido'], 422);
        }
        if (empty($data['code'])) {
            ApiHelper::respond(['error' => 'El campo code es requerido'], 422);
        }
        if (empty($data['type'])) {
            ApiHelper::respond(['error' => 'El campo type es requerido'], 422);
        }

        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond(['error' => 'El formato del email no es válido'], 422);
        }

        $result = $this->emailService->verifyCode(
            $data['email'],
            $data['code'],
            strtoupper($data['type'])
        );

        if ($result['valid']) {
            ApiHelper::respond([
                'valid' => true,
                'message' => $result['message'],
                'emailCodeId' => $result['emailCodeId'],
                'data' => $result['data'] ?? null,
            ]);
        } else {
            ApiHelper::respond([
                'valid' => false,
                'message' => $result['message'],
                'attemptsRemaining' => $result['attemptsRemaining'] ?? null,
            ], 400);
        }
    }

    /**
     * POST /api/email/verify/password-reset
     * 
     * Verifica un código de restablecimiento de contraseña
     * 
     * Body:
     * - email: string (requerido) - Dirección de correo
     * - code: string (requerido) - Código de verificación (6 dígitos)
     */
    public function verifyPasswordReset(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $data = ApiHelper::getJsonBody();

        // Validar campos requeridos
        if (empty($data['email'])) {
            ApiHelper::respond(['error' => 'El campo email es requerido'], 422);
        }
        if (empty($data['code'])) {
            ApiHelper::respond(['error' => 'El campo code es requerido'], 422);
        }

        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond(['error' => 'El formato del email no es válido'], 422);
        }

        $result = $this->emailService->verifyPasswordResetCode($data['email'], $data['code']);

        if ($result['valid']) {
            ApiHelper::respond([
                'valid' => true,
                'message' => $result['message'],
                'emailCodeId' => $result['emailCodeId'],
                'data' => $result['data'] ?? null,
            ]);
        } else {
            ApiHelper::respond([
                'valid' => false,
                'message' => $result['message'],
                'attemptsRemaining' => $result['attemptsRemaining'] ?? null,
            ], 400);
        }
    }

    /**
     * POST /api/email/verify/email-confirmation
     * 
     * Verifica un código de confirmación de email
     * 
     * Body:
     * - email: string (requerido) - Dirección de correo
     * - code: string (requerido) - Código de verificación (6 dígitos)
     */
    public function verifyEmailConfirmation(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $data = ApiHelper::getJsonBody();

        // Validar campos requeridos
        if (empty($data['email'])) {
            ApiHelper::respond(['error' => 'El campo email es requerido'], 422);
        }
        if (empty($data['code'])) {
            ApiHelper::respond(['error' => 'El campo code es requerido'], 422);
        }

        // Validar formato de email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond(['error' => 'El formato del email no es válido'], 422);
        }

        $result = $this->emailService->verifyEmailConfirmationCode($data['email'], $data['code']);

        if ($result['valid']) {
            ApiHelper::respond([
                'valid' => true,
                'message' => $result['message'],
                'emailCodeId' => $result['emailCodeId'],
                'data' => $result['data'] ?? null,
            ]);
        } else {
            ApiHelper::respond([
                'valid' => false,
                'message' => $result['message'],
                'attemptsRemaining' => $result['attemptsRemaining'] ?? null,
            ], 400);
        }
    }

    // ==================== HISTORIAL Y ESTADÍSTICAS ====================

    /**
     * GET /api/email/history/:email
     * 
     * Obtiene el historial de correos enviados a un email
     * 
     * Query params:
     * - limit: int (opcional, default: 50, max: 100)
     */
    public function history(string $email): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        if (empty($email)) {
            ApiHelper::respond(['error' => 'El email es requerido'], 422);
        }

        // Validar formato de email
        $email = urldecode($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiHelper::respond(['error' => 'El formato del email no es válido'], 422);
        }

        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));

        $history = $this->emailService->getHistory($email, $limit);

        ApiHelper::respond([
            'email' => $email,
            'count' => count($history),
            'history' => array_map(fn($record) => [
                'id' => $record['Id'],
                'type' => $record['TypeCode'],
                'typeName' => $record['TypeName'],
                'subject' => $record['Subject'],
                'sentAt' => $record['SentAt'],
                'expiresAt' => $record['ExpiresAt'],
                'isUsed' => (bool) $record['IsUsed'],
                'usedAt' => $record['UsedAt'],
                'attempts' => (int) $record['Attempts'],
            ], $history),
        ]);
    }

    /**
     * GET /api/email/stats
     * 
     * Obtiene estadísticas de envíos de correo por tipo
     */
    public function stats(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsGet();

        $stats = $this->emailService->getStats();

        ApiHelper::respond([
            'stats' => array_map(fn($stat) => [
                'id' => (int) $stat['Id'],
                'code' => $stat['Code'],
                'name' => $stat['Name'],
                'totalSent' => (int) $stat['TotalSent'],
                'totalUsed' => (int) $stat['TotalUsed'],
                'totalPending' => (int) $stat['TotalPending'],
            ], $stats),
            'timestamp' => time(),
        ]);
    }

    /**
     * POST /api/email/cleanup
     * 
     * Limpia los códigos expirados (para tareas programadas)
     */
    public function cleanup(): void
    {
        ApiHelper::respondIfOptions();
        ApiHelper::allowedMethodsPost();

        $cleaned = $this->emailService->cleanupExpired();

        ApiHelper::respond([
            'success' => true,
            'cleaned' => $cleaned,
            'timestamp' => time(),
        ]);
    }

    /**
     * Normaliza adjuntos enviados en JSON.
     *
     * Formato esperado:
     * attachments: [
     *   { name: "archivo.pdf", contentBase64: "...", mime: "application/pdf" }
     * ]
     */
    private function normalizeAttachments($attachments): array
    {
        if (empty($attachments)) {
            return [];
        }

        if (!is_array($attachments)) {
            ApiHelper::respond(['error' => 'El campo attachments debe ser un arreglo'], 422);
        }

        // Permite enviar un solo objeto en vez de un arreglo de objetos.
        if (isset($attachments['contentBase64']) || isset($attachments['content'])) {
            $attachments = [$attachments];
        }

        if (count($attachments) > 5) {
            ApiHelper::respond(['error' => 'Solo se permiten hasta 5 adjuntos por correo'], 422);
        }

        $normalized = [];
        $totalBytes = 0;
        $maxFileBytes = 10 * 1024 * 1024;
        $maxTotalBytes = 15 * 1024 * 1024;

        foreach ($attachments as $index => $attachment) {
            if (!is_array($attachment)) {
                ApiHelper::respond(['error' => "El adjunto {$index} debe ser un objeto"], 422);
            }

            $name = trim((string) ($attachment['name'] ?? $attachment['filename'] ?? ''));
            $contentBase64 = (string) ($attachment['contentBase64'] ?? $attachment['content'] ?? '');
            $mime = trim((string) ($attachment['mime'] ?? $attachment['mimeType'] ?? 'application/octet-stream'));

            if ($name === '') {
                ApiHelper::respond(['error' => "El adjunto {$index} requiere name o filename"], 422);
            }

            if ($contentBase64 === '') {
                ApiHelper::respond(['error' => "El adjunto {$index} requiere contentBase64"], 422);
            }

            $name = basename(str_replace('\\', '/', $name));
            if ($name === '' || preg_match('/[\x00-\x1F\x7F]/', $name)) {
                ApiHelper::respond(['error' => "El nombre del adjunto {$index} no es valido"], 422);
            }

            if (str_contains($contentBase64, ',')) {
                $contentBase64 = substr($contentBase64, strpos($contentBase64, ',') + 1);
            }

            $contentBase64 = preg_replace('/\s+/', '', $contentBase64);
            $content = base64_decode($contentBase64, true);

            if ($content === false) {
                ApiHelper::respond(['error' => "El contenido del adjunto {$index} no es base64 valido"], 422);
            }

            $fileBytes = strlen($content);
            if ($fileBytes === 0) {
                ApiHelper::respond(['error' => "El adjunto {$index} esta vacio"], 422);
            }

            if ($fileBytes > $maxFileBytes) {
                ApiHelper::respond(['error' => "El adjunto {$index} excede 10 MB"], 413);
            }

            $totalBytes += $fileBytes;
            if ($totalBytes > $maxTotalBytes) {
                ApiHelper::respond(['error' => 'El total de adjuntos excede 15 MB'], 413);
            }

            $normalized[] = [
                'content' => $content,
                'name' => $name,
                'mime' => $mime !== '' ? $mime : 'application/octet-stream',
            ];
        }

        return $normalized;
    }
}
