<?php

namespace App\Services;

/**
 * Lista controlada de operaciones ISAPI permitidas desde el servidor.
 *
 * El servidor solo conoce comandos semanticos. El cliente de escritorio decide
 * la ruta, metodo y credenciales ISAPI correspondientes a cada uno.
 */
class IsapiCommandService
{
    private const DEFINITIONS = [
        'test_proxy_request' => [
            'label' => 'Proxy ISAPI temporal',
            'description' => 'Envia una solicitud de prueba a una ruta relativa /ISAPI/... mediante el desktop.',
            'defaultParameters' => [
                'method' => 'GET',
                'path' => '/ISAPI/System/status',
                'contentType' => 'application/xml',
                'body' => null,
            ],
        ],
        'network_ping' => [
            'label' => 'Ping de red',
            'description' => 'Comprueba conectividad ICMP sin utilizar credenciales de la terminal.',
            'defaultParameters' => [
                'timeoutMs' => 2000,
                'attempts' => 2,
            ],
        ],
        'device_status' => [
            'label' => 'Estado del dispositivo',
            'description' => 'Prueba conectividad y obtiene el estado general.',
            'defaultParameters' => [],
        ],
        'device_info' => [
            'label' => 'Informacion del dispositivo',
            'description' => 'Modelo, serie, firmware y datos de identificacion disponibles.',
            'defaultParameters' => [],
        ],
        'system_capabilities' => [
            'label' => 'Capacidades del sistema',
            'description' => 'Descubre funciones ISAPI admitidas por el modelo y firmware.',
            'defaultParameters' => [],
        ],
        'access_capabilities' => [
            'label' => 'Capacidades de control de acceso',
            'description' => 'Descubre funciones disponibles para acceso, usuarios y eventos.',
            'defaultParameters' => [],
        ],
        'get_system_time' => [
            'label' => 'Hora de la terminal',
            'description' => 'GET /ISAPI/System/time - hora local, zona horaria y modo de sincronizacion.',
            'defaultParameters' => [],
        ],
        'get_card_reader_config' => [
            'label' => 'Configuracion del lector',
            'description' => 'GET /ISAPI/AccessControl/CardReaderCfg/{readerNo}?format=json.',
            'defaultParameters' => [
                'readerNo' => 1,
            ],
        ],
        'get_identity_terminal_config' => [
            'label' => 'Identidad y umbrales',
            'description' => 'GET /ISAPI/AccessControl/IdentityTerminal - configuracion facial y umbrales.',
            'defaultParameters' => [],
        ],
        'get_registered_members' => [
            'label' => 'Socios registrados',
            'description' => 'Solicita al cliente los socios registrados en la terminal.',
            'defaultParameters' => [
                'page' => 1,
                'pageSize' => 50,
                'offset' => 0,
                'cursor' => null,
                'includeTotal' => true,
            ],
        ],
        'get_recent_activity' => [
            'label' => 'Actividad reciente',
            'description' => 'POST /ISAPI/AccessControl/AcsEvent?format=json - eventos de acceso paginados.',
            'defaultParameters' => [
                'page' => 1,
                'pageSize' => 30,
                'offset' => 0,
                'cursor' => null,
                'includeTotal' => true,
                'from' => null,
                'to' => null,
                'major' => 0,
                'minor' => 0,
                'searchId' => null,
            ],
        ],
        'get_event_picture' => [
            'label' => 'Captura de evento',
            'description' => 'Descarga una captura de acceso de la terminal seleccionada.',
            'defaultParameters' => ['picturePath' => ''],
        ],
    ];

    public function actions(): array
    {
        $actions = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            if ($key === 'test_proxy_request' && !$this->testProxyEnabled()) {
                continue;
            }
            $actions[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'defaultParameters' => $definition['defaultParameters'],
            ];
        }

        return $actions;
    }

    public function definition(string $action, array $parameters = []): ?array
    {
        $action = trim($action);
        if (!isset(self::DEFINITIONS[$action])) {
            return null;
        }
        if ($action === 'test_proxy_request' && !$this->testProxyEnabled()) {
            return null;
        }
        $definition = ['action' => $action] + self::DEFINITIONS[$action];
        $definition['parameters'] = $this->normalizeParameters($action, $parameters);

        return $definition;
    }

    private function normalizeParameters(string $action, array $parameters): array
    {
        if ($action === 'test_proxy_request') {
            return $this->normalizeTestProxyParameters($parameters);
        }

        if ($action === 'get_event_picture') {
            $path = $parameters['picturePath'] ?? null;
            if (!is_string($path) || strlen($path) > 1024
                || !preg_match('~^/LOCALS/pic/acsLinkCap/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:jpe?g|png)(?:@WEB[A-Za-z0-9_-]+)?$~D', $path)) {
                throw new \InvalidArgumentException('picturePath debe ser una ruta relativa de captura /LOCALS/pic/acsLinkCap/ valida.');
            }
            return ['picturePath' => $path];
        }

        if ($action === 'network_ping') {
            return [
                'timeoutMs' => $this->integer($parameters['timeoutMs'] ?? 2000, 250, 10000, 'timeoutMs'),
                'attempts' => $this->integer($parameters['attempts'] ?? 2, 1, 5, 'attempts'),
            ];
        }

        if ($action === 'get_card_reader_config') {
            return [
                'readerNo' => $this->integer($parameters['readerNo'] ?? 1, 1, 255, 'readerNo'),
            ];
        }

        if (!in_array($action, ['get_registered_members', 'get_recent_activity'], true)) {
            return [];
        }

        $page = $this->integer($parameters['page'] ?? 1, 1, 1000000, 'page');
        $maxPageSize = $action === 'get_recent_activity' ? 30 : 100;
        $defaultPageSize = $action === 'get_recent_activity' ? 30 : 50;
        $pageSize = $this->integer($parameters['pageSize'] ?? $defaultPageSize, 1, $maxPageSize, 'pageSize');
        $cursor = isset($parameters['cursor']) ? trim((string) $parameters['cursor']) : '';
        if (mb_strlen($cursor) > 200) {
            throw new \InvalidArgumentException('cursor excede 200 caracteres.');
        }

        $normalized = [
            'page' => $page,
            'pageSize' => $pageSize,
            'offset' => ($page - 1) * $pageSize,
            'cursor' => $cursor !== '' ? $cursor : null,
            'includeTotal' => filter_var(
                $parameters['includeTotal'] ?? true,
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE
            ) ?? true,
        ];

        if ($action === 'get_recent_activity') {
            $to = $this->dateTime($parameters['to'] ?? null, new \DateTimeImmutable('now'));
            $from = $this->dateTime($parameters['from'] ?? null, $to->modify('-24 hours'));
            if ($from > $to) {
                throw new \InvalidArgumentException('from no puede ser posterior a to.');
            }
            if (($to->getTimestamp() - $from->getTimestamp()) > 31 * 86400) {
                throw new \InvalidArgumentException('El rango de actividad no puede exceder 31 dias.');
            }
            $normalized['from'] = $this->isoDateWithTimezone($from);
            $normalized['to'] = $this->isoDateWithTimezone($to);
            $normalized['major'] = $this->integer($parameters['major'] ?? 0, 0, 2147483647, 'major');
            $normalized['minor'] = $this->integer($parameters['minor'] ?? 0, 0, 2147483647, 'minor');
            $normalized['searchId'] = 'cc-' . substr(hash('sha256', implode('|', [
                trim($normalized['from']),
                trim($normalized['to']),
                (string) $normalized['major'],
                (string) $normalized['minor'],
            ])), 0, 24);
        }

        return $normalized;
    }

    private function integer($value, int $min, int $max, string $name): int
    {
        $result = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);
        if ($result === false) {
            throw new \InvalidArgumentException("{$name} debe ser un entero entre {$min} y {$max}.");
        }
        return $result;
    }

    private function dateTime($value, \DateTimeImmutable $default): \DateTimeImmutable
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return $default;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Fecha de paginacion invalida.');
        }
    }

    /** ISO 8601 with seconds and an explicit UTC offset, without fractions. */
    private function isoDateWithTimezone(\DateTimeImmutable $value): string
    {
        // Temporary desktop compatibility: its current parser expects a trailing space.
        return $value->format('Y-m-d\TH:i:sP') . ' ';
    }

    private function normalizeTestProxyParameters(array $parameters): array
    {
        if (!$this->testProxyEnabled()) {
            throw new \InvalidArgumentException('El proxy ISAPI temporal esta deshabilitado.');
        }

        $method = strtoupper(trim((string) ($parameters['method'] ?? 'GET')));
        $allowedMethods = ['GET', 'POST', 'PUT'];
        if ((bool) config('isapi.test_proxy_allow_delete', false)) {
            $allowedMethods[] = 'DELETE';
        }
        if (!in_array($method, $allowedMethods, true)) {
            throw new \InvalidArgumentException('Metodo no permitido por el proxy temporal.');
        }

        $path = trim((string) ($parameters['path'] ?? ''));
        if ($path === '' || mb_strlen($path) > 1000) {
            throw new \InvalidArgumentException('La ruta relativa ISAPI es obligatoria y no puede exceder 1000 caracteres.');
        }
        $decodedPath = rawurldecode($path);
        if (!str_starts_with($path, '/ISAPI/') ||
            str_contains($decodedPath, '://') ||
            str_contains($decodedPath, '..') ||
            str_contains($decodedPath, '\\') ||
            preg_match('/[\r\n\x00]/', $decodedPath)) {
            throw new \InvalidArgumentException('La ruta debe comenzar con /ISAPI/ y no contener host ni segmentos inseguros.');
        }
        $urlParts = parse_url($path);
        if ($urlParts === false || isset($urlParts['scheme']) || isset($urlParts['host']) ||
            isset($urlParts['user']) || isset($urlParts['pass']) || isset($urlParts['fragment'])) {
            throw new \InvalidArgumentException('La ruta ISAPI relativa no es valida.');
        }

        $contentType = strtolower(trim((string) ($parameters['contentType'] ?? 'application/xml')));
        if (!in_array($contentType, ['application/json', 'application/xml', 'text/xml'], true)) {
            throw new \InvalidArgumentException('contentType debe ser application/json, application/xml o text/xml.');
        }

        $body = $parameters['body'] ?? null;
        if ($body !== null && !is_string($body)) {
            throw new \InvalidArgumentException('El body del proxy debe ser texto JSON o XML.');
        }
        $maxBodyBytes = (int) config('isapi.test_proxy_max_body_bytes', 262144);
        if ($body !== null && strlen($body) > $maxBodyBytes) {
            throw new \InvalidArgumentException('El body excede el limite temporal de 256 KB.');
        }
        if ($method === 'GET') {
            $body = null;
        }

        return [
            'method' => $method,
            'path' => $path,
            'contentType' => $contentType,
            'body' => $body !== '' ? $body : null,
        ];
    }

    private function testProxyEnabled(): bool
    {
        return (bool) config('isapi.test_proxy_enabled', false);
    }
}
