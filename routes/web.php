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
$router->any('/api/customers/:customerId', 'CustomersController', 'getCustomer');
$router->any('/api/customers/token', 'CustomersController', 'customerToken');
$router->any('/api/customers/token/register', 'CustomersController', 'registerToken');
$router->any('/api/customers/token/await', 'CustomersController', 'awaitToken');

// Rutas administrativas
$router->any('/admin', 'AdminController', 'index');
$router->get('/admin/customers', 'AdminController', 'customers');
$router->get('/admin/api/customers', 'AdminController', 'customersJson');
$router->get('/admin/api-docs', 'AdminController', 'apiDocs');

// Rutas de herramientas
$router->any('/password-generator', 'ToolsController', 'passwordGenerator');
$router->any('/quick-hash', 'ToolsController', 'quickHash');
$router->any('/generate-password', 'ToolsController', 'generatePassword');

return $router;
