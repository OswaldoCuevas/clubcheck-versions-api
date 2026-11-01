<?php

use App\Modules\Announcements\Controllers\AdminAnnouncementController;
use App\Modules\Admin\Controllers\AdminDashboardController;
use App\Modules\ApiDocs\Controllers\AdminApiDocsController;
use App\Modules\CustomerAccess\Controllers\AdminCustomerAccessController;
use App\Modules\CustomerSessions\Controllers\CustomerSessionController;
use App\Modules\Customers\Controllers\AdminCustomerController;
use App\Modules\Customers\Controllers\CustomerController;
use App\Modules\Customers\Controllers\CustomerDesktopSyncController;
use App\Modules\CustomerStats\Controllers\AdminCustomerStatsController;
use App\Modules\Downloads\Controllers\AdminDownloadController;
use App\Modules\Licenses\Controllers\AdminLicenseController;
use App\Modules\WhatsApp\Controllers\AdminWhatsAppController;
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
//   $router->get('/api/protected', 'ApiController', 'protectedMethod', ['jwt']);
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
$router->any('/login', 'AuthController', 'login');
$router->any('/logout', 'AuthController', 'logout');

// Rutas de API limpias
$router->get('/api/version', 'ApiController', 'version');
$router->get('/api/check-update', 'ApiController', 'checkUpdate');
$router->get('/api/download', 'ApiController', 'download');
$router->get('/api/download-setup', 'ApiController', 'downloadSetup'); // Compatibilidad: descarga publica del Setup ZIP
$router->get('/api/download-zip', 'ApiController', 'downloadZip'); // Descarga publica del Setup ZIP
$router->get('/api/server/timestamp', 'ApiController', 'timestamp'); // Timestamp del servidor

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
$router->post('/api/customers/push-tokens', 'PushNotificationsController', 'register', ['push_client_jwt']);
$router->post('/api/customers/push-tokens/unregister', 'PushNotificationsController', 'unregister', ['push_client_jwt']);
$router->post('/api/customers/permission-requests', 'PermissionRequestsController', 'create', ['customer_jwt']);
$router->get('/api/customers/permission-requests/:id', 'PermissionRequestsController', 'status', ['customer_jwt']);
$router->post('/api/customers/permission-requests/:id/cancel', 'PermissionRequestsController', 'cancel', ['customer_jwt']);
$router->get('/api/customers/announcements/current', 'AnnouncementsController', 'current', ['customer_jwt']);
$router->post('/api/customers/announcements/viewed', 'AnnouncementsController', 'viewedCurrent', ['customer_jwt']);
$router->post('/api/customers/announcements/:id/viewed', 'AnnouncementsController', 'viewed', ['customer_jwt']);
$router->post('/api/customers/desktop/pull', CustomerDesktopSyncController::class, 'pullDesktop', ['customer_jwt']);
$router->post('/api/customers/desktop/push', CustomerDesktopSyncController::class, 'pushDesktop', ['customer_jwt']);
$router->post('/api/customers/desktop/users/:userId/photo', 'UserPhotosController', 'upload', ['customer_jwt']);
$router->post('/api/customers', CustomerController::class, 'patchCustomer', ['customer_jwt']);
$router->patch('/api/customers', CustomerController::class, 'patchCustomer', ['customer_jwt']);
$router->get('/api/customers/:customerId', CustomerController::class, 'getCustomer', ['customer_jwt']);
$router->get('/api/customers/messages-sends-at-month/:customerId', CustomerController::class, 'getMessagesSendsAtMonth', ['customer_jwt']);

// Desktop web/reporting API
$router->post('/api/desktop/login', 'DesktopApiController', 'login');
$router->get('/api/desktop/dashboard', 'DesktopApiController', 'dashboard', ['desktop_jwt']);
$router->get('/api/desktop/users', 'DesktopApiController', 'users', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId', 'DesktopUserProfileController', 'user', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/attendances', 'DesktopUserProfileController', 'attendances', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/subscriptions', 'DesktopUserProfileController', 'subscriptions', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/statistics', 'DesktopUserProfileController', 'statistics', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/photo/original', 'UserPhotosController', 'readOriginal', ['desktop_jwt']);
$router->get('/api/desktop/users/:userId/photo', 'UserPhotosController', 'read', ['desktop_jwt']);
$router->get('/api/desktop/memberships', 'DesktopApiController', 'memberships', ['desktop_jwt']);
$router->get('/api/desktop/membership-sales', 'DesktopApiController', 'membershipSales', ['desktop_jwt']);
$router->get('/api/desktop/products', 'DesktopApiController', 'products', ['desktop_jwt']);
$router->get('/api/desktop/attendances', 'DesktopApiController', 'attendances', ['desktop_jwt']);
$router->get('/api/desktop/sales', 'DesktopApiController', 'sales', ['desktop_jwt']);
$router->get('/api/desktop/admins', 'DesktopApiController', 'admins', ['desktop_jwt']);
$router->get('/api/desktop/charts', 'DesktopApiController', 'chartsOverview', ['desktop_jwt']);
$router->get('/api/desktop/charts/users', 'DesktopApiController', 'chartsUsers', ['desktop_jwt']);
$router->get('/api/desktop/charts/memberships', 'DesktopApiController', 'chartsMemberships', ['desktop_jwt']);
$router->get('/api/desktop/charts/products', 'DesktopApiController', 'chartsProducts', ['desktop_jwt']);
$router->get('/api/desktop/charts/attendances', 'DesktopApiController', 'chartsAttendances', ['desktop_jwt']);
$router->get('/api/desktop/charts/sales', 'DesktopApiController', 'chartsSales', ['desktop_jwt']);
$router->get('/api/desktop/permission-requests', 'PermissionRequestsController', 'index', ['desktop_jwt']);
$router->patch('/api/desktop/permission-requests/:id', 'PermissionRequestsController', 'resolve', ['desktop_jwt']);
$router->get('/api/desktop/notifications', 'NotificationsController', 'index', ['desktop_jwt']);
$router->patch('/api/desktop/notifications/read-all', 'NotificationsController', 'markAllRead', ['desktop_jwt']);
$router->patch('/api/desktop/notifications/:id/read', 'NotificationsController', 'markRead', ['desktop_jwt']);

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
$router->get('/api/customers/whatsapp/status', 'WhatsAppController', 'status');
$router->get('/api/customers/whatsapp/monthly-count/:customerApiId', 'WhatsAppController', 'monthlyCount', ['customer_jwt']);

// Listado de mensajes con filtros
$router->get('/api/customers/whatsapp/messages/:customerApiId', 'WhatsAppController', 'listMessages', ['customer_jwt']);

// Envío de templates individuales
$router->post('/api/customers/whatsapp/send/subscription', 'WhatsAppController', 'sendSubscription', ['customer_jwt']);
$router->post('/api/customers/whatsapp/send/warning', 'WhatsAppController', 'sendWarning', ['customer_jwt']);
$router->post('/api/customers/whatsapp/send/finalized', 'WhatsAppController', 'sendFinalized', ['customer_jwt']);
$router->post('/api/customers/whatsapp/send/last-day', 'WhatsAppController', 'sendLastDay', ['customer_jwt']);

// Envío en bulk
$router->post('/api/customers/whatsapp/send/bulk', 'WhatsAppController', 'sendBulk', ['customer_jwt']);

// Perfil del negocio
$router->post('/api/customers/whatsapp/business-profile/register', 'WhatsAppController', 'registerBusinessProfile'); // NO, Administrativo
$router->get('/api/customers/whatsapp/business-profile', 'WhatsAppController', 'getBusinessProfile'); // NO, Administrativo

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
$router->get('/admin/push', 'PushNotificationsController', 'page');
$router->post('/admin/api/push/send', 'PushNotificationsController', 'send');

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
$router->get('/admin/desktop-tables', 'DesktopTablesController', 'index');// NO, Administrativo
$router->get('/admin/desktop-tables/view', 'DesktopTablesController', 'viewTable');// NO, Administrativo
$router->get('/admin/desktop-tables/api/data', 'DesktopTablesController', 'getData');// NO, Administrativo

// Downloads History Admin
$router->get('/admin/downloads', AdminDownloadController::class, 'downloads', $adminMiddleware);
$router->get('/admin/api/downloads', AdminDownloadController::class, 'downloadsJson', $adminMiddleware);
$router->get('/admin/api/downloads/ip/:ipAddress', AdminDownloadController::class, 'downloadsByIpJson', $adminMiddleware);

// License Validation (public — no auth required)
$router->post('/api/licenses/validate', 'StripeController', 'validateLicense');

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
$router->any('/password-generator', 'ToolsController', 'passwordGenerator');
$router->any('/quick-hash', 'ToolsController', 'quickHash');
$router->any('/generate-password', 'ToolsController', 'generatePassword');

// ==================== STRIPE ====================
// Configuración pública (clave pública para el cliente)
$router->get('/api/customers/stripe/config', 'StripeController', 'getPublicConfig');

// Clientes
$router->post('/api/customers/stripe/customers', 'StripeController', 'createCustomer', ['customer_jwt']);
$router->get('/api/customers/stripe/customers/:customerId', 'StripeController', 'getCustomer', ['customer_jwt']);
$router->put('/api/customers/stripe/customers/:customerId', 'StripeController', 'updateCustomer', ['customer_jwt']);

// Tarjetas
$router->post('/api/customers/stripe/customers/:customerId/cards', 'StripeController', 'addCard', ['customer_jwt']);
$router->get('/api/customers/stripe/customers/:customerId/cards', 'StripeController', 'listCards', ['customer_jwt']);
$router->delete('/api/customers/stripe/customers/:customerId/cards/:cardId', 'StripeController', 'deleteCard', ['customer_jwt']);
$router->put('/api/customers/stripe/customers/:customerId/cards/:cardId/default', 'StripeController', 'setDefaultCard', ['customer_jwt']);

// Suscripciones
$router->post('/api/customers/stripe/customers/:customerId/subscriptions', 'StripeController', 'createSubscription', ['customer_jwt']);
$router->get('/api/customers/stripe/customers/:customerId/subscriptions/active', 'StripeController', 'getActiveSubscription', ['customer_jwt']);
$router->put('/api/customers/stripe/subscriptions/:subscriptionId', 'StripeController', 'updateSubscription', ['customer_jwt']);
$router->put('/api/customers/stripe/subscriptions/:subscriptionId/plan', 'StripeController', 'changePlan', ['customer_jwt']);
$router->post('/api/customers/stripe/license/refresh', 'StripeController', 'refreshLicense', ['customer_jwt']);

// Paquetes
$router->get('/api/customers/stripe/plans', 'StripeController', 'getplans', ['customer_jwt']);
$router->get('/api/customers/stripe/customers/:customerId/plan', 'StripeController', 'getCurrentPlan', ['customer_jwt']);

// Precios/Planes
$router->get('/api/customers/stripe/prices', 'StripeController', 'listPrices', ['customer_jwt']);
$router->post('/api/customers/stripe/subscriptions/:subscriptionId/preview', 'StripeController', 'previewPlanChange', ['customer_jwt']);
$router->post('/api/customers/stripe/customers/:customerId/subscriptions/preview', 'StripeController', 'previewNewSubscription', ['customer_jwt']);

// Cupones
$router->post('/api/customers/stripe/coupons/validate', 'StripeController', 'validateCoupon');

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
