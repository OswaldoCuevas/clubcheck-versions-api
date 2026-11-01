<?php

use App\Modules\Announcements\Controllers\AdminAnnouncementController;
use App\Modules\Announcements\Controllers\AnnouncementController;
use App\Modules\AdminAccess\Controllers\AdminAuthController;
use App\Modules\Admin\Controllers\AdminDashboardController;
use App\Modules\Billing\Controllers\BillingController;
use App\Modules\ApiDocs\Controllers\AdminApiDocsController;
use App\Modules\CustomerAccess\Controllers\AdminCustomerAccessController;
use App\Modules\CustomerSessions\Controllers\CustomerSessionController;
use App\Modules\Customers\Controllers\AdminCustomerController;
use App\Modules\Customers\Controllers\AdminDesktopTableController;
use App\Modules\Customers\Controllers\CustomerController;
use App\Modules\Customers\Controllers\CustomerDesktopSyncController;
use App\Modules\CustomerStats\Controllers\AdminCustomerStatsController;
use App\Modules\Downloads\Controllers\AdminDownloadController;
use App\Modules\Downloads\Controllers\PublicDownloadController;
use App\Modules\DesktopAccess\Controllers\DesktopLoginController;
use App\Modules\DesktopReporting\Controllers\DesktopReportController;
use App\Modules\DesktopReporting\Controllers\DesktopUserProfileController;
use App\Modules\Licenses\Controllers\AdminLicenseController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\PermissionRequests\Controllers\PermissionRequestController;
use App\Modules\PushNotifications\Controllers\PushNotificationController;
use App\Modules\Tools\Controllers\ToolsController;
use App\Modules\UserPhotos\Controllers\UserPhotoController;
use App\Modules\WhatsApp\Controllers\AdminWhatsAppController;
use App\Modules\WhatsApp\Controllers\CustomerWhatsAppController;
use Core\Router;

$container = require __DIR__ . '/../config/services.php';
$router = new Router($container);

// ==================== MIDDLEWARES ====================
// 
// Los middlewares se pasan como cuarto parámetro en un array:
//   $router->get('/ruta', 'Controller', 'method', ['middleware1', 'middleware2']);
// 
// Middlewares disponibles:
//   - 'jwt' : Requiere token JWT válido en header Authorization: Bearer <token>
//   - 'auth': Requiere sesión de usuario autenticado
// 
// Ejemplo de rutas protegidas con JWT:
//   $router->get('/api/protected', 'ExampleController', 'protectedMethod', ['jwt']);
//   $router->post('/api/data', 'DataController', 'store', ['jwt']);
// 
// Para acceder al payload del JWT en el controlador:
//   use App\Middleware\JwtMiddleware;
//   $userId = JwtMiddleware::get('user_id');
//   $payload = JwtMiddleware::getCurrentPayload();
// ======================================================

// Rutas principales
$router->any('/', 'HomeController', 'index');
$router->get('/home', 'HomeController', 'index');
$router->post('/home', 'HomeController', 'index');

// Rutas de autenticación
$router->get('/login', AdminAuthController::class, 'show');
$router->post('/login', AdminAuthController::class, 'login');
$router->get('/logout', AdminAuthController::class, 'logout');
$router->post('/logout', AdminAuthController::class, 'logout');

// Rutas de API limpias
$router->get('/api/version', PublicDownloadController::class, 'version');
$router->get('/api/check-update', PublicDownloadController::class, 'checkUpdate');
$router->get('/api/download', PublicDownloadController::class, 'download');
$router->get('/api/download-setup', PublicDownloadController::class, 'downloadSetup');
$router->get('/api/download-zip', PublicDownloadController::class, 'downloadZip');
$router->get('/api/server/timestamp', PublicDownloadController::class, 'timestamp');

// Rutas para sesiones de clientes (aplicación de escritorio)
$router->post('/api/customers/sessions/start', CustomerSessionController::class, 'startSession');
$router->post('/api/customers/sessions/heartbeat', CustomerSessionController::class, 'heartbeat');
$router->post('/api/customers/sessions/end', CustomerSessionController::class, 'endSession');
$router->get('/api/customers/sessions/active', CustomerSessionController::class, 'activeSessions');
$router->post('/api/customers/save', CustomerController::class, 'saveCustomer', ['customer_jwt']);
$router->post('/api/customers/register', CustomerController::class, 'registerCustomer');
$router->post('/api/customers/login', CustomerController::class, 'loginCustomer');
$router->post('/api/customers/validate', CustomerController::class, 'validateCustomer');
$router->get('/api/customers/token', CustomerController::class, 'customerToken');
$router->post('/api/customers/token/register', CustomerController::class, 'registerToken', ['customer_jwt']);
$router->post('/api/customers/token/await', CustomerController::class, 'awaitToken');
$router->get('/api/customers/jwt/validate', CustomerController::class, 'validateJwtToken');
$router->post('/api/customers/jwt/validate', CustomerController::class, 'validateJwtToken');
$router->post('/api/customers/update-client-version', CustomerController::class, 'updateClientVersion', ['customer_jwt']);
$router->post('/api/customers/push-tokens', PushNotificationController::class, 'register', ['push_client_jwt']);
$router->post('/api/customers/push-tokens/unregister', PushNotificationController::class, 'unregister', ['push_client_jwt']);
$router->post('/api/customers/permission-requests', PermissionRequestController::class, 'create', ['customer_jwt']);
$router->get('/api/customers/permission-requests/:id', PermissionRequestController::class, 'status', ['customer_jwt']);
$router->post('/api/customers/permission-requests/:id/cancel', PermissionRequestController::class, 'cancel', ['customer_jwt']);
$router->get('/api/customers/announcements/current', AnnouncementController::class, 'current', ['customer_jwt']);
$router->post('/api/customers/announcements/viewed', AnnouncementController::class, 'viewedCurrent', ['customer_jwt']);
$router->post('/api/customers/announcements/:id/viewed', AnnouncementController::class, 'viewed', ['customer_jwt']);
$router->post('/api/customers/desktop/pull', CustomerDesktopSyncController::class, 'pullDesktop', ['customer_jwt']);
$router->post('/api/customers/desktop/push', CustomerDesktopSyncController::class, 'pushDesktop', ['customer_jwt']);
$router->post('/api/customers/desktop/users/:userId/photo', UserPhotoController::class, 'upload', ['customer_jwt']);
$router->post('/api/customers', CustomerController::class, 'patchCustomer', ['customer_jwt']);
$router->patch('/api/customers', CustomerController::class, 'patchCustomer', ['customer_jwt']);
$router->get('/api/customers/:customerId', CustomerController::class, 'getCustomer', ['customer_jwt']);
$router->get('/api/customers/messages-sends-at-month/:customerId', CustomerController::class, 'getMessagesSendsAtMonth', ['customer_jwt']);

// Desktop web/reporting API
$router->post('/api/desktop/login', DesktopLoginController::class, 'login');
$router->get('/api/desktop/dashboard', DesktopReportController::class, 'dashboard', ['desktop_jwt']);
$router->get('/api/desktop/users', DesktopReportController::class, 'users', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId', DesktopUserProfileController::class, 'user', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/attendances', DesktopUserProfileController::class, 'attendances', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/subscriptions', DesktopUserProfileController::class, 'subscriptions', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/statistics', DesktopUserProfileController::class, 'statistics', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/photo/original', UserPhotoController::class, 'readOriginal', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/photo', UserPhotoController::class, 'read', ['desktop_jwt']);
$router->get('/api/desktop/memberships', DesktopReportController::class, 'memberships', ['desktop_jwt']);
$router->get('/api/desktop/membership-sales', DesktopReportController::class, 'membershipSales', ['desktop_jwt']);
$router->get('/api/desktop/products', DesktopReportController::class, 'products', ['desktop_jwt']);
$router->get('/api/desktop/attendances', DesktopReportController::class, 'attendances', ['desktop_jwt']);
$router->get('/api/desktop/sales', DesktopReportController::class, 'sales', ['desktop_jwt']);
$router->get('/api/desktop/admins', DesktopReportController::class, 'admins', ['desktop_jwt']);
$router->get('/api/desktop/charts', DesktopReportController::class, 'chartsOverview', ['desktop_jwt']);
$router->get('/api/desktop/charts/users', DesktopReportController::class, 'chartsUsers', ['desktop_jwt']);
$router->get('/api/desktop/charts/memberships', DesktopReportController::class, 'chartsMemberships', ['desktop_jwt']);
$router->get('/api/desktop/charts/products', DesktopReportController::class, 'chartsProducts', ['desktop_jwt']);
$router->get('/api/desktop/charts/attendances', DesktopReportController::class, 'chartsAttendances', ['desktop_jwt']);
$router->get('/api/desktop/charts/sales', DesktopReportController::class, 'chartsSales', ['desktop_jwt']);
$router->get('/api/desktop/permission-requests', PermissionRequestController::class, 'index', ['desktop_jwt']);
$router->patch('/api/desktop/permission-requests/:id', PermissionRequestController::class, 'resolve', ['desktop_jwt']);
$router->get('/api/desktop/notifications', NotificationController::class, 'index', ['desktop_jwt']);
$router->patch('/api/desktop/notifications/read-all', NotificationController::class, 'markAllRead', ['desktop_jwt']);
$router->patch('/api/desktop/notifications/:id/read', NotificationController::class, 'markRead', ['desktop_jwt']);

// Cliente ISAPI de escritorio (customer_jwt): canal persistente basado en cola.
// WebSocket puede agregarse despues como aviso; estos endpoints conservan entrega y reintentos.
$router->post('/api/customers/isapi/heartbeat', 'IsapiController', 'heartbeat', ['customer_jwt']);
$router->post('/api/customers/isapi/commands/claim', 'IsapiController', 'claim', ['customer_jwt']);
$router->post('/api/customers/isapi/commands/:id/result', 'IsapiController', 'result', ['customer_jwt']);

// Rutas de mensajes enviados (MessageSent)
// $router->get('/api/messages-sent', 'MessageSentController', 'index');
// $router->get('/api/messages-sent/:id', 'MessageSentController', 'show');
// $router->post('/api/messages-sent', 'MessageSentController', 'store');
// $router->put('/api/messages-sent/:id', 'MessageSentController', 'update');
// $router->delete('/api/messages-sent/:id', 'MessageSentController', 'destroy');

// ==================== WHATSAPP ====================
// Estado del servicio
$router->get('/api/customers/whatsapp/status', CustomerWhatsAppController::class, 'status');
$router->get('/api/customers/whatsapp/monthly-count/:customerApiId', CustomerWhatsAppController::class, 'monthlyCount', ['customer_jwt']);

// Listado de mensajes con filtros
$router->get('/api/customers/whatsapp/messages/:customerApiId', CustomerWhatsAppController::class, 'listMessages', ['customer_jwt']);

// Envío de templates individuales
$router->post('/api/customers/whatsapp/send/subscription', CustomerWhatsAppController::class, 'sendSubscription', ['customer_jwt']);
$router->post('/api/customers/whatsapp/send/warning', CustomerWhatsAppController::class, 'sendWarning', ['customer_jwt']);
$router->post('/api/customers/whatsapp/send/finalized', CustomerWhatsAppController::class, 'sendFinalized', ['customer_jwt']);
$router->post('/api/customers/whatsapp/send/last-day', CustomerWhatsAppController::class, 'sendLastDay', ['customer_jwt']);

// Envío en bulk
$router->post('/api/customers/whatsapp/send/bulk', CustomerWhatsAppController::class, 'sendBulk', ['customer_jwt']);

// Perfil del negocio
$router->post('/api/customers/whatsapp/business-profile/register', CustomerWhatsAppController::class, 'registerBusinessProfile'); // NO, Administrativo
$router->get('/api/customers/whatsapp/business-profile', CustomerWhatsAppController::class, 'getBusinessProfile'); // NO, Administrativo
$router->post('/api/customers/whatsapp/business-profile/:customerId', CustomerWhatsAppController::class, 'registerBusinessProfile');
$router->post('/api/customers/whatsapp/business-profile/:customerId/register', CustomerWhatsAppController::class, 'registerBusinessProfile');
$router->get('/api/customers/whatsapp/business-profile/:customerId', CustomerWhatsAppController::class, 'getBusinessProfile');

// Rutas administrativas
$adminMiddleware = ['auth', 'permission:admin_access'];
$router->any('/admin', AdminDashboardController::class, 'index', $adminMiddleware);
$router->get('/admin/customers', AdminCustomerController::class, 'customers', $adminMiddleware);
$router->get('/admin/customer-login-attempts', AdminCustomerAccessController::class, 'customerLoginAttempts', $adminMiddleware);
$router->get('/admin/api/customers', AdminCustomerController::class, 'customersJson', $adminMiddleware);
$router->get('/admin/api/customer-login-attempts', AdminCustomerAccessController::class, 'customerLoginAttemptsJson', $adminMiddleware);
$router->post('/admin/api/customers/save', AdminCustomerController::class, 'saveCustomerJson', $adminMiddleware);
$router->post('/admin/api/customers/regenerate-access-key', AdminCustomerController::class, 'regenerateAccessKey', $adminMiddleware);
// Eliminar cliente (desde panel admin)
$router->post('/admin/api/customers/:customerId/delete', AdminCustomerController::class, 'deleteCustomerJson', $adminMiddleware);
$router->delete('/admin/api/customers/:customerId', AdminCustomerController::class, 'deleteCustomerJson', $adminMiddleware);
$router->get('/admin/api-docs', AdminApiDocsController::class, 'apiDocs', $adminMiddleware);
$router->get('/admin/push', PushNotificationController::class, 'page', $adminMiddleware);
$router->post('/admin/api/push/send', PushNotificationController::class, 'send', $adminMiddleware);

// Announcements Admin
$router->get('/admin/announcements',AdminAnnouncementController::class,'announcements',$adminMiddleware,);
$router->get('/admin/api/announcements',AdminAnnouncementController::class,'announcementsList',$adminMiddleware,);
$router->post('/admin/api/announcements',AdminAnnouncementController::class,'save',$adminMiddleware,);
$router->post('/admin/api/announcements/upload-image',AdminAnnouncementController::class,'uploadImage',$adminMiddleware,);
$router->get('/admin/api/announcements/:id/views',AdminAnnouncementController::class,'getViews',$adminMiddleware,);
$router->post('/admin/api/announcements/:id/activate',AdminAnnouncementController::class,'activate',$adminMiddleware,);
$router->post('/admin/api/announcements/:id/delete',AdminAnnouncementController::class,'delete',$adminMiddleware,);
$router->delete('/admin/api/announcements/:id',AdminAnnouncementController::class,'delete',$adminMiddleware,);

// WhatsApp Admin CRUD
$router->get('/admin/whatsapp', AdminWhatsAppController::class, 'whatsapp', $adminMiddleware);
$router->get('/admin/whatsapp/messages', AdminWhatsAppController::class, 'whatsappMessages', $adminMiddleware);
$router->post('/admin/whatsapp/messages/test', AdminWhatsAppController::class, 'sendWhatsappTestMessage', $adminMiddleware);
$router->get('/admin/api/whatsapp', AdminWhatsAppController::class, 'whatsappListJson', $adminMiddleware);
$router->get('/admin/api/whatsapp/templates', AdminWhatsAppController::class, 'whatsappTemplatesJson', $adminMiddleware);
$router->post('/admin/api/whatsapp/templates', AdminWhatsAppController::class, 'whatsappTemplateCreateJson', $adminMiddleware);
$router->post('/admin/api/whatsapp/templates/:id/delete', AdminWhatsAppController::class, 'whatsappTemplateDeleteJson', $adminMiddleware);
$router->delete('/admin/api/whatsapp/templates/:id', AdminWhatsAppController::class, 'whatsappTemplateDeleteJson', $adminMiddleware);
$router->post('/admin/api/whatsapp/:id/delete', AdminWhatsAppController::class, 'whatsappDeleteJson', $adminMiddleware);
$router->post('/admin/api/whatsapp/:id/register', AdminWhatsAppController::class, 'whatsappRegisterJson', $adminMiddleware);
$router->get('/admin/api/whatsapp/:id/status', AdminWhatsAppController::class, 'whatsappStatusJson', $adminMiddleware);
$router->delete('/admin/api/whatsapp/:id', AdminWhatsAppController::class, 'whatsappDeleteJson', $adminMiddleware);
$router->post('/admin/api/whatsapp', AdminWhatsAppController::class, 'whatsappCreateJson', $adminMiddleware);

// JWT Tokens Admin
$router->get('/admin/jwt-tokens', AdminCustomerAccessController::class, 'jwtTokens', $adminMiddleware);
$router->get('/admin/api/jwt-tokens', AdminCustomerAccessController::class, 'jwtTokensJson', $adminMiddleware);
$router->post('/admin/api/jwt-tokens/create', AdminCustomerAccessController::class, 'createJwtToken', $adminMiddleware);
$router->post('/admin/api/jwt-tokens/revoke', AdminCustomerAccessController::class, 'revokeJwtToken', $adminMiddleware);
$router->get('/admin/api/jwt-tokens/customer/:customerId/ips', AdminCustomerAccessController::class, 'customerIpsJson', $adminMiddleware);
$router->post('/admin/api/jwt-tokens/ips/:id/flag', AdminCustomerAccessController::class, 'flagIp', $adminMiddleware);

// Customer Stats Admin
$router->get('/admin/customer-stats', AdminCustomerStatsController::class, 'customerStats', $adminMiddleware);
$router->get('/admin/api/customer-stats', AdminCustomerStatsController::class, 'customerStatsJson', $adminMiddleware);
$router->get('/admin/api/customer-stats/:customerId', AdminCustomerStatsController::class, 'customerStatsDetailJson', $adminMiddleware);

// Desktop Tables Admin
$router->get('/admin/desktop-tables', AdminDesktopTableController::class, 'index', $adminMiddleware);
$router->get('/admin/desktop-tables/view', AdminDesktopTableController::class, 'viewTable', $adminMiddleware);
$router->get('/admin/desktop-tables/api/data', AdminDesktopTableController::class, 'getData', $adminMiddleware);

// Downloads History Admin
$router->get('/admin/downloads', AdminDownloadController::class, 'downloads', $adminMiddleware);
$router->get('/admin/api/downloads', AdminDownloadController::class, 'downloadsJson', $adminMiddleware);
$router->get('/admin/api/downloads/ip/:ipAddress', AdminDownloadController::class, 'downloadsByIpJson', $adminMiddleware);

// License Validation (public — no auth required)
$router->post('/api/licenses/validate', BillingController::class, 'validateLicense');

// Licenses Admin
$router->get('/admin/licenses', AdminLicenseController::class, 'licenses', $adminMiddleware);
$router->get('/admin/api/licenses', AdminLicenseController::class, 'licensesJson', $adminMiddleware);
$router->post('/admin/api/licenses/generate', AdminLicenseController::class, 'generateLicenseAdmin', $adminMiddleware);

// Diagnostico remoto ISAPI
$router->get('/admin/isapi', 'IsapiController', 'page');
$router->get('/admin/api/isapi', 'IsapiController', 'adminIndex');
$router->post('/admin/api/isapi/commands', 'IsapiController', 'adminCreate');
$router->get('/admin/api/isapi/commands/:id', 'IsapiController', 'adminShow');

// Rutas de herramientas
$router->get('/password-generator', ToolsController::class, 'passwordGenerator', $adminMiddleware);
$router->post('/password-generator', ToolsController::class, 'passwordGenerator', $adminMiddleware);
$router->get('/quick-hash', ToolsController::class, 'quickHash', $adminMiddleware);
$router->get('/generate-password', ToolsController::class, 'generatePassword', $adminMiddleware);

// ==================== STRIPE ====================
// Configuración pública (clave pública para el cliente)
$router->get('/api/customers/stripe/config', BillingController::class, 'getPublicConfig');

// Clientes
$router->post('/api/customers/stripe/customers', BillingController::class, 'createCustomer', ['customer_jwt']);
$router->get('/api/customers/stripe/customers/:customerId', BillingController::class, 'getCustomer', ['customer_jwt']);
$router->put('/api/customers/stripe/customers/:customerId', BillingController::class, 'updateCustomer', ['customer_jwt']);

// Tarjetas
$router->post('/api/customers/stripe/customers/:customerId/cards', BillingController::class, 'addCard', ['customer_jwt']);
$router->get('/api/customers/stripe/customers/:customerId/cards', BillingController::class, 'listCards', ['customer_jwt']);
$router->delete('/api/customers/stripe/customers/:customerId/cards/:cardId', BillingController::class, 'deleteCard', ['customer_jwt']);
$router->put('/api/customers/stripe/customers/:customerId/cards/:cardId/default', BillingController::class, 'setDefaultCard', ['customer_jwt']);

// Suscripciones
$router->post('/api/customers/stripe/customers/:customerId/subscriptions', BillingController::class, 'createSubscription', ['customer_jwt']);
$router->get('/api/customers/stripe/customers/:customerId/subscriptions/active', BillingController::class, 'getActiveSubscription', ['customer_jwt']);
$router->put('/api/customers/stripe/subscriptions/:subscriptionId', BillingController::class, 'updateSubscription', ['customer_jwt']);
$router->put('/api/customers/stripe/subscriptions/:subscriptionId/plan', BillingController::class, 'changePlan', ['customer_jwt']);
$router->post('/api/customers/stripe/license/refresh', BillingController::class, 'refreshLicense', ['customer_jwt']);

// Paquetes
$router->get('/api/customers/stripe/plans', BillingController::class, 'getPlans', ['customer_jwt']);
$router->get('/api/customers/stripe/customers/:customerId/plan', BillingController::class, 'getCurrentPlan', ['customer_jwt']);

// Precios/Planes
$router->get('/api/customers/stripe/prices', BillingController::class, 'listPrices', ['customer_jwt']);
$router->post('/api/customers/stripe/subscriptions/:subscriptionId/preview', BillingController::class, 'previewPlanChange', ['customer_jwt']);
$router->post('/api/customers/stripe/customers/:customerId/subscriptions/preview', BillingController::class, 'previewNewSubscription', ['customer_jwt']);

// Cupones
$router->post('/api/customers/stripe/coupons/validate', BillingController::class, 'validateCoupon');

// ==================== EMAIL ====================
// Estado y tipos
$router->get('/api/email/status', 'EmailController', 'status');
$router->get('/api/email/types', 'EmailController', 'types');

// Envío de correos
$router->post('/api/email/send', 'EmailController', 'send', ['customer_jwt']);
$router->post('/api/email/send/password-reset', 'EmailController', 'sendPasswordReset', ['customer_jwt']);
$router->post('/api/email/send/email-confirmation', 'EmailController', 'sendEmailConfirmation', ['customer_jwt']);
$router->post('/api/email/send/welcome', 'EmailController', 'sendWelcome', ['customer_jwt']);
$router->post('/api/email/send/notification', 'EmailController', 'sendNotification', ['customer_jwt']);

// Verificación de códigos
$router->post('/api/email/verify', 'EmailController', 'verify', ['customer_jwt']);
$router->post('/api/email/verify/password-reset', 'EmailController', 'verifyPasswordReset', ['customer_jwt']);
$router->post('/api/email/verify/email-confirmation', 'EmailController', 'verifyEmailConfirmation', ['customer_jwt']);

// Historial y estadísticas
$router->get('/api/email/history/:email', 'EmailController', 'history', ['customer_jwt']);
$router->get('/api/email/stats', 'EmailController', 'stats', ['customer_jwt']);
$router->post('/api/email/cleanup', 'EmailController', 'cleanup', ['customer_jwt']);

return $router;
