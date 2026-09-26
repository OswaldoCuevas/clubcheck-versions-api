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
    ];

    public function actions(): array
    {
        $actions = [];
        foreach (self::DEFINITIONS as $key => $definition) {
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
        $definition = ['action' => $action] + self::DEFINITIONS[$action];
        $definition['parameters'] = $this->normalizeParameters($action, $parameters);

        return $definition;
    }

    private function normalizeParameters(string $action, array $parameters): array
    {
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
}
