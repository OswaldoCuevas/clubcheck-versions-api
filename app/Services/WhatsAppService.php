<?php

namespace App\Services;

require_once __DIR__ . '/../Models/MessageSentModel.php';
require_once __DIR__ . '/../Models/WhatsAppConfigurationModel.php';
require_once __DIR__ . '/../Models/WhatsAppTemplateModel.php';
require_once __DIR__ . '/../Models/CustomerRegistryModel.php';
require_once __DIR__ . '/../Models/ApplicationModel.php';
require_once __DIR__ . '/../enums/WhatsAppEvent.php';
require_once __DIR__ . '/WhatsApp/WhatsAppTemplateStrategyInterface.php';
require_once __DIR__ . '/WhatsApp/WhatsAppTemplateComponentBuilder.php';
require_once __DIR__ . '/WhatsApp/DefaultWhatsAppTemplateStrategy.php';
require_once __DIR__ . '/WhatsApp/CustomerWhatsAppTemplateStrategy.php';
require_once __DIR__ . '/../../utils/CustomerPermits.php';
require_once __DIR__ . '/../../utils/GlobalFunctions.php';

use App\Enums\WhatsAppEvent;
use App\Services\WhatsApp\CustomerWhatsAppTemplateStrategy;
use App\Services\WhatsApp\DefaultWhatsAppTemplateStrategy;
use App\Services\WhatsApp\WhatsAppTemplateComponentBuilder;
use App\Services\WhatsApp\WhatsAppTemplateStrategyInterface;
use Models\MessageSentModel;
use Models\WhatsAppConfigurationModel;
use Models\WhatsAppTemplateModel;
use Models\CustomerRegistryModel;
use Models\ApplicationModel;
use CustomerPermits;
use GlobalFunctions;

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
    private WhatsAppTemplateModel $templateModel;
    private WhatsAppTemplateComponentBuilder $componentBuilder;
    private WhatsAppTemplateStrategyInterface $templateStrategy;
    private ?string $customerId;
    private string $clubName = 'tu club';

    /**
     * Constructor
     * 
     * @param string|null $customerId ID del customer (customerApiId o customerId). 
     *                                Si se proporciona, intenta usar su configuración personalizada.
     *                                Si no existe o está vacía, usa la configuración global.
     */
    public function __construct(?string $customerId = null)
    {
        $appModel = new ApplicationModel();
        $appId = !empty($customerId)
            ? $appModel->getCustomerAppId($customerId)
            : $appModel->getSelectedApp()['id'];

        // Primero se cargan credenciales por app; si estan vacias, ApplicationModel cae al .env actual.
        $this->config = $appModel->getWhatsappConfig($appId);
        $this->customerId = $customerId;

        $this->apiUrl = $this->config['api_url'];
        $this->phoneNumberId = $this->config['phone_number_id'];
        $this->accessToken = $this->config['access_token'];

        $this->messageSentModel = new MessageSentModel();
        $this->templateModel = new WhatsAppTemplateModel();
        $this->componentBuilder = new WhatsAppTemplateComponentBuilder();

        // Estrategia base: si no hay configuracion completa del customer,
        // los eventos se envian con templates y credenciales globales.
        $this->templateStrategy = new DefaultWhatsAppTemplateStrategy($this->componentBuilder);

        if (!empty($customerId)) {
            $this->loadCustomerConfig($customerId);
        }
    }

    /**
     * Carga la configuración específica de un customer si existe
     */
    private function loadCustomerConfig(string $customerId): void
    {
        try {
            $customerModel = new CustomerRegistryModel();
            $customer = $customerModel->getCustomer($customerId);
            if ($customer && !empty($customer['name'])) {
                $this->clubName = $customer['name'];
            }

            $configModel = new WhatsAppConfigurationModel();
            $customerConfig = $configModel->findByCustomerId($customerId);
            
            if ($customerConfig && !empty($customerConfig['PhoneNumberId']) && !empty($customerConfig['AccessToken'])) {
                $this->phoneNumberId = $customerConfig['PhoneNumberId'];
                $this->accessToken = $customerConfig['AccessToken'];

                // Estrategia customer: intenta usar el template relacionado al evento.
                // Si no existe, la propia estrategia cae a DefaultWhatsAppTemplateStrategy.
                $this->templateStrategy = new CustomerWhatsAppTemplateStrategy(
                    $this->templateModel,
                    new DefaultWhatsAppTemplateStrategy($this->componentBuilder),
                    $this->componentBuilder
                );
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

    /**
     * Valida que un número de teléfono tenga un formato válido
     * 
     * @param string $phone Número de teléfono a validar
     * @return bool True si el teléfono es válido, false si no
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        // Remover espacios y caracteres especiales
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        // Validar que solo contenga dígitos después de la limpieza
        if (empty($cleanPhone) || !ctype_digit($cleanPhone)) {
            return false;
        }

        // Validar longitud (mínimo 10 dígitos, máximo 15 para formato internacional)
        $length = strlen($cleanPhone);
        if ($length < 10 || $length > 15) {
            return false;
        }

        return true;
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

    private function sendTemplateForEvent(
        WhatsAppEvent $event,
        string $phone,
        array $parameters,
        array $defaultTemplate,
        array $defaultComponents,
        string $description,
        string $invalidPhoneDescription,
        string $customerApiId,
        ?string $userId,
        ?string $subscriptionId,
        ?string $username,
        ?string $errorMessage = null
    ): array {
        if (!$this->isValidPhoneNumber($phone)) {
            $result = self::createResult(false, 'El número de teléfono no tiene un formato válido.', null, 0, $subscriptionId);
            $this->logMessage($customerApiId, $userId, $username, $phone, $invalidPhoneDescription, $result);
            return $result;
        }

        $phone = $this->normalizePhone($phone);
        $parameters['phone'] = $phone;
        $parameters['username'] = $username ?? ($parameters['username'] ?? '');

        // La estrategia decide que template usar y con que credenciales enviar:
        // customer si hay template personalizado; default si no aplica.
        $message = $this->resolveTemplateMessage($event, $parameters, $defaultTemplate, $defaultComponents, $customerApiId);

        if (!empty($message['errorMessage'])) {
            $result = self::createResult(false, $message['errorMessage'], null, 0, $subscriptionId);
            $this->logMessage(
                $customerApiId,
                $userId,
                $username,
                $phone,
                $message['description'] ?? $description,
                $result
            );
            return $result;
        }

        $body = $this->buildTemplatePayload(
            $phone,
            $message['name'],
            $message['language'],
            $message['components']
        );

        return $this->sendWithStrategy(
            $message['strategy'],
            $body,
            $phone,
            $message['description'] ?? $description,
            $customerApiId,
            $userId,
            $subscriptionId,
            $username,
            $errorMessage
        );
    }

    private function resolveTemplateMessage(
        WhatsAppEvent $event,
        array $parameters,
        array $defaultTemplate,
        array $defaultComponents,
        string $customerApiId
    ): array {
        // Delegacion pura al contrato de Strategy; aqui no se pregunta si es
        // customer o default, porque esa decision vive en la implementacion.
        return $this->templateStrategy->resolveTemplateMessage(
            $event,
            $parameters,
            $defaultTemplate,
            $defaultComponents,
            $customerApiId
        );
    }

    private function buildTemplatePayload(string $phone, string $templateName, string $language, array $components = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return $payload;
    }

    private function sendWithStrategy(
        string $strategy,
        array $body,
        string $phone,
        string $description,
        string $customerApiId,
        ?string $userId,
        ?string $subscriptionId,
        ?string $username,
        ?string $errorMessage = null
    ): array {
        $currentPhoneNumberId = $this->phoneNumberId;
        $currentAccessToken = $this->accessToken;

        // El mensaje puede resolverse como default aunque el servicio haya
        // cargado credenciales del customer, por eso se seleccionan aqui.
        $credentials = [
            'default' => [
                'phoneNumberId' => $this->config['phone_number_id'],
                'accessToken' => $this->config['access_token'],
            ],
            'customer' => [
                'phoneNumberId' => $this->phoneNumberId,
                'accessToken' => $this->accessToken,
            ],
        ][$strategy] ?? [
            'phoneNumberId' => $this->config['phone_number_id'],
            'accessToken' => $this->config['access_token'],
        ];

        $this->phoneNumberId = $credentials['phoneNumberId'];
        $this->accessToken = $credentials['accessToken'];

        try {
            return $this->sendAndLog($body, $phone, $description, $customerApiId, $userId, $subscriptionId, $username, $errorMessage);
        } finally {
            $this->phoneNumberId = $currentPhoneNumberId;
            $this->accessToken = $currentAccessToken;
        }
    }

    /**
     * Envía un template de nueva suscripción (bienvenida)
     * 
     * Template: subscription
     * Parámetros: nombre, club, fecha inicio, fecha fin
     */
    public function sendSubscriptionTemplate(
        string $phone,
        string $firstName,
        string $startDate,
        string $endDate,
        string $customerApiId,
        ?string $userId = null,
        ?string $subscriptionId = null,
        ?string $username = null,
        ?string $errorMessage = null
    ): array {
        $clubName = $this->clubName;
        $templateConfig = $this->config['templates']['subscription'];
        $parameters = compact('firstName', 'clubName', 'startDate', 'endDate');

        return $this->sendTemplateForEvent(
            WhatsAppEvent::SUBSCRIPTION_CREATED,
            $phone,
            $parameters,
            $templateConfig,
            [
                ['type' => 'header', 'variables' => ['clubName'], 'format' => 'name', 'suffix' => ':'],
                ['type' => 'body', 'variables' => ['firstName', 'startDate', 'endDate'], 'formats' => ['firstName' => 'name']],
            ],
            "Bienvenida de membresia: {$startDate} - {$endDate}",
            "Bienvenida de membresia (telefono invalido): {$startDate} - {$endDate}",
            $customerApiId,
            $userId,
            $subscriptionId,
            $username,
            $errorMessage
        );
    }

    /**
     * Envía un template de advertencia de vencimiento próximo
     * 
     * Template: warning_subscription
     * Parámetros: club, días restantes
     */
    public function sendWarningTemplate(
        string $phone,
        string $days,
        string $customerApiId,
        ?string $userId = null,
        ?string $subscriptionId = null,
        ?string $username = null,
        ?string $errorMessage = null
    ): array {
        $clubName = $this->clubName;
        $templateConfig = $this->config['templates']['warning_subscription'];
        $parameters = compact('clubName', 'days');

        return $this->sendTemplateForEvent(
            WhatsAppEvent::SUBSCRIPTION_WARNING,
            $phone,
            $parameters,
            $templateConfig,
            [
                ['type' => 'header', 'variables' => ['clubName'], 'format' => 'name', 'suffix' => ':'],
                ['type' => 'body', 'variables' => ['days'], 'format' => 'text'],
            ],
            "Aviso de membresia: vence en {$days}. Club: " . GlobalFunctions::FormatName($clubName),
            "Aviso de membresia (telefono invalido): vence en {$days}",
            $customerApiId,
            $userId,
            $subscriptionId,
            $username,
            $errorMessage
        );
    }

    /**
     * Envía un template de membresía finalizada
     * 
     * Template: finalized_subscription
     * Parámetros: club
     */
    public function sendFinalizedTemplate(
        string $phone,
        string $customerApiId,
        ?string $userId = null,
        ?string $subscriptionId = null,
        ?string $username = null,
        ?string $errorMessage = null
    ): array {
        $clubName = $this->clubName;
        $templateConfig = $this->config['templates']['finalized_subscription'];
        $parameters = compact('clubName');

        return $this->sendTemplateForEvent(
            WhatsAppEvent::SUBSCRIPTION_FINALIZED,
            $phone,
            $parameters,
            $templateConfig,
            [
                ['type' => 'header', 'variables' => ['clubName'], 'format' => 'text', 'suffix' => ':'],
            ],
            "Aviso de membresia finalizada. Club: " . GlobalFunctions::FormatName($clubName),
            'Aviso de membresia finalizada (telefono invalido)',
            $customerApiId,
            $userId,
            $subscriptionId,
            $username,
            $errorMessage
        );
    }

    /**
     * Envía un template de último día
     * 
     * Template: warning_last_day
     * Sin parámetros adicionales
     */
    public function sendLastDayTemplate(
        string $phone,
        string $customerApiId,
        ?string $userId = null,
        ?string $subscriptionId = null,
        ?string $username = null,
        ?string $errorMessage = null
    ): array {
        $clubName = $this->clubName;
        $templateConfig = $this->config['templates']['warning_last_day'];
        $parameters = compact('clubName');

        return $this->sendTemplateForEvent(
            WhatsAppEvent::SUBSCRIPTION_LAST_DAY,
            $phone,
            $parameters,
            $templateConfig,
            [
                ['type' => 'header', 'variables' => ['clubName'], 'format' => 'text', 'suffix' => ':'],
            ],
            "Aviso de membresia: ultimo dia. Club: " . GlobalFunctions::FormatName($clubName),
            'Aviso de ultimo dia (telefono invalido)',
            $customerApiId,
            $userId,
            $subscriptionId,
            $username,
            $errorMessage
        );
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
     * - parameters: array (days, startDate, endDate, firstName)
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

        foreach ($bulkItems as $item) {
            $subscriptionId = $item['subscriptionId'] ?? null;
            $errorMessage = null;

            if (empty($subscriptionId)) {
                $errorMessage = 'subscriptionId es requerido';
                $results['failed'][] = [
                    'subscriptionId' => null,
                    'error' => $errorMessage,
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
                $errorMessage = 'El teléfono es requerido';
                $results['failed'][] = [
                    'subscriptionId' => $subscriptionId,
                    'error' => $errorMessage,
                ];
                $results['failedCount']++;
                continue;
            }

            if (WhatsAppEvent::fromTemplateType($template) === null) {
                $results['failed'][] = [
                    'subscriptionId' => $subscriptionId,
                    'error' => "Template desconocido: {$template}",
                ];
                $results['failedCount']++;
                continue;
            }

            try {
                $customerPermits = new CustomerPermits($customerApiId);
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
                continue;
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
        $event = WhatsAppEvent::fromTemplateType($template);
        $strategies = [
            WhatsAppEvent::SUBSCRIPTION_CREATED->value => fn () => $this->sendSubscriptionTemplate(
                $phone,
                $parameters['firstName'] ?? 'Cliente',
                $parameters['startDate'] ?? '',
                $parameters['endDate'] ?? '',
                $customerApiId,
                $userId,
                $subscriptionId,
                $username,
                $errorMessage
            ),
            WhatsAppEvent::SUBSCRIPTION_WARNING->value => fn () => (($parameters['days'] ?? null) == 1)
                ? $this->sendLastDayTemplate($phone, $customerApiId, $userId, $subscriptionId, $username, $errorMessage)
                : $this->sendWarningTemplate($phone, $parameters['days'] ?? '3', $customerApiId, $userId, $subscriptionId, $username, $errorMessage),
            WhatsAppEvent::SUBSCRIPTION_FINALIZED->value => fn () => $this->sendFinalizedTemplate(
                $phone,
                $customerApiId,
                $userId,
                $subscriptionId,
                $username,
                $errorMessage
            ),
            WhatsAppEvent::SUBSCRIPTION_LAST_DAY->value => fn () => $this->sendLastDayTemplate(
                $phone,
                $customerApiId,
                $userId,
                $subscriptionId,
                $username,
                $errorMessage
            ),
        ];

        if (!$event || !isset($strategies[$event->value])) {
            return self::createResult(false, "Template desconocido: {$template}", null, 0, $subscriptionId);
        }

        return $strategies[$event->value]();
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
                'Id' => GlobalFunctions::generateUuid(),
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
