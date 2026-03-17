<?php

namespace App\Services;

require_once __DIR__ . '/../Models/MessageSentModel.php';
require_once __DIR__ . '/../Models/WhatsAppConfigurationModel.php';
require_once __DIR__ . '/../../utils/CustomerPermits.php';

use Models\MessageSentModel;
use Models\WhatsAppConfigurationModel;
use CustomerPermits;

/**
 * Servicio de WhatsApp Business API
 * 
 * Maneja el envío de mensajes y templates a través de la API de WhatsApp
 * Puede usar la configuración global o la configuración específica de un customer
 */
class WhatsAppService
{
    private string $apiUrl;
    private string $phoneNumberId;
    private string $accessToken;
    private array $config;
    private MessageSentModel $messageSentModel;
    private ?string $customerId;

    /**
     * Constructor
     * 
     * @param string|null $customerId ID del customer (customerApiId o customerId). 
     *                                Si se proporciona, intenta usar su configuración personalizada.
     *                                Si no existe o está vacía, usa la configuración global.
     */
    public function __construct(?string $customerId = null)
    {
        $this->config = require __DIR__ . '/../../config/whatsapp.php';
        $this->customerId = $customerId;
        
        // Valores default desde config global
        $this->apiUrl = $this->config['api_url'];
        $this->phoneNumberId = $this->config['phone_number_id'];
        $this->accessToken = $this->config['access_token'];
        
        // Si se proporciona customerId, intentar cargar su configuración
        if (!empty($customerId)) {
            $this->loadCustomerConfig($customerId);
        }
        
        $this->messageSentModel = new MessageSentModel();
    }

    /**
     * Carga la configuración específica de un customer si existe
     */
    private function loadCustomerConfig(string $customerId): void
    {
        try {
            $configModel = new WhatsAppConfigurationModel();
            $customerConfig = $configModel->findByCustomerId($customerId);
            
            if ($customerConfig) {
                // Si tiene phoneNumberId, usarlo
                if (!empty($customerConfig['PhoneNumberId'])) {
                    $this->phoneNumberId = $customerConfig['PhoneNumberId'];
                }
                
                // Si tiene accessToken propio, usarlo
                if (!empty($customerConfig['AccessToken'])) {
                    $this->accessToken = $customerConfig['AccessToken'];
                }
            }
        } catch (\Throwable $e) {
            // Si falla la carga, continuar con config global (ya establecida)
            error_log("Error loading customer WhatsApp config: " . $e->getMessage());
        }
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
        ?string $username = null,
        ?string $errorMessage = null
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
        $result = $this->sendAndLog($body, $phone, $description, $customerApiId, $userId, $subscriptionId, $username, $errorMessage);
        
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
        ?string $username = null,
        ?string $errorMessage = null
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
        return $this->sendAndLog($body, $phone, $description, $customerApiId, $userId, $subscriptionId, $username, $errorMessage);
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
        ?string $username = null,
        ?string $errorMessage = null
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
        return $this->sendAndLog($body, $phone, $description, $customerApiId, $userId, $subscriptionId, $username, $errorMessage);
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
        ?string $username = null,
        ?string $errorMessage = null
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
        return $this->sendAndLog($body, $phone, $description, $customerApiId, $userId, $subscriptionId, $username, $errorMessage);
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

         // Obtener mes y año actual
        $now = new \DateTime('now', new \DateTimeZone('America/Mexico_City'));
        $month = (int) $now->format('m');
        $year = (int) $now->format('Y');
        $totalMessagesAtMonth = $this->messageSentModel->countSuccessfulByMonth($customerApiId, $month, $year);

        $customerPermits = new CustomerPermits($customerApiId);

        foreach ($bulkItems as $item) {
            $subscriptionId = $item['subscriptionId'] ?? null;
            $errorMessage = null;

            try{    
                $customerPermits->checkSendMessage($totalMessagesAtMonth);
            } catch (\App\Exceptions\ApiException $e) {
                $errorMessage = $e->getMessage();
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }

            if ($errorMessage) {
                $results['failed'][] = [
                    'subscriptionId' => $subscriptionId,
                    'error' => $errorMessage,
                ];
                $results['failedCount']++;
         
            }

           
            
            if (empty($subscriptionId)) {
                $errorMessage = 'subscriptionId es requerido';
                $results['failed'][] = [
                    'subscriptionId' => null,
                    'error' => $errorMessage,
                ];
                $results['failedCount']++;
    
            }

            $template = $item['template'] ?? '';
            $phone = $item['phone'] ?? '';
            $userId = $item['userId'] ?? null;
            $username = $item['username'] ?? null;
            $parameters = $item['parameters'] ?? [];

            if (empty($phone)) {
                $errorMessage = 'El teléfono es requerido';
                $results['failed'][] = [
                    'subscriptionId' => $subscriptionId,
                    'error' => $errorMessage,
                ];
                $results['failedCount']++;
            }

            $result = $this->sendTemplateByType(
                $template,
                $phone,
                $parameters,
                $customerApiId,
                $userId,
                $subscriptionId,
                $username,
                $errorMessage
            );

            if ($result['success']) {
                $results['success'][] = [
                    'subscriptionId' => $subscriptionId,
                    'messageId' => $result['messageId'],
                ];
                $results['successCount']++;
                $totalMessagesAtMonth++;
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
        ?string $username,
        ?string $errorMessage = null
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
                    $username,
                    $errorMessage
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
                    $username,
                    $errorMessage
                );

            case 'finalized':
            case 'finalized_subscription':
                return $this->sendFinalizedTemplate(
                    $phone,
                    $clubName,
                    $customerApiId,
                    $userId,
                    $subscriptionId,
                    $username,
                    $errorMessage
                );

            case 'last_day':
            case 'warning_last_day':
                return $this->sendLastDayTemplate(
                    $phone,
                    $clubName,
                    $customerApiId,
                    $userId,
                    $subscriptionId,
                    $username,
                    $errorMessage
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
        ?string $username,
        ?string $errorMessage = null
    ): array {
        $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);

        $result = $errorMessage !== null
        ? self::createResult(false, $errorMessage, null, 0, $subscriptionId)
        : $this->sendRequest($jsonBody);


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

    // ==================== PERFIL DEL NEGOCIO ====================

    /**
     * Sube una imagen de logo a WhatsApp y devuelve el handle
     * 
     * @param string $imagePath Ruta absoluta de la imagen
     * @return array ['success' => bool, 'handle' => string|null, 'error' => string|null]
     */
    private function uploadProfileImage(string $imagePath): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'handle' => null,
                'error' => 'WhatsApp no está configurado'
            ];
        }

        if (!file_exists($imagePath)) {
            return [
                'success' => false,
                'handle' => null,
                'error' => 'La imagen no existe'
            ];
        }

        $url = "{$this->apiUrl}/{$this->phoneNumberId}/media";

        $mimeType = mime_content_type($imagePath);
        $cFile = new \CURLFile($imagePath, $mimeType, basename($imagePath));

        $postData = [
            'file' => $cFile,
            'type' => $mimeType,
            'messaging_product' => 'whatsapp'
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'handle' => null,
                'error' => "Error de conexión: {$curlError}"
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'handle' => $responseData['id'] ?? null,
                'error' => null
            ];
        }

        $errorMessage = $responseData['error']['message'] ?? "HTTP {$httpCode}";
        return [
            'success' => false,
            'handle' => null,
            'error' => $errorMessage
        ];
    }

    /**
     * Actualiza el perfil del negocio de WhatsApp
     * 
     * @param array $profileData Datos del perfil. Campos disponibles:
     *   - about: string (256 chars max) - Descripción del negocio
     *   - address: string - Dirección física
     *   - description: string (512 chars max) - Descripción detallada
     *   - email: string - Email de contacto
     *   - profile_picture_handle: string - ID del media subido previamente
     *   - vertical: string - Industria (AUTO, BEAUTY, APPAREL, EDU, ENTERTAIN, EVENT_PLAN, FINANCE, GROCERY, GOVT, HOTEL, HEALTH, NONPROFIT, PROF_SERVICES, RETAIL, TRAVEL, RESTAURANT, NOT_A_BIZ)
     *   - websites: array - Lista de URLs
     * 
     * @return array ['success' => bool, 'response' => array|null, 'error' => string|null]
     */
    public function updateBusinessProfile(array $profileData): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'response' => null,
                'error' => 'WhatsApp no está configurado. Verifique WHATSAPP_PHONE_NUMBER_ID y WHATSAPP_ACCESS_TOKEN.'
            ];
        }

        $url = "{$this->apiUrl}/{$this->phoneNumberId}/whatsapp_business_profile";

        $payload = [
            'messaging_product' => 'whatsapp'
        ];

        // Agregar solo los campos proporcionados
        $allowedFields = ['about', 'address', 'description', 'email', 'profile_picture_handle', 'vertical', 'websites'];
        foreach ($allowedFields as $field) {
            if (isset($profileData[$field])) {
                $payload[$field] = $profileData[$field];
            }
        }

        $jsonBody = json_encode($payload);

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
            return [
                'success' => false,
                'response' => null,
                'error' => "Error de conexión: {$curlError}"
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'response' => $responseData,
                'error' => null
            ];
        }

        $errorMessage = $responseData['error']['message'] ?? "HTTP {$httpCode}: " . substr($response, 0, 400);
        return [
            'success' => false,
            'response' => $responseData,
            'error' => $errorMessage
        ];
    }

    /**
     * Registra o actualiza el perfil del negocio con nombre y logo
     * 
     * @param string $businessName Nombre del negocio que aparece en los mensajes
     * @param string|null $logoPath Ruta absoluta de la imagen del logo (opcional)
     * @param array $additionalData Datos adicionales del perfil (opcional)
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function registerBusinessProfile(
        string $businessName,
        ?string $logoPath = null,
        array $additionalData = []
    ): array {
        $profileData = [
            'about' => substr($businessName, 0, 256) // Límite de 256 caracteres
        ];

        // Si hay logo, subirlo primero
        if ($logoPath !== null && !empty($logoPath)) {
            $uploadResult = $this->uploadProfileImage($logoPath);
            
            if (!$uploadResult['success']) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Error al subir el logo: " . $uploadResult['error']
                ];
            }

            $profileData['profile_picture_handle'] = $uploadResult['handle'];
        }

        // Agregar datos adicionales
        $profileData = array_merge($profileData, $additionalData);

        // Actualizar perfil
        $result = $this->updateBusinessProfile($profileData);

        if ($result['success']) {
            return [
                'success' => true,
                'data' => [
                    'businessName' => $businessName,
                    'profileUpdated' => true,
                    'logoUploaded' => $logoPath !== null,
                    'response' => $result['response']
                ],
                'error' => null
            ];
        }

        return [
            'success' => false,
            'data' => null,
            'error' => $result['error']
        ];
    }

    /**
     * Obtiene el perfil actual del negocio
     * 
     * @return array ['success' => bool, 'profile' => array|null, 'error' => string|null]
     */
    public function getBusinessProfile(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'profile' => null,
                'error' => 'WhatsApp no está configurado'
            ];
        }

        $url = "{$this->apiUrl}/{$this->phoneNumberId}/whatsapp_business_profile?fields=about,address,description,email,profile_picture_url,websites,vertical";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'profile' => null,
                'error' => "Error de conexión: {$curlError}"
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'profile' => $responseData['data'][0] ?? null,
                'error' => null
            ];
        }

        $errorMessage = $responseData['error']['message'] ?? "HTTP {$httpCode}";
        return [
            'success' => false,
            'profile' => null,
            'error' => $errorMessage
        ];
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

    // ==================== REGISTRO DE NÚMERO ====================

    /**
     * Registra un número de teléfono en WhatsApp Business API
     * 
     * Este método hace un POST a /{phone-number-id}/register para completar
     * el registro del número y que aparezca como "connected" en Meta.
     * 
     * @param string $phoneNumberId ID del número de teléfono de WhatsApp
     * @param string $accessToken Token de acceso (si es diferente al global)
     * @param string|null $pin PIN de 6 dígitos para two-step verification (opcional)
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function registerPhoneNumber(string $phoneNumberId, string $accessToken, ?string $pin = null): array
    {
        $url = "{$this->apiUrl}/{$phoneNumberId}/register";

        $payload = [
            'messaging_product' => 'whatsapp'
        ];

        // Agregar PIN si se proporciona (para two-step verification)
        if ($pin !== null && strlen($pin) === 6) {
            $payload['pin'] = $pin;
        }

        $jsonBody = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
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
            return [
                'success' => false,
                'error' => "Error de conexión: {$curlError}",
                'httpCode' => 0
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'error' => null,
                'httpCode' => $httpCode,
                'response' => $responseData
            ];
        }

        $errorMessage = $responseData['error']['message'] ?? "HTTP {$httpCode}";
        return [
            'success' => false,
            'error' => $errorMessage,
            'httpCode' => $httpCode,
            'response' => $responseData
        ];
    }

    /**
     * Verifica el estado de un número de teléfono en WhatsApp
     * 
     * @param string $phoneNumberId ID del número de teléfono
     * @param string $accessToken Token de acceso
     * @return array ['success' => bool, 'status' => string|null, 'error' => string|null]
     */
    public function getPhoneNumberStatus(string $phoneNumberId, string $accessToken): array
    {
        $url = "{$this->apiUrl}/{$phoneNumberId}?fields=verified_name,code_verification_status,display_phone_number,quality_rating,platform_type,throughput,id";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'status' => null,
                'error' => "Error de conexión: {$curlError}"
            ];
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'status' => $responseData['code_verification_status'] ?? 'UNKNOWN',
                'data' => $responseData,
                'error' => null
            ];
        }

        $errorMessage = $responseData['error']['message'] ?? "HTTP {$httpCode}";
        return [
            'success' => false,
            'status' => null,
            'error' => $errorMessage
        ];
    }
}
