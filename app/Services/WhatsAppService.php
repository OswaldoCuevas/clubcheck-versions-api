<?php

namespace App\Services;

require_once __DIR__ . '/../Models/MessageSentModel.php';

use Models\MessageSentModel;

/**
 * Servicio de WhatsApp Business API
 * 
 * Maneja el envío de mensajes y templates a través de la API de WhatsApp
 */
class WhatsAppService
{
    private string $apiUrl;
    private string $phoneNumberId;
    private string $accessToken;
    private array $config;
    private MessageSentModel $messageSentModel;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/whatsapp.php';
        $this->apiUrl = $this->config['api_url'];
        $this->phoneNumberId = $this->config['phone_number_id'];
        $this->accessToken = $this->config['access_token'];
        $this->messageSentModel = new MessageSentModel();
    }

    // ==================== RESULTADO ====================

    /**
     * Estructura de resultado de envío de mensaje
     */
    public static function createResult(
        bool $success,
        ?string $errorMessage = null,
        ?string $responseContent = null,
        int $statusCode = 0,
        ?string $subscriptionId = null,
        ?string $messageId = null
    ): array {
        return [
            'success' => $success,
            'errorMessage' => $errorMessage,
            'responseContent' => $responseContent,
            'statusCode' => $statusCode,
            'subscriptionId' => $subscriptionId,
            'messageId' => $messageId,
        ];
    }

    // ==================== VALIDACIÓN ====================

    /**
     * Verifica si el servicio está correctamente configurado
     */
    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId) && !empty($this->accessToken);
    }

    /**
     * Normaliza un número de teléfono añadiendo código de país si es necesario
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Si no tiene código de país, agregar el default (México)
        if (strlen($phone) === 10) {
            $phone = $this->config['default_country_code'] . $phone;
        }
        
        return $phone;
    }

    // ==================== ENVÍO INTERNO ====================

    /**
     * Envía un request a la API de WhatsApp
     */
    private function sendRequest(string $jsonBody): array
    {
        if (!$this->isConfigured()) {
            return self::createResult(
                false,
                'WhatsApp no está configurado. Verifique WHATSAPP_PHONE_NUMBER_ID y WHATSAPP_ACCESS_TOKEN.',
                null,
                0
            );
        }

        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return self::createResult(false, "Error de conexión: {$curlError}", null, 0);
        }

        $success = $httpCode >= 200 && $httpCode < 300;
        $errorMessage = null;
        $messageId = null;

        if (!$success) {
            $responseData = json_decode($response, true);
            $errorMessage = $responseData['error']['message'] ?? "HTTP {$httpCode}: " . substr($response, 0, 400);
        } else {
            $responseData = json_decode($response, true);
            $messageId = $responseData['messages'][0]['id'] ?? null;
        }

        return self::createResult($success, $errorMessage, $response, $httpCode, null, $messageId);
    }

    // ==================== TEMPLATES ====================

    /**
     * Envía un template de nueva suscripción (bienvenida)
     * 
     * Template: subscription
     * Parámetros: nombre, club, fecha inicio, fecha fin
     */
    public function sendSubscriptionTemplate(
        string $phone,
        string $firstName,
        string $clubName,
        string $startDate,
        string $endDate,
        string $customerApiId,
        ?string $userId = null,
        ?string $subscriptionId = null,
        ?string $username = null
    ): array {
        $phone = $this->normalizePhone($phone);
        $templateConfig = $this->config['templates']['subscription'];

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateConfig['name'],
                'language' => ['code' => $templateConfig['language']],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $this->sanitize($firstName)],
                            ['type' => 'text', 'text' => $this->sanitize($clubName)],
                            ['type' => 'text', 'text' => $this->sanitize($startDate)],
                            ['type' => 'text', 'text' => $this->sanitize($endDate)],
                        ],
                    ],
                ],
            ],
        ];

        $description = "Bienvenida de membresía {$clubName}: {$startDate} - {$endDate}";
        $result = $this->sendAndLog($body, $phone, $description, $customerApiId, $userId, $subscriptionId, $username);
        
        return $result;
    }

    /**
     * Envía un template de advertencia de vencimiento próximo
     * 
     * Template: warning_subscription
     * Parámetros: club, días restantes
     */
    public function sendWarningTemplate(
        string $phone,
        string $clubName,
        string $days,
        string $customerApiId,
        ?string $userId = null,
        ?string $subscriptionId = null,
        ?string $username = null
    ): array {
        $phone = $this->normalizePhone($phone);
        $templateConfig = $this->config['templates']['warning_subscription'];

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateConfig['name'],
                'language' => ['code' => $templateConfig['language']],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $this->sanitize($clubName)],
                            ['type' => 'text', 'text' => $this->sanitize($days)],
                        ],
                    ],
                ],
            ],
        ];

        $description = "Aviso de membresía: vence en {$days}. Club: {$clubName}";
        return $this->sendAndLog($body, $phone, $description, $customerApiId, $userId, $subscriptionId, $username);
    }

    /**
     * Envía un template de membresía finalizada
     * 
     * Template: finalized_subscription
     * Parámetros: club
     */
    public function sendFinalizedTemplate(
        string $phone,
        string $clubName,
        string $customerApiId,
        ?string $userId = null,
        ?string $subscriptionId = null,
        ?string $username = null
    ): array {
        $phone = $this->normalizePhone($phone);
        $templateConfig = $this->config['templates']['finalized_subscription'];

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateConfig['name'],
                'language' => ['code' => $templateConfig['language']],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $this->sanitize($clubName)],
                        ],
                    ],
                ],
            ],
        ];

        $description = "Aviso de membresía finalizada. Club: {$clubName}";
        return $this->sendAndLog($body, $phone, $description, $customerApiId, $userId, $subscriptionId, $username);
    }

    /**
     * Envía un template de último día
     * 
     * Template: warning_last_day
     * Sin parámetros adicionales
     */
    public function sendLastDayTemplate(
        string $phone,
        string $clubName,
        string $customerApiId,
        ?string $userId = null,
        ?string $subscriptionId = null,
        ?string $username = null
    ): array {
        $phone = $this->normalizePhone($phone);
        $templateConfig = $this->config['templates']['warning_last_day'];

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateConfig['name'],
                'language' => ['code' => $templateConfig['language']],
            ],
        ];

        $description = "Aviso de membresía: último día. Club: {$clubName}";
        return $this->sendAndLog($body, $phone, $description, $customerApiId, $userId, $subscriptionId, $username);
    }

    // ==================== BULK OPERATIONS ====================

    /**
     * Envía mensajes en bulk y retorna los subscriptionId exitosos
     * 
     * Cada item del bulk debe tener:
     * - template: string ('subscription', 'warning', 'finalized', 'last_day')
     * - subscriptionId: string (requerido)
     * - phone: string
     * - userId: string|null
     * - parameters: array (days, startDate, endDate, firstName, clubName)
     * 
     * @param array $bulkItems Lista de items a enviar
     * @param string $customerApiId ID del cliente de la API
     * @return array ['success' => [], 'failed' => [], 'total' => int, 'successCount' => int]
     */
    public function sendBulk(array $bulkItems, string $customerApiId): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'total' => count($bulkItems),
            'successCount' => 0,
            'failedCount' => 0,
        ];

        if (empty($bulkItems)) {
            return $results;
        }

        // Limitar el tamaño del bulk
        $maxBulkSize = $this->config['max_bulk_size'] ?? 100;
        if (count($bulkItems) > $maxBulkSize) {
            $bulkItems = array_slice($bulkItems, 0, $maxBulkSize);
            $results['total'] = count($bulkItems);
            $results['warning'] = "Se limitó a {$maxBulkSize} mensajes";
        }

        foreach ($bulkItems as $item) {
            $subscriptionId = $item['subscriptionId'] ?? null;
            
            if (empty($subscriptionId)) {
                $results['failed'][] = [
                    'subscriptionId' => null,
                    'error' => 'subscriptionId es requerido',
                ];
                $results['failedCount']++;
                continue;
            }

            $template = $item['template'] ?? '';
            $phone = $item['phone'] ?? '';
            $userId = $item['userId'] ?? null;
            $username = $item['username'] ?? null;
            $parameters = $item['parameters'] ?? [];

            if (empty($phone)) {
                $results['failed'][] = [
                    'subscriptionId' => $subscriptionId,
                    'error' => 'El teléfono es requerido',
                ];
                $results['failedCount']++;
                continue;
            }

            $result = $this->sendTemplateByType(
                $template,
                $phone,
                $parameters,
                $customerApiId,
                $userId,
                $subscriptionId,
                $username
            );

            if ($result['success']) {
                $results['success'][] = [
                    'subscriptionId' => $subscriptionId,
                    'messageId' => $result['messageId'],
                ];
                $results['successCount']++;
            } else {
                $results['failed'][] = [
                    'subscriptionId' => $subscriptionId,
                    'error' => $result['errorMessage'],
                ];
                $results['failedCount']++;
            }
        }

        return $results;
    }

    /**
     * Envía un template según su tipo
     */
    private function sendTemplateByType(
        string $template,
        string $phone,
        array $parameters,
        string $customerApiId,
        ?string $userId,
        ?string $subscriptionId,
        ?string $username
    ): array {
        $clubName = $parameters['clubName'] ?? 'tu club';

        switch (strtolower($template)) {
            case 'subscription':
            case 'new_subscription':
                return $this->sendSubscriptionTemplate(
                    $phone,
                    $parameters['firstName'] ?? 'Cliente',
                    $clubName,
                    $parameters['startDate'] ?? '',
                    $parameters['endDate'] ?? '',
                    $customerApiId,
                    $userId,
                    $subscriptionId,
                    $username
                );

            case 'warning':
            case 'warning_subscription':
                $days = $parameters['days'] ?? '3';
                $daysText = $days == 1 ? 'un día' : "{$days} días";
                return $this->sendWarningTemplate(
                    $phone,
                    $clubName,
                    $daysText,
                    $customerApiId,
                    $userId,
                    $subscriptionId,
                    $username
                );

            case 'finalized':
            case 'finalized_subscription':
                return $this->sendFinalizedTemplate(
                    $phone,
                    $clubName,
                    $customerApiId,
                    $userId,
                    $subscriptionId,
                    $username
                );

            case 'last_day':
            case 'warning_last_day':
                return $this->sendLastDayTemplate(
                    $phone,
                    $clubName,
                    $customerApiId,
                    $userId,
                    $subscriptionId,
                    $username
                );

            default:
                return self::createResult(
                    false,
                    "Template desconocido: {$template}",
                    null,
                    0,
                    $subscriptionId
                );
        }
    }

    // ==================== LOGGING ====================

    /**
     * Envía el mensaje y registra el resultado en la base de datos
     */
    private function sendAndLog(
        array $body,
        string $phone,
        string $description,
        string $customerApiId,
        ?string $userId,
        ?string $subscriptionId,
        ?string $username
    ): array {
        $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);
        $result = $this->sendRequest($jsonBody);
        
        // Agregar subscriptionId al resultado
        $result['subscriptionId'] = $subscriptionId;

        // Guardar en la base de datos
        $this->logMessage($customerApiId, $userId, $username, $phone, $description, $result);

        return $result;
    }

    /**
     * Registra un intento de envío de mensaje en la base de datos
     */
    private function logMessage(
        string $customerApiId,
        ?string $userId,
        ?string $username,
        string $phoneNumber,
        string $message,
        array $result
    ): void {
        try {
            $now = new \DateTime('now', new \DateTimeZone('America/Mexico_City'));
            
            $data = [
                'Id' => $this->generateUuid(),
                'CustomerApiId' => $customerApiId,
                'UserId' => $userId,
                'Username' => $username,
                'PhoneNumber' => $phoneNumber,
                'Message' => $message,
                'SentDay' => $now->format('Y-m-d'),
                'SentHour' => $now->format('H:i:s'),
                'Successful' => $result['success'] ? 1 : 0,
                'ErrorMessage' => $result['errorMessage'],
            ];

            $this->messageSentModel->create($data);
        } catch (\Throwable $e) {
            // No interrumpir el flujo principal si falla el logging
            error_log("Error logging WhatsApp message: " . $e->getMessage());
        }
    }

    // ==================== UTILIDADES ====================

    /**
     * Sanitiza texto para templates
     */
    private function sanitize(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }
        return trim($value);
    }

    /**
     * Genera un UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // ==================== CONTEO MENSUAL ====================

    /**
     * Obtiene el conteo de mensajes enviados exitosamente en el mes actual
     */
    public function getMonthlyCount(string $customerApiId): int
    {
        $now = new \DateTime('now', new \DateTimeZone('America/Mexico_City'));
        return $this->messageSentModel->countSuccessfulByMonth(
            $customerApiId,
            (int) $now->format('m'),
            (int) $now->format('Y')
        );
    }
}
