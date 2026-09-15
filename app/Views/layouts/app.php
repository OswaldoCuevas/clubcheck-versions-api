<?php
$layoutCurrentPath = function_exists('current_path')
    ? current_path()
    : (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$layoutCurrentPath = '/' . ltrim($layoutCurrentPath, '/');
$isAuthenticatedLayout = isset($isAuthenticated) && $isAuthenticated;
$useAdminLayout = $isAuthenticatedLayout
    && (!isset($hideNavbar) || !$hideNavbar)
    && str_starts_with($layoutCurrentPath, '/admin');

$adminApps = [];
$selectedAdminApp = null;
if ($useAdminLayout) {
    require_once __DIR__ . '/../../Models/ApplicationModel.php';
    $applicationModelForLayout = new \Models\ApplicationModel();
    $selectedAdminApp = $applicationModelForLayout->getSelectedApp();
    $adminApps = $applicationModelForLayout->all();
}

$adminTitle = isset($title) ? htmlspecialchars($title) : 'Dashboard';
$adminUserName = htmlspecialchars($currentUser['username'] ?? 'Usuario');
$adminUserRole = htmlspecialchars($currentUser['role'] ?? 'Administrador');

$days = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
$months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$timestamp = time();
$adminDate = $days[(int) date('w', $timestamp)] . ', ' . date('j', $timestamp) . ' de ' . $months[(int) date('n', $timestamp) - 1] . ' de ' . date('Y', $timestamp);

$adminSections = [
    'Operacion' => [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'fa-solid fa-chart-line'],
        ['label' => 'Clientes', 'url' => '/admin/customers', 'icon' => 'fa-solid fa-users'],
        ['label' => 'Estadisticas', 'url' => '/admin/customer-stats', 'icon' => 'fa-solid fa-chart-pie'],
        ['label' => 'Descargas', 'url' => '/admin/downloads', 'icon' => 'fa-solid fa-download'],
    ],
    'Sistema' => [
        ['label' => 'Aplicaciones', 'url' => '/admin/applications', 'icon' => 'fa-solid fa-layer-group'],
        ['label' => 'Planes Stripe', 'url' => '/admin/stripe-plans', 'icon' => 'fa-solid fa-credit-card'],
        ['label' => 'WhatsApp', 'url' => '/admin/whatsapp', 'icon' => 'fa-brands fa-whatsapp'],
        ['label' => 'Licencias', 'url' => '/admin/licenses', 'icon' => 'fa-solid fa-key'],
        ['label' => 'Tokens JWT', 'url' => '/admin/jwt-tokens', 'icon' => 'fa-solid fa-shield-halved'],
        ['label' => 'Tablas', 'url' => '/admin/desktop-tables', 'icon' => 'fa-solid fa-database'],
    ],
    'Soporte' => [
        ['label' => 'Anuncios', 'url' => '/admin/announcements', 'icon' => 'fa-solid fa-bullhorn'],
        ['label' => 'Intentos login', 'url' => '/admin/customer-login-attempts', 'icon' => 'fa-solid fa-user-lock'],
        ['label' => 'API Docs', 'url' => '/admin/api-docs', 'icon' => 'fa-solid fa-book'],
    ],
];

$isAdminNavActive = static function (array $item) use ($layoutCurrentPath): bool {
    $url = $item['url'];
    if (!empty($item['exact'])) {
        return $layoutCurrentPath === $url;
    }

    return $layoutCurrentPath === $url || str_starts_with($layoutCurrentPath, rtrim($url, '/') . '/');
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? htmlspecialchars($title) : htmlspecialchars($selectedAdminApp['name'] ?? 'Admin') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f7fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #2c3e50;
        }

        .container {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        .card {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            background: #ffffff;
        }

        .card-header {
            background: #34495e;
            color: white;
            border-radius: 4px 4px 0 0 !important;
            padding: 1.5rem;
            border-bottom: 1px solid #2c3e50;
        }

        .form-control, .form-select {
            border-radius: 4px;
            border: 1px solid #ced4da;
            padding: 0.75rem 1rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background-color: #ffffff;
        }

        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            outline: 0;
        }

        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            border-radius: 4px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
            border: 1px solid #3498db;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
            transform: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .alert {
            border-radius: 4px;
            border: 1px solid;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .btn-outline-light {
            color: #6c757d;
            border-color: #6c757d;
            background: transparent;
            border-radius: 4px;
        }

        .btn-outline-light:hover {
            color: #ffffff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .admin-shell {
            --admin-blue: #2f80ed;
            --admin-blue-dark: #1769aa;
            --admin-line: #d7eafd;
            --admin-text: #15395b;
            --admin-muted: #6b8299;
            min-height: 100vh;
            display: flex;
            background: linear-gradient(180deg, #f5faff 0%, #eef7ff 52%, #f8fbff 100%);
            color: var(--admin-text);
        }

        body.has-admin-shell,
        body.has-admin-shell * {
            scrollbar-color: #7dbcf7 #edf7ff;
            scrollbar-width: thin;
        }

        body.has-admin-shell::-webkit-scrollbar,
        body.has-admin-shell *::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        body.has-admin-shell::-webkit-scrollbar-track,
        body.has-admin-shell *::-webkit-scrollbar-track {
            background: #edf7ff;
            border-radius: 999px;
        }

        body.has-admin-shell::-webkit-scrollbar-thumb,
        body.has-admin-shell *::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #9bd2ff, #2f80ed);
            border: 2px solid #edf7ff;
            border-radius: 999px;
        }

        body.has-admin-shell::-webkit-scrollbar-thumb:hover,
        body.has-admin-shell *::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #75bdff, #1769aa);
        }

        .admin-sidebar {
            position: sticky;
            top: 0;
            width: 268px;
            height: 100vh;
            flex: 0 0 268px;
            padding: 24px 18px;
            overflow-y: auto;
            background: rgba(255,255,255,0.84);
            border-right: 1px solid var(--admin-line);
            box-shadow: 12px 0 34px rgba(47, 128, 237, 0.07);
            backdrop-filter: blur(14px);
        }

        .admin-mobile-menu,
        .admin-sidebar-backdrop {
            display: none;
        }

        .admin-brand {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 44px;
            margin-bottom: 26px;
            color: var(--admin-text);
            font-size: 24px;
            font-weight: 700;
            text-decoration: none;
            letter-spacing: 0;
        }

        .admin-brand:hover {
            color: var(--admin-blue-dark);
            text-decoration: none;
        }

        .admin-brand-mark {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: linear-gradient(145deg, #dff0ff, #ffffff);
            color: var(--admin-blue);
            box-shadow: inset 0 0 0 1px #c7e2fb;
        }

        .admin-app-switcher {
            margin-bottom: 24px;
        }

        .admin-app-label {
            display: block;
            padding: 0 8px 8px;
            color: #7d92a8;
            font-size: 12px;
            font-weight: 700;
        }

        .admin-app-select-wrap {
            position: relative;
        }

        .admin-app-current {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 48px;
            padding: 7px 38px 7px 9px;
            border: 1px solid var(--admin-line);
            border-radius: 10px;
            background: #f7fbff;
            color: var(--admin-text);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.72);
        }

        .admin-app-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            flex: 0 0 34px;
            border-radius: 8px;
            background: #e2f1ff;
            color: var(--admin-blue);
        }

        .admin-app-name {
            display: block;
            min-width: 0;
            overflow: hidden;
            color: var(--admin-text);
            font-weight: 700;
            line-height: 1.1;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-app-caption {
            display: block;
            margin-top: 2px;
            color: var(--admin-muted);
            font-size: 12px;
            line-height: 1.1;
        }

        .admin-app-select {
            position: absolute;
            inset: 0;
            width: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .admin-app-chevron {
            position: absolute;
            top: 50%;
            right: 13px;
            color: #4d8ed0;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .admin-nav-section {
            margin: 22px 0;
        }

        .admin-nav-title {
            display: block;
            padding: 0 8px 8px;
            color: #7d92a8;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0;
        }

        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 44px;
            margin: 4px 0;
            padding: 0 12px;
            border: 1px solid transparent;
            border-radius: 8px;
            color: #315574;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.16s ease, color 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
        }

        .admin-nav-link i {
            width: 20px;
            text-align: center;
            color: #4d8ed0;
        }

        .admin-nav-link:hover {
            color: var(--admin-blue-dark);
            background: #f1f8ff;
            border-color: #d3e8fb;
            text-decoration: none;
        }

        .admin-nav-link.active {
            color: #ffffff;
            background: var(--admin-blue);
            box-shadow: 0 12px 26px rgba(47, 128, 237, 0.18);
        }

        .admin-nav-link.active i {
            color: #ffffff;
        }

        .admin-sidebar-footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid var(--admin-line);
        }

        .admin-main {
            min-width: 0;
            flex: 1;
            padding: 28px 32px 44px;
        }

        .admin-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            max-width: 1240px;
            margin: 0 auto 22px;
        }

        .admin-page-title {
            margin: 0;
            color: var(--admin-text);
            font-size: clamp(26px, 3vw, 36px);
            font-weight: 700;
            letter-spacing: 0;
        }

        .admin-page-date {
            margin-top: 5px;
            color: var(--admin-muted);
            font-size: 15px;
            font-weight: 500;
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .admin-icon-btn,
        .admin-user-avatar {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            flex: 0 0 44px;
            border-radius: 50%;
            border: 1px solid var(--admin-line);
            background: rgba(255,255,255,0.92);
            color: var(--admin-blue-dark);
            box-shadow: 0 10px 22px rgba(47, 128, 237, 0.10);
        }

        .admin-user-name,
        .admin-user-role {
            display: block;
        }

        .admin-user-name {
            color: var(--admin-text);
            font-weight: 700;
            line-height: 1.1;
            white-space: nowrap;
        }

        .admin-user-role {
            color: var(--admin-muted);
            font-size: 13px;
            white-space: nowrap;
        }

        .admin-content {
            min-width: 0;
        }

        .admin-content > .container,
        .admin-content > .container-fluid {
            max-width: 1240px;
            padding-top: 0;
        }

        @media (max-width: 991.98px) {
            .admin-shell {
                display: block;
            }

            .admin-mobile-menu {
                position: fixed;
                z-index: 45;
                top: 18px;
                left: 14px;
                width: 48px;
                height: 48px;
                display: grid;
                place-items: center;
                border: 1px solid var(--admin-line);
                border-radius: 50%;
                background: rgba(255,255,255,0.96);
                color: var(--admin-text);
                box-shadow: 0 12px 26px rgba(47, 128, 237, 0.16);
            }

            .admin-sidebar-backdrop {
                position: fixed;
                inset: 0;
                z-index: 35;
                display: block;
                visibility: hidden;
                opacity: 0;
                background: rgba(21, 57, 91, 0.22);
                transition: opacity 0.18s ease, visibility 0.18s ease;
            }

            body.admin-menu-open .admin-sidebar-backdrop {
                visibility: visible;
                opacity: 1;
            }

            .admin-sidebar {
                position: fixed;
                z-index: 40;
                top: 0;
                left: 0;
                width: min(82vw, 310px);
                height: 100vh;
                padding: 22px 18px;
                border-right: 1px solid var(--admin-line);
                border-bottom: 0;
                transform: translateX(-105%);
                transition: transform 0.22s ease;
            }

            body.admin-menu-open .admin-sidebar {
                transform: translateX(0);
            }

            .admin-brand {
                margin-bottom: 10px;
                font-size: 20px;
            }

            .admin-app-switcher {
                margin-bottom: 10px;
            }

            .admin-nav {
                display: block;
            }

            .admin-nav-section {
                margin: 18px 0;
            }

            .admin-nav-title {
                display: block;
            }

            .admin-nav-link {
                min-height: 44px;
            }

            .admin-main {
                padding: 24px 14px 36px;
            }

            .admin-topbar {
                align-items: flex-start;
                margin-bottom: 16px;
                padding-left: 64px;
            }

            .admin-user-meta {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .admin-topbar {
                min-height: 54px;
                align-items: center;
                justify-content: flex-end;
            }

            .admin-topbar > div:first-child {
                display: none;
            }

            .admin-user {
                justify-content: flex-end;
            }
        }

        <?= isset($customStyles) ? $customStyles : '' ?>
    </style>
</head>
<body class="<?= $useAdminLayout ? 'has-admin-shell' : '' ?>">
    <?php if ($useAdminLayout): ?>
        <div class="admin-shell">
            <button type="button" class="admin-mobile-menu" id="adminMobileMenuBtn" aria-label="Abrir menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="admin-sidebar-backdrop" id="adminSidebarBackdrop"></div>

            <aside class="admin-sidebar" id="adminSidebar">

                <form class="admin-app-switcher" method="post" action="<?= app_url('/admin/app/select') ?>">
                    <label class="admin-app-label" for="adminAppSelect">Aplicacion</label>
                    <div class="admin-app-select-wrap">
                        <div class="admin-app-current">
                            <span class="admin-app-icon" style="color: <?= htmlspecialchars($selectedAdminApp['color'] ?? '#2f80ed') ?>">
                                <i class="<?= htmlspecialchars($selectedAdminApp['iconClass'] ?? 'fa-solid fa-layer-group') ?>"></i>
                            </span>
                            <span class="min-w-0">
                                <span class="admin-app-name"><?= htmlspecialchars($selectedAdminApp['name'] ?? 'Aplicacion') ?></span>
                                <span class="admin-app-caption">Cambiar contexto</span>
                            </span>
                        </div>
                        <select class="admin-app-select" id="adminAppSelect" name="appId" onchange="this.form.submit()">
                            <?php foreach ($adminApps as $app): ?>
                                <option value="<?= htmlspecialchars($app['id']) ?>" <?= (($selectedAdminApp['id'] ?? null) === $app['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($app['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down admin-app-chevron"></i>
                    </div>
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($layoutCurrentPath) ?>">
                </form>

                <nav class="admin-nav" aria-label="Navegacion administrativa">
                    <?php foreach ($adminSections as $sectionLabel => $items): ?>
                        <div class="admin-nav-section">
                            <span class="admin-nav-title"><?= htmlspecialchars($sectionLabel) ?></span>
                            <?php foreach ($items as $item): ?>
                                <a class="admin-nav-link <?= $isAdminNavActive($item) ? 'active' : '' ?>" href="<?= app_url($item['url']) ?>">
                                    <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                                    <span><?= htmlspecialchars($item['label']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </nav>

                <div class="admin-sidebar-footer">
                    <a class="admin-nav-link" href="<?= app_url('/logout') ?>">
                        <i class="fas fa-arrow-right-from-bracket"></i>
                        <span>Cerrar sesion</span>
                    </a>
                </div>
            </aside>

            <main class="admin-main">
                <header class="admin-topbar">
                    <div>
                        <h1 class="admin-page-title"><?= $adminTitle ?></h1>
                        <div class="admin-page-date"><?= htmlspecialchars($adminDate) ?></div>
                    </div>
                    <div class="admin-user">
                        <span class="admin-icon-btn" title="Notificaciones"><i class="far fa-bell"></i></span>
                        <span class="admin-user-avatar" title="<?= $adminUserName ?>"><i class="far fa-user"></i></span>
                        <span class="admin-user-meta">
                            <span class="admin-user-name"><?= $adminUserName ?></span>
                            <span class="admin-user-role"><?= $adminUserRole ?></span>
                        </span>
                    </div>
                </header>
                <div class="admin-content">
                    <?= $content ?? '' ?>
                </div>
            </main>
        </div>
    <?php elseif (!isset($hideNavbar) || !$hideNavbar): ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
            <div class="container">
                <a class="navbar-brand" href="<?= app_url('/') ?>">
                    <i class="fas fa-cloud-upload-alt me-2"></i>
                    Versiones
                </a>
                <div class="navbar-nav ms-auto">
                    <?php if ($isAuthenticatedLayout): ?>
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i>
                                <?= htmlspecialchars($currentUser['username'] ?? 'Usuario') ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">
                                    <i class="fas fa-id-badge me-2"></i>
                                    <?= htmlspecialchars($currentUser['role'] ?? 'Usuario') ?>
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= app_url('/logout') ?>">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    Cerrar Sesion
                                </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a class="nav-link" href="<?= app_url('/login') ?>">
                            <i class="fas fa-sign-in-alt me-1"></i>
                            Iniciar Sesion
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <?php if (!$useAdminLayout): ?>
        <?= $content ?? '' ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?= isset($customScripts) ? $customScripts : '' ?>

    <script>
        (function() {
            const menuButton = document.getElementById('adminMobileMenuBtn');
            const backdrop = document.getElementById('adminSidebarBackdrop');
            const sidebar = document.getElementById('adminSidebar');
            if (!menuButton || !backdrop || !sidebar) {
                return;
            }

            const setOpen = (open) => {
                document.body.classList.toggle('admin-menu-open', open);
                menuButton.setAttribute('aria-label', open ? 'Cerrar menu' : 'Abrir menu');
                menuButton.innerHTML = open ? '<i class="fas fa-xmark"></i>' : '<i class="fas fa-bars"></i>';
            };

            menuButton.addEventListener('click', () => setOpen(!document.body.classList.contains('admin-menu-open')));
            backdrop.addEventListener('click', () => setOpen(false));
            sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
        })();

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 8000);
    </script>
</body>
</html>
