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

// Rutas para sesiones de clientes (aplicación de escritorio)
$router->any('/api/customers/sessions/start', 'CustomersController', 'startSession');
$router->any('/api/customers/sessions/heartbeat', 'CustomersController', 'heartbeat');
$router->any('/api/customers/sessions/end', 'CustomersController', 'endSession');
$router->any('/api/customers/sessions/active', 'CustomersController', 'activeSessions');
$router->any('/api/customers/save', 'CustomersController', 'saveCustomer');
$router->any('/api/customers/register', 'CustomersController', 'registerCustomer');
$router->any('/api/customers/login', 'CustomersController', 'loginCustomer');
$router->post('/api/customers/validate', 'CustomersController', 'validateCustomer');
$router->any('/api/customers', 'CustomersController', 'patchCustomer');
$router->any('/api/customers/:customerId', 'CustomersController', 'getCustomer');
$router->any('/api/customers/token', 'CustomersController', 'customerToken');
$router->any('/api/customers/token/register', 'CustomersController', 'registerToken');
$router->any('/api/customers/token/await', 'CustomersController', 'awaitToken');
$router->any('/api/customers/jwt/validate', 'CustomersController', 'validateJwtToken');
$router->any('/api/customers/desktop/pull', 'CustomersController', 'pullDesktop');
$router->any('/api/customers/desktop/push', 'CustomersController', 'pushDesktop');
$router->any('/api/customers/messages-sends-at-month/:customerId', 'CustomersController', 'getMessagesSendsAtMonth');

// Rutas de mensajes enviados (MessageSent)
$router->get('/api/messages-sent', 'MessageSentController', 'index');
$router->get('/api/messages-sent/:id', 'MessageSentController', 'show');
$router->post('/api/messages-sent', 'MessageSentController', 'store');
$router->put('/api/messages-sent/:id', 'MessageSentController', 'update');
$router->delete('/api/messages-sent/:id', 'MessageSentController', 'destroy');

// ==================== WHATSAPP ====================
// Estado del servicio
$router->get('/api/customers/whatsapp/status', 'WhatsAppController', 'status');
$router->get('/api/customers/whatsapp/monthly-count/:customerApiId', 'WhatsAppController', 'monthlyCount');

// Listado de mensajes con filtros
$router->get('/api/customers/whatsapp/messages/:customerApiId', 'WhatsAppController', 'listMessages');

// Envío de templates individuales
$router->post('/api/customers/whatsapp/send/subscription', 'WhatsAppController', 'sendSubscription');
$router->post('/api/customers/whatsapp/send/warning', 'WhatsAppController', 'sendWarning');
$router->post('/api/customers/whatsapp/send/finalized', 'WhatsAppController', 'sendFinalized');
$router->post('/api/customers/whatsapp/send/last-day', 'WhatsAppController', 'sendLastDay');

// Envío en bulk
$router->post('/api/customers/whatsapp/send/bulk', 'WhatsAppController', 'sendBulk');

// Perfil del negocio
$router->post('/api/customers/whatsapp/business-profile/register', 'WhatsAppController', 'registerBusinessProfile');
$router->get('/api/customers/whatsapp/business-profile', 'WhatsAppController', 'getBusinessProfile');

// Rutas administrativas
$router->any('/admin', 'AdminController', 'index');
$router->get('/admin/customers', 'AdminController', 'customers');
$router->get('/admin/api/customers', 'AdminController', 'customersJson');
$router->post('/admin/api/customers/regenerate-access-key', 'AdminController', 'regenerateAccessKey');
$router->get('/admin/api-docs', 'AdminController', 'apiDocs');

// WhatsApp Admin CRUD
$router->get('/admin/whatsapp', 'AdminController', 'whatsapp');
$router->get('/admin/api/whatsapp', 'AdminController', 'whatsappListJson');
$router->post('/admin/api/whatsapp/:id/delete', 'AdminController', 'whatsappDeleteJson');
$router->post('/admin/api/whatsapp/:id/register', 'AdminController', 'whatsappRegisterJson');
$router->get('/admin/api/whatsapp/:id/status', 'AdminController', 'whatsappStatusJson');
$router->delete('/admin/api/whatsapp/:id', 'AdminController', 'whatsappDeleteJson');
$router->post('/admin/api/whatsapp', 'AdminController', 'whatsappCreateJson');

// JWT Tokens Admin
$router->get('/admin/jwt-tokens', 'AdminController', 'jwtTokens');
$router->get('/admin/api/jwt-tokens', 'AdminController', 'jwtTokensJson');
$router->post('/admin/api/jwt-tokens/create', 'AdminController', 'createJwtToken');
$router->post('/admin/api/jwt-tokens/revoke', 'AdminController', 'revokeJwtToken');
$router->get('/admin/api/jwt-tokens/customer/:customerId/ips', 'AdminController', 'customerIpsJson');
$router->post('/admin/api/jwt-tokens/ips/:id/flag', 'AdminController', 'flagIp');

// Rutas de herramientas
$router->any('/password-generator', 'ToolsController', 'passwordGenerator');
$router->any('/quick-hash', 'ToolsController', 'quickHash');
$router->any('/generate-password', 'ToolsController', 'generatePassword');

// ==================== STRIPE ====================
// Configuración pública (clave pública para el cliente)
$router->get('/api/customers/stripe/config', 'StripeController', 'getPublicConfig');

// Clientes
$router->post('/api/customers/stripe/customers', 'StripeController', 'createCustomer');
$router->get('/api/customers/stripe/customers/:customerId', 'StripeController', 'getCustomer');
$router->put('/api/customers/stripe/customers/:customerId', 'StripeController', 'updateCustomer');

// Tarjetas
$router->post('/api/customers/stripe/customers/:customerId/cards', 'StripeController', 'addCard');
$router->get('/api/customers/stripe/customers/:customerId/cards', 'StripeController', 'listCards');
$router->delete('/api/customers/stripe/customers/:customerId/cards/:cardId', 'StripeController', 'deleteCard');
$router->put('/api/customers/stripe/customers/:customerId/cards/:cardId/default', 'StripeController', 'setDefaultCard');

// Suscripciones
$router->post('/api/customers/stripe/customers/:customerId/subscriptions', 'StripeController', 'createSubscription');
$router->get('/api/customers/stripe/customers/:customerId/subscriptions/active', 'StripeController', 'getActiveSubscription');
$router->put('/api/customers/stripe/subscriptions/:subscriptionId', 'StripeController', 'updateSubscription');
$router->put('/api/customers/stripe/subscriptions/:subscriptionId/plan', 'StripeController', 'changePlan');

// Paquetes
$router->get('/api/customers/stripe/plans', 'StripeController', 'getplans');
$router->get('/api/customers/stripe/customers/:customerId/plan', 'StripeController', 'getCurrentPlan');

// Precios/Planes
$router->get('/api/customers/stripe/prices', 'StripeController', 'listPrices');
$router->post('/api/customers/stripe/subscriptions/:subscriptionId/preview', 'StripeController', 'previewPlanChange');
$router->post('/api/customers/stripe/customers/:customerId/subscriptions/preview', 'StripeController', 'previewNewSubscription');

// ==================== EMAIL ====================
// Estado y tipos
$router->get('/api/email/status', 'EmailController', 'status');
$router->get('/api/email/types', 'EmailController', 'types');

// Envío de correos
$router->post('/api/email/send', 'EmailController', 'send');
$router->post('/api/email/send/password-reset', 'EmailController', 'sendPasswordReset');
$router->post('/api/email/send/email-confirmation', 'EmailController', 'sendEmailConfirmation');
$router->post('/api/email/send/welcome', 'EmailController', 'sendWelcome');
$router->post('/api/email/send/notification', 'EmailController', 'sendNotification');

// Verificación de códigos
$router->post('/api/email/verify', 'EmailController', 'verify');
$router->post('/api/email/verify/password-reset', 'EmailController', 'verifyPasswordReset');
$router->post('/api/email/verify/email-confirmation', 'EmailController', 'verifyEmailConfirmation');

// Historial y estadísticas
$router->get('/api/email/history/:email', 'EmailController', 'history');
$router->get('/api/email/stats', 'EmailController', 'stats');
$router->post('/api/email/cleanup', 'EmailController', 'cleanup');

return $router;
