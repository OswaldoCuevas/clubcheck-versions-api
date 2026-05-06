<?php

namespace Controllers;

use Core\Controller;
use Models\CustomerRegistryModel;

require_once __DIR__ . '/../Core/Controller.php';
require_once __DIR__ . '/../Models/CustomerRegistryModel.php';

// Modelos Desktop
require_once __DIR__ . '/../Models/AdministratorsDesktopModel.php';
require_once __DIR__ . '/../Models/AppSettingsDesktopModel.php';
require_once __DIR__ . '/../Models/AttendancesDesktopModel.php';
require_once __DIR__ . '/../Models/BarcodeLookupCacheDesktopModel.php';
require_once __DIR__ . '/../Models/CashRegisterDesktopModel.php';
require_once __DIR__ . '/../Models/HistoryOperationsDesktopModel.php';
require_once __DIR__ . '/../Models/InfoMySubscriptionDesktopModel.php';
require_once __DIR__ . '/../Models/MigrationsDesktopModel.php';
require_once __DIR__ . '/../Models/ProductDesktopModel.php';
require_once __DIR__ . '/../Models/ProductPriceDesktopModel.php';
require_once __DIR__ . '/../Models/ProductStockDesktopModel.php';
require_once __DIR__ . '/../Models/SaleTicketDesktopModel.php';
require_once __DIR__ . '/../Models/SaleTicketItemDesktopModel.php';
require_once __DIR__ . '/../Models/SendEmailsAdminDesktopModel.php';
require_once __DIR__ . '/../Models/SentMessagesDesktopModel.php';
require_once __DIR__ . '/../Models/SubscriptionPeriodDesktopModel.php';
require_once __DIR__ . '/../Models/SubscriptionsDesktopModel.php';
require_once __DIR__ . '/../Models/SyncStatusDesktopModel.php';
require_once __DIR__ . '/../Models/UsersDesktopModel.php';
require_once __DIR__ . '/../Models/WhatsAppDesktopModel.php';

use Models\AdministratorsDesktopModel;
use Models\AppSettingsDesktopModel;
use Models\AttendancesDesktopModel;
use Models\BarcodeLookupCacheDesktopModel;
use Models\CashRegisterDesktopModel;
use Models\HistoryOperationsDesktopModel;
use Models\InfoMySubscriptionDesktopModel;
use Models\MigrationsDesktopModel;
use Models\ProductDesktopModel;
use Models\ProductPriceDesktopModel;
use Models\ProductStockDesktopModel;
use Models\SaleTicketDesktopModel;
use Models\SaleTicketItemDesktopModel;
use Models\SendEmailsAdminDesktopModel;
use Models\SentMessagesDesktopModel;
use Models\SubscriptionPeriodDesktopModel;
use Models\SubscriptionsDesktopModel;
use Models\SyncStatusDesktopModel;
use Models\UsersDesktopModel;
use Models\WhatsAppDesktopModel;

class DesktopTablesController extends Controller
{
    private array $desktopTables = [];

    public function __construct()
    {
        parent::__construct();
        $this->initializeDesktopTables();
    }

    /**
     * Inicializa el array de tablas desktop disponibles con sus modelos
     */
    private function initializeDesktopTables(): void
    {
        $this->desktopTables = [
            'administrators' => [
                'name' => 'Administradores',
                'table' => 'AdministratorsDesktop',
                'model' => AdministratorsDesktopModel::class,
                'icon' => 'fa-user-shield',
                'description' => 'Gestión de administradores del sistema',
            ],
            'app-settings' => [
                'name' => 'Configuración de App',
                'table' => 'AppSettingsDesktop',
                'model' => AppSettingsDesktopModel::class,
                'icon' => 'fa-cog',
                'description' => 'Configuración general de la aplicación',
            ],
            'attendances' => [
                'name' => 'Asistencias',
                'table' => 'AttendancesDesktop',
                'model' => AttendancesDesktopModel::class,
                'icon' => 'fa-calendar-check',
                'description' => 'Registro de asistencias de usuarios',
            ],
            'barcode-cache' => [
                'name' => 'Cache de Códigos de Barras',
                'table' => 'BarcodeLookupCacheDesktop',
                'model' => BarcodeLookupCacheDesktopModel::class,
                'icon' => 'fa-barcode',
                'description' => 'Cache de búsqueda de códigos de barras',
            ],
            'cash-register' => [
                'name' => 'Caja Registradora',
                'table' => 'CashRegisterDesktop',
                'model' => CashRegisterDesktopModel::class,
                'icon' => 'fa-cash-register',
                'description' => 'Operaciones de caja registradora',
            ],
            'history-operations' => [
                'name' => 'Historial de Operaciones',
                'table' => 'HistoryOperationsDesktop',
                'model' => HistoryOperationsDesktopModel::class,
                'icon' => 'fa-history',
                'description' => 'Historial de todas las operaciones',
            ],
            'info-subscription' => [
                'name' => 'Info Suscripción',
                'table' => 'InfoMySubscriptionDesktop',
                'model' => InfoMySubscriptionDesktopModel::class,
                'icon' => 'fa-info-circle',
                'description' => 'Información de suscripciones',
            ],
            'migrations' => [
                'name' => 'Migraciones',
                'table' => 'MigrationsDesktop',
                'model' => MigrationsDesktopModel::class,
                'icon' => 'fa-database',
                'description' => 'Control de migraciones de base de datos',
            ],
            'products' => [
                'name' => 'Productos',
                'table' => 'ProductDesktop',
                'model' => ProductDesktopModel::class,
                'icon' => 'fa-box',
                'description' => 'Catálogo de productos',
            ],
            'product-prices' => [
                'name' => 'Precios de Productos',
                'table' => 'ProductPriceDesktop',
                'model' => ProductPriceDesktopModel::class,
                'icon' => 'fa-tags',
                'description' => 'Precios de productos',
            ],
            'product-stock' => [
                'name' => 'Stock de Productos',
                'table' => 'ProductStockDesktop',
                'model' => ProductStockDesktopModel::class,
                'icon' => 'fa-boxes',
                'description' => 'Control de inventario y stock',
            ],
            'sale-tickets' => [
                'name' => 'Tickets de Venta',
                'table' => 'SaleTicketDesktop',
                'model' => SaleTicketDesktopModel::class,
                'icon' => 'fa-receipt',
                'description' => 'Tickets de ventas realizadas',
            ],
            'sale-ticket-items' => [
                'name' => 'Items de Tickets',
                'table' => 'SaleTicketItemDesktop',
                'model' => SaleTicketItemDesktopModel::class,
                'icon' => 'fa-list',
                'description' => 'Líneas de detalle de tickets de venta',
            ],
            'send-emails-admin' => [
                'name' => 'Emails Admin',
                'table' => 'SendEmailsAdminDesktop',
                'model' => SendEmailsAdminDesktopModel::class,
                'icon' => 'fa-envelope',
                'description' => 'Emails administrativos enviados',
            ],
            'sent-messages' => [
                'name' => 'Mensajes Enviados',
                'table' => 'SentMessagesDesktop',
                'model' => SentMessagesDesktopModel::class,
                'icon' => 'fa-paper-plane',
                'description' => 'Mensajes enviados a clientes',
            ],
            'subscription-periods' => [
                'name' => 'Períodos de Suscripción',
                'table' => 'SubscriptionPeriodDesktop',
                'model' => SubscriptionPeriodDesktopModel::class,
                'icon' => 'fa-calendar-alt',
                'description' => 'Períodos de suscripciones',
            ],
            'subscriptions' => [
                'name' => 'Suscripciones',
                'table' => 'SubscriptionsDesktop',
                'model' => SubscriptionsDesktopModel::class,
                'icon' => 'fa-user-tag',
                'description' => 'Suscripciones de clientes',
            ],
            'sync-status' => [
                'name' => 'Estado de Sincronización',
                'table' => 'SyncStatusDesktop',
                'model' => SyncStatusDesktopModel::class,
                'icon' => 'fa-sync',
                'description' => 'Estado de sincronización de datos',
            ],
            'users' => [
                'name' => 'Usuarios',
                'table' => 'UsersDesktop',
                'model' => UsersDesktopModel::class,
                'icon' => 'fa-users',
                'description' => 'Usuarios del sistema',
            ],
            'whatsapp' => [
                'name' => 'WhatsApp',
                'table' => 'WhatsAppDesktop',
                'model' => WhatsAppDesktopModel::class,
                'icon' => 'fa-whatsapp',
                'description' => 'Configuración y mensajes de WhatsApp',
            ],
        ];
    }

    /**
     * Muestra la vista principal con listado de tablas desktop
     */
    public function index()
    {
        $this->requirePermission('admin_access');

        $currentUser = $this->userModel->getCurrentUser();
        $registry = new CustomerRegistryModel();
        $customers = $registry->getCustomers();

        $data = [
            'currentUser' => $currentUser,
            'title' => 'Tablas Desktop - ClubCheck',
            'isAuthenticated' => true,
            'desktopTables' => $this->desktopTables,
            'customers' => $customers,
        ];

        $this->view('admin/desktop-tables/index', $data);
    }

    /**
     * Muestra los datos de una tabla específica filtrada por customerApiId
     */
    public function viewTable()
    {
        $this->requirePermission('admin_access');

        // Obtener parámetros de la URL
        $tableKey = $_GET['table'] ?? '';
        $customerApiId = $_GET['customer'] ?? '';

        // Validar que la tabla existe
        if (!isset($this->desktopTables[$tableKey])) {
            $this->redirect('/admin/desktop-tables');
            return;
        }

        $tableInfo = $this->desktopTables[$tableKey];
        $currentUser = $this->userModel->getCurrentUser();
        $registry = new CustomerRegistryModel();
        $customers = $registry->getCustomers();

        // Obtener datos si hay un customerApiId seleccionado
        $records = [];
        $selectedCustomer = null;
        
        if (!empty($customerApiId)) {
            // Instanciar el modelo correspondiente
            $modelClass = $tableInfo['model'];
            $model = new $modelClass();
            
            // Obtener los datos usando el método pull
            $records = $model->pull($customerApiId, true);
            
            // Buscar información del cliente seleccionado
            foreach ($customers as $customer) {
                if ($customer['customerId'] === $customerApiId) {
                    $selectedCustomer = $customer;
                    break;
                }
            }
        }

        $data = [
            'currentUser' => $currentUser,
            'title' => $tableInfo['name'] . ' - Tablas Desktop',
            'isAuthenticated' => true,
            'tableKey' => $tableKey,
            'tableInfo' => $tableInfo,
            'customers' => $customers,
            'customerApiId' => $customerApiId,
            'selectedCustomer' => $selectedCustomer,
            'records' => $records,
        ];

        $this->view('admin/desktop-tables/view', $data);
    }

    /**
     * API: Obtiene los datos de una tabla en formato JSON
     */
    public function getData()
    {
        $this->requirePermission('admin_access');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['error' => 'Method not allowed'], 405);
        }

        $tableKey = $_GET['table'] ?? '';
        $customerApiId = $_GET['customer'] ?? '';

        // Validar que la tabla existe
        if (!isset($this->desktopTables[$tableKey])) {
            $this->json(['error' => 'Tabla no encontrada'], 404);
        }

        // Validar customerApiId
        if (empty($customerApiId)) {
            $this->json(['error' => 'customerApiId es requerido'], 422);
        }

        $tableInfo = $this->desktopTables[$tableKey];
        
        try {
            // Instanciar el modelo correspondiente
            $modelClass = $tableInfo['model'];
            $model = new $modelClass();
            
            // Obtener los datos
            $records = $model->pull($customerApiId, true);
            
            $this->json([
                'success' => true,
                'table' => $tableInfo['name'],
                'customerApiId' => $customerApiId,
                'count' => count($records),
                'records' => $records,
            ]);
        } catch (\Exception $e) {
            $this->json([
                'error' => 'Error al obtener datos: ' . $e->getMessage(),
            ], 500);
        }
    }
}
