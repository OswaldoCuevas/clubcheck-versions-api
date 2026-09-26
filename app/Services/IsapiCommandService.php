<?php

namespace App\Services;

/**
 * Lista controlada de operaciones ISAPI permitidas desde el servidor.
 *
 * Normalmente el servidor solo conoce comandos semanticos. El proxy temporal
 * puede transportar una ruta relativa; host y credenciales siempre son locales.
 */
class IsapiCommandService
{
    private const DEFINITIONS = [
        'test_proxy_request' => [
            'label' => 'Proxy ISAPI temporal',
            'description' => 'Envia una solicitud de prueba a una ruta relativa ISAPI mediante el desktop.',
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
            'description' => 'Solicita eventos recientes de acceso o asistencia.',
            'defaultParameters' => [
                'page' => 1,
                'pageSize' => 50,
                'offset' => 0,
                'cursor' => null,
                'includeTotal' => true,
                'from' => null,
                'to' => null,
            ],
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

        if ($action === 'network_ping') {
            return [
                'timeoutMs' => $this->integer($parameters['timeoutMs'] ?? 2000, 250, 10000, 'timeoutMs'),
                'attempts' => $this->integer($parameters['attempts'] ?? 2, 1, 5, 'attempts'),
            ];
        }

        if (!in_array($action, ['get_registered_members', 'get_recent_activity'], true)) {
            return [];
        }

        $page = $this->integer($parameters['page'] ?? 1, 1, 1000000, 'page');
        $pageSize = $this->integer($parameters['pageSize'] ?? 50, 1, 100, 'pageSize');
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
            throw new \InvalidArgumentException('La ruta debe ser relativa, comenzar con /ISAPI/ y no contener host ni segmentos inseguros.');
        }
        $urlParts = parse_url($path);
        if ($urlParts === false || isset($urlParts['scheme']) || isset($urlParts['host']) ||
            isset($urlParts['user']) || isset($urlParts['pass']) || isset($urlParts['fragment'])) {
            throw new \InvalidArgumentException('La ruta ISAPI relativa no es valida.');
        }

        $contentType = strtolower(trim((string) ($parameters['contentType'] ?? 'application/json')));
        $allowedContentTypes = ['application/json', 'application/xml', 'text/xml'];
        if (!in_array($contentType, $allowedContentTypes, true)) {
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
