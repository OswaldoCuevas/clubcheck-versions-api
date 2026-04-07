<?php

use Core\Router;

$router = new Router();

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
$router->get('/api/download-setup', 'ApiController', 'downloadSetup'); // Descarga pública del Setup

// Rutas para sesiones de clientes (aplicación de escritorio)
$router->any('/api/customers/sessions/start', 'CustomersController', 'startSession');// NO
$router->any('/api/customers/sessions/heartbeat', 'CustomersController', 'heartbeat');// NO
$router->any('/api/customers/sessions/end', 'CustomersController', 'endSession');// NO
$router->any('/api/customers/sessions/active', 'CustomersController', 'activeSessions');// NO
$router->any('/api/customers/save', 'CustomersController', 'saveCustomer', ['customer_jwt']);
$router->any('/api/customers/register', 'CustomersController', 'registerCustomer');// NO
$router->any('/api/customers/login', 'CustomersController', 'loginCustomer');// NO
$router->post('/api/customers/validate', 'CustomersController', 'validateCustomer');// NO
$router->any('/api/customers', 'CustomersController', 'patchCustomer',['customer_jwt']);
$router->any('/api/customers/:customerId', 'CustomersController', 'getCustomer', ['customer_jwt']);
$router->any('/api/customers/token', 'CustomersController', 'customerToken'); // NO
$router->any('/api/customers/token/register', 'CustomersController', 'registerToken', ['customer_jwt']);
$router->any('/api/customers/token/await', 'CustomersController', 'awaitToken');// NO
$router->any('/api/customers/jwt/validate', 'CustomersController', 'validateJwtToken');
$router->any('/api/customers/desktop/pull', 'CustomersController', 'pullDesktop', ['customer_jwt']);
$router->any('/api/customers/desktop/push', 'CustomersController', 'pushDesktop', ['customer_jwt']);
$router->any('/api/customers/messages-sends-at-month/:customerId', 'CustomersController', 'getMessagesSendsAtMonth', ['customer_jwt']);

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
$router->any('/admin', 'AdminController', 'index');// NO, Administrativo
$router->get('/admin/customers', 'AdminController', 'customers');// NO, Administrativo
$router->get('/admin/api/customers', 'AdminController', 'customersJson');// NO, Administrativo
$router->post('/admin/api/customers/regenerate-access-key', 'AdminController', 'regenerateAccessKey');// NO, Administrativo
$router->get('/admin/api-docs', 'AdminController', 'apiDocs');// NO, Administrativo

// WhatsApp Admin CRUD
$router->get('/admin/whatsapp', 'AdminController', 'whatsapp');// NO, Administrativo
$router->get('/admin/api/whatsapp', 'AdminController', 'whatsappListJson');// NO, Administrativo
$router->post('/admin/api/whatsapp/:id/delete', 'AdminController', 'whatsappDeleteJson');// NO, Administrativo
$router->post('/admin/api/whatsapp/:id/register', 'AdminController', 'whatsappRegisterJson');// NO, Administrativo
$router->get('/admin/api/whatsapp/:id/status', 'AdminController', 'whatsappStatusJson');// NO, Administrativo
$router->delete('/admin/api/whatsapp/:id', 'AdminController', 'whatsappDeleteJson');// NO, Administrativo
$router->post('/admin/api/whatsapp', 'AdminController', 'whatsappCreateJson');// NO, Administrativo

// JWT Tokens Admin
$router->get('/admin/jwt-tokens', 'AdminController', 'jwtTokens');// NO, Administrativo
$router->get('/admin/api/jwt-tokens', 'AdminController', 'jwtTokensJson');// NO, Administrativo
$router->post('/admin/api/jwt-tokens/create', 'AdminController', 'createJwtToken');// NO, Administrativo
$router->post('/admin/api/jwt-tokens/revoke', 'AdminController', 'revokeJwtToken');// NO, Administrativo
$router->get('/admin/api/jwt-tokens/customer/:customerId/ips', 'AdminController', 'customerIpsJson');// NO, Administrativo
$router->post('/admin/api/jwt-tokens/ips/:id/flag', 'AdminController', 'flagIp');// NO, Administrativo

// Customer Stats Admin
$router->get('/admin/customer-stats', 'AdminController', 'customerStats');// NO, Administrativo
$router->get('/admin/api/customer-stats', 'AdminController', 'customerStatsJson');// NO, Administrativo
$router->get('/admin/api/customer-stats/:customerId', 'AdminController', 'customerStatsDetailJson');// NO, Administrativo

// Downloads History Admin
$router->get('/admin/downloads', 'AdminController', 'downloads');// NO, Administrativo
$router->get('/admin/api/downloads', 'AdminController', 'downloadsJson');// NO, Administrativo
$router->get('/admin/api/downloads/ip/:ipAddress', 'AdminController', 'downloadsByIpJson');// NO, Administrativo

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

// Paquetes
$router->get('/api/customers/stripe/plans', 'StripeController', 'getplans'); // NO, para mostrar planes disponibles al cliente
$router->get('/api/customers/stripe/customers/:customerId/plan', 'StripeController', 'getCurrentPlan', ['customer_jwt']);

// Precios/Planes
$router->get('/api/customers/stripe/prices', 'StripeController', 'listPrices', ['customer_jwt']);
$router->post('/api/customers/stripe/subscriptions/:subscriptionId/preview', 'StripeController', 'previewPlanChange', ['customer_jwt']);
$router->post('/api/customers/stripe/customers/:customerId/subscriptions/preview', 'StripeController', 'previewNewSubscription', ['customer_jwt']);

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
