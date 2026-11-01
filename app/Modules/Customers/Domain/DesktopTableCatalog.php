<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

final class DesktopTableCatalog
{
    /** @var array<string, DesktopTableDefinition>|null */
    private ?array $definitions = null;

    /** @return array<string, array<string, string>> */
    public function all(): array
    {
        $tables = [];

        foreach ($this->definitions() as $key => $definition) {
            $tables[$key] = $definition->publicData();
        }

        return $tables;
    }

    public function find(string $key): ?DesktopTableDefinition
    {
        return $this->definitions()[$key] ?? null;
    }

    /** @return array<string, DesktopTableDefinition> */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $this->definitions = [
            'administrators' => $this->definition(
                'Administradores',
                'AdministratorsDesktop',
                'administrators',
                'fa-user-shield',
                'Gestión de administradores del sistema',
            ),
            'access-devices' => $this->definition(
                'Dispositivos de Acceso',
                'AccessDevicesDesktop',
                'accessDevices',
                'fa-door-open',
                'Dispositivos de control de acceso',
            ),
            'app-settings' => $this->definition(
                'Configuración de App',
                'AppSettingsDesktop',
                'appSettings',
                'fa-cog',
                'Configuración general de la aplicación',
            ),
            'attendances' => $this->definition(
                'Asistencias',
                'AttendancesDesktop',
                'attendances',
                'fa-calendar-check',
                'Registro de asistencias de usuarios',
            ),
            'barcode-cache' => $this->definition(
                'Cache de Códigos de Barras',
                'BarcodeLookupCacheDesktop',
                'barcodeLookupCache',
                'fa-barcode',
                'Cache de búsqueda de códigos de barras',
            ),
            'cash-register' => $this->definition(
                'Caja Registradora',
                'CashRegisterDesktop',
                'cashRegisters',
                'fa-cash-register',
                'Operaciones de caja registradora',
            ),
            'history-operations' => $this->definition(
                'Historial de Operaciones',
                'HistoryOperationsDesktop',
                'historyOperations',
                'fa-history',
                'Historial de todas las operaciones',
            ),
            'info-subscription' => $this->definition(
                'Info Suscripción',
                'InfoMySubscriptionDesktop',
                'infoMySubscription',
                'fa-info-circle',
                'Información de suscripciones',
            ),
            'migrations' => $this->definition(
                'Migraciones',
                'MigrationsDesktop',
                'migrations',
                'fa-database',
                'Control de migraciones de base de datos',
            ),
            'products' => $this->definition(
                'Productos',
                'ProductDesktop',
                'products',
                'fa-box',
                'Catálogo de productos',
            ),
            'product-prices' => $this->definition(
                'Precios de Productos',
                'ProductPriceDesktop',
                'productPrices',
                'fa-tags',
                'Precios de productos',
            ),
            'product-stock' => $this->definition(
                'Stock de Productos',
                'ProductStockDesktop',
                'productStock',
                'fa-boxes',
                'Control de inventario y stock',
            ),
            'operations-access-devices' => $this->definition(
                'Operaciones de Dispositivos',
                'OperationsAccessDevicesDesktop',
                'operationsAccessDevices',
                'fa-exchange-alt',
                'Operaciones enviadas a dispositivos de acceso',
            ),
            'sale-tickets' => $this->definition(
                'Tickets de Venta',
                'SaleTicketDesktop',
                'saleTickets',
                'fa-receipt',
                'Tickets de ventas realizadas',
            ),
            'sale-ticket-items' => $this->definition(
                'Items de Tickets',
                'SaleTicketItemDesktop',
                'saleTicketItems',
                'fa-list',
                'Líneas de detalle de tickets de venta',
            ),
            'send-emails-admin' => $this->definition(
                'Emails Admin',
                'SendEmailsAdminDesktop',
                'sendEmailsAdmin',
                'fa-envelope',
                'Emails administrativos enviados',
            ),
            'sent-messages' => $this->definition(
                'Mensajes Enviados',
                'SentMessagesDesktop',
                'sentMessages',
                'fa-paper-plane',
                'Mensajes enviados a clientes',
            ),
            'subscription-periods' => $this->definition(
                'Períodos de Suscripción',
                'SubscriptionPeriodDesktop',
                'subscriptionPeriods',
                'fa-calendar-alt',
                'Períodos de suscripciones',
            ),
            'subscriptions' => $this->definition(
                'Suscripciones',
                'SubscriptionsDesktop',
                'subscriptions',
                'fa-user-tag',
                'Suscripciones de clientes',
            ),
            'sync-status' => $this->definition(
                'Estado de Sincronización',
                'SyncStatusDesktop',
                'syncStatus',
                'fa-sync',
                'Estado de sincronización de datos',
            ),
            'users' => $this->definition(
                'Usuarios',
                'UsersDesktop',
                'users',
                'fa-users',
                'Usuarios del sistema',
            ),
            'user-access-devices' => $this->definition(
                'Usuarios y Dispositivos',
                'UserAccessDevicesDesktop',
                'userAccessDevices',
                'fa-id-card',
                'Relación de usuarios con dispositivos de acceso',
            ),
            'access-users' => $this->definition(
                'Usuarios de Acceso',
                'AccessUsersDesktop',
                'accessUsers',
                'fa-user-lock',
                'Usuarios sincronizados para control de acceso',
            ),
            'class-catalog' => $this->definition(
                'Catálogo de Clases',
                'ClassCatalogDesktop',
                'classCatalog',
                'fa-chalkboard',
                'Catálogo de clases disponibles',
            ),
            'instructors' => $this->definition(
                'Instructores',
                'InstructorsDesktop',
                'instructors',
                'fa-user-tie',
                'Instructores registrados',
            ),
            'class-instructors' => $this->definition(
                'Clases e Instructores',
                'ClassInstructorsDesktop',
                'classInstructors',
                'fa-user-graduate',
                'Relación entre clases e instructores',
            ),
            'class-schedule-groups' => $this->definition(
                'Grupos de Horarios',
                'ClassScheduleGroupsDesktop',
                'classScheduleGroups',
                'fa-layer-group',
                'Grupos de horarios de clases',
            ),
            'class-schedules' => $this->definition(
                'Horarios de Clases',
                'ClassSchedulesDesktop',
                'classSchedules',
                'fa-calendar-days',
                'Horarios programados para clases',
            ),
            'class-enrollments' => $this->definition(
                'Inscripciones a Clases',
                'ClassEnrollmentsDesktop',
                'classEnrollments',
                'fa-clipboard-list',
                'Inscripciones de usuarios a clases',
            ),
            'class-reservations' => $this->definition(
                'Reservaciones de Clases',
                'ClassReservationsDesktop',
                'classReservations',
                'fa-calendar-plus',
                'Reservaciones realizadas para clases',
            ),
            'class-schedule-instructors' => $this->definition(
                'Instructores por Horario',
                'ClassScheduleInstructorsDesktop',
                'classScheduleInstructors',
                'fa-people-arrows',
                'Asignación de instructores a horarios',
            ),
            'class-schedule-reschedules' => $this->definition(
                'Reprogramaciones',
                'ClassScheduleReschedulesDesktop',
                'classScheduleReschedules',
                'fa-calendar-week',
                'Cambios y reprogramaciones de horarios',
            ),
            'sale-classes' => $this->definition(
                'Ventas de Clases',
                'SaleClassesDesktop',
                'saleClasses',
                'fa-ticket',
                'Ventas asociadas a clases',
            ),
            'sale-class-schedules' => $this->definition(
                'Ventas por Horario',
                'SaleClassSchedulesDesktop',
                'saleClassSchedules',
                'fa-calendar-check',
                'Relación de ventas con horarios de clases',
            ),
            'whatsapp' => $this->definition(
                'WhatsApp',
                'WhatsappDesktop',
                'whatsapp',
                'fa-whatsapp',
                'Configuración y mensajes de WhatsApp',
            ),
        ];

        return $this->definitions;
    }

    private function definition(
        string $name,
        string $table,
        string $bulkName,
        string $icon,
        string $description,
    ): DesktopTableDefinition {
        return new DesktopTableDefinition(
            $name,
            $table,
            $bulkName,
            $icon,
            $description,
        );
    }
}
