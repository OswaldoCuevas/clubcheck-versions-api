<?php

use Core\Router;

$router = new Router();

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

// Rutas administrativas
$router->any('/admin', 'AdminController', 'index');
$router->get('/admin/customers', 'AdminController', 'customers');
$router->get('/admin/api/customers', 'AdminController', 'customersJson');
$router->post('/admin/api/customers/regenerate-access-key', 'AdminController', 'regenerateAccessKey');
$router->get('/admin/api-docs', 'AdminController', 'apiDocs');

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

// Precios/Planes
$router->get('/api/customers/stripe/prices', 'StripeController', 'listPrices');
$router->post('/api/customers/stripe/subscriptions/:subscriptionId/preview', 'StripeController', 'previewPlanChange');
$router->post('/api/customers/stripe/customers/:customerId/subscriptions/preview', 'StripeController', 'previewNewSubscription');

return $router;
