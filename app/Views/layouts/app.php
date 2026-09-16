<?php
require_once __DIR__ . '/../components/action-menu.php';
require_once __DIR__ . '/../components/filter-panel.php';
require_once __DIR__ . '/../components/pagination.php';
require_once __DIR__ . '/../components/toast.php';

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
        ['label' => 'Versiones', 'url' => '/admin/versions', 'icon' => 'fa-solid fa-cloud-arrow-up'],
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

        .form-control,
        .form-select {
            min-height: 48px;
            border-radius: 12px;
            border: 1px solid #d7eafd;
            padding: 1rem 1rem 0.55rem;
            background-color: #ffffff;
            color: #0f2740;
            font-weight: 600;
            box-shadow: 0 8px 22px rgba(47, 128, 237, 0.05);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out, background 0.15s ease-in-out;
        }

        textarea.form-control {
            min-height: 82px;
        }

        .form-control::placeholder {
            color: #7d92a8;
            font-weight: 500;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #1299dc;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(18, 153, 220, 0.12), 0 10px 24px rgba(47, 128, 237, 0.08);
            outline: 0;
        }

        .form-label {
            margin-bottom: 0.35rem;
            color: #315574;
            font-size: 12px;
            font-weight: 700;
        }

        .input-shell {
            position: relative;
        }

        .input-shell > .form-label,
        .input-shell > label {
            position: absolute;
            z-index: 2;
            top: 7px;
            left: 13px;
            margin: 0;
            color: #315574;
            font-size: 11px;
            line-height: 1;
            pointer-events: none;
        }

        .input-shell > .form-control,
        .input-shell > .form-select {
            width: 100%;
        }

        .input-shell > .form-control:not(textarea),
        .input-shell > .form-select {
            height: 58px;
        }

        .btn {
            border-radius: 11px;
            font-weight: 700;
        }

        .btn-primary,
        .btn-success {
            min-height: 42px;
            background-color: #1299dc;
            border-color: #1299dc;
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(18, 153, 220, 0.16);
            transition: all 0.15s ease-in-out;
        }

        .btn-primary:hover,
        .btn-success:hover {
            background-color: #0b88c5;
            border-color: #0b88c5;
            color: #ffffff;
            box-shadow: 0 14px 28px rgba(18, 153, 220, 0.22);
        }

        .btn-secondary,
        .btn-outline-secondary {
            min-height: 42px;
            background: #ffffff;
            border-color: #bde2f8;
            color: #087cba;
        }

        .btn-secondary:hover,
        .btn-outline-secondary:hover {
            background: #eaf8ff;
            border-color: #8cd4f4;
            color: #075f8e;
        }

        .btn-outline-primary {
            min-height: 42px;
            background: #ffffff;
            border-color: #bde2f8;
            color: #087cba;
        }

        .btn-outline-primary:hover {
            background: #eaf8ff;
            border-color: #8cd4f4;
            color: #075f8e;
        }

        .btn-outline-danger {
            background: #ffffff;
            border-color: #ffd0d7;
            color: #c62840;
        }

        .btn-outline-danger:hover {
            background: #fff1f3;
            border-color: #ff9aaa;
            color: #9f1f34;
        }

        .admin-form-panel {
            padding: 18px;
            border: 1px solid #d7eafd;
            border-radius: 14px;
            background: rgba(255,255,255,0.96);
            box-shadow: 0 18px 38px rgba(47, 128, 237, 0.09);
        }

        .admin-form-panel-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .admin-form-panel-title h2,
        .admin-form-panel-title h3 {
            margin: 0;
            color: #15395b;
            font-size: 18px;
            font-weight: 800;
        }

        .admin-search-select {
            position: relative;
        }

        .admin-search-select-control {
            width: 100%;
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 1rem 1rem 0.55rem;
            border: 1px solid #d7eafd;
            border-radius: 12px;
            background: #ffffff;
            color: #0f2740;
            font-weight: 700;
            text-align: left;
            box-shadow: 0 8px 22px rgba(47, 128, 237, 0.05);
        }

        .admin-search-select.open .admin-search-select-control {
            border-color: #1299dc;
            box-shadow: 0 0 0 3px rgba(18, 153, 220, 0.12), 0 10px 24px rgba(47, 128, 237, 0.08);
        }

        .admin-search-select-label {
            position: absolute;
            top: 7px;
            left: 13px;
            z-index: 2;
            color: #315574;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
        }

        .admin-search-select-menu {
            position: fixed;
            z-index: 1080;
            display: none;
            max-height: min(360px, calc(100vh - 24px));
            overflow-y: auto;
            padding: 8px;
            border: 1px solid #d7eafd;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 18px 40px rgba(47, 128, 237, 0.16);
            scrollbar-color: #1299dc #eaf8ff;
        }

        .admin-search-select-menu.open {
            display: block;
        }

        .admin-search-select-menu::-webkit-scrollbar {
            width: 8px;
        }

        .admin-search-select-menu::-webkit-scrollbar-track {
            background: #eaf8ff;
            border-radius: 999px;
        }

        .admin-search-select-menu::-webkit-scrollbar-thumb {
            background: #1299dc;
            border-radius: 999px;
        }

        .admin-search-select-search {
            width: 100%;
            height: 44px;
            margin-bottom: 6px;
            padding: 0 12px;
            border: 1px solid #d7eafd;
            border-radius: 10px;
            background: #f8fbff;
        }

        .admin-search-select-option,
        .admin-search-select-more {
            width: 100%;
            min-height: 38px;
            display: flex;
            align-items: center;
            padding: 0 10px;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: #15395b;
            text-align: left;
            font-weight: 600;
        }

        .admin-search-select-option:hover,
        .admin-search-select-option.active {
            background: #eaf8ff;
        }

        .admin-search-select-more {
            justify-content: center;
            margin-top: 6px;
            color: #087cba;
            background: #f2fbff;
            font-weight: 800;
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

        <?= function_exists('admin_action_menu_styles') ? admin_action_menu_styles() : '' ?>
        <?= function_exists('admin_filter_panel_styles') ? admin_filter_panel_styles() : '' ?>
        <?= function_exists('admin_pagination_styles') ? admin_pagination_styles() : '' ?>
        <?= function_exists('admin_toast_styles') ? admin_toast_styles() : '' ?>
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
    <?= function_exists('admin_action_menu_scripts') ? admin_action_menu_scripts() : '' ?>
    <?= function_exists('admin_filter_panel_scripts') ? admin_filter_panel_scripts() : '' ?>
    <?= function_exists('admin_pagination_scripts') ? admin_pagination_scripts() : '' ?>
    <?= function_exists('admin_toast_scripts') ? admin_toast_scripts() : '' ?>

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

        window.AdminUI = window.AdminUI || {};

        window.AdminUI.formatCurrency = function(value, currency = 'MXN') {
            const amount = Number(value || 0);
            return amount.toLocaleString('es-MX', { style: 'currency', currency });
        };

        window.AdminUI.currencyToNumber = function(value) {
            const normalized = String(value || '').replace(/[^\d.-]/g, '');
            const amount = Number(normalized);
            return Number.isFinite(amount) ? amount : 0;
        };

        window.AdminUI.currencyToCents = function(value) {
            return Math.round(window.AdminUI.currencyToNumber(value) * 100);
        };

        window.AdminUI.setCurrencyFromCents = function(input, cents, currency = 'MXN') {
            if (!input) return;
            input.value = window.AdminUI.formatCurrency(Number(cents || 0) / 100, currency);
        };

        window.AdminUI.initCurrencyInputs = function(scope = document) {
            scope.querySelectorAll('[data-currency-input]').forEach((input) => {
                if (input.dataset.currencyReady === '1') return;
                input.dataset.currencyReady = '1';
                const currency = input.dataset.currency || 'MXN';
                const format = () => {
                    input.value = window.AdminUI.formatCurrency(window.AdminUI.currencyToNumber(input.value), currency);
                };

                input.addEventListener('focus', () => {
                    const value = window.AdminUI.currencyToNumber(input.value);
                    input.value = value ? String(value) : '';
                    input.select();
                });
                input.addEventListener('blur', format);
                if (input.value !== '') format();
            });
        };

        window.AdminUI.initSearchSelects = function(scope = document) {
            scope.querySelectorAll('select[data-search-select]').forEach((select) => {
                if (select.dataset.searchReady === '1') return;
                select.dataset.searchReady = '1';
                const pageSize = Number(select.dataset.pageSize || 10);
                const label = select.dataset.label || select.getAttribute('aria-label') || 'Seleccionar';
                const wrapper = document.createElement('div');
                wrapper.className = 'admin-search-select';
                const labelEl = document.createElement('span');
                labelEl.className = 'admin-search-select-label';
                labelEl.textContent = label;
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'admin-search-select-control';
                const menu = document.createElement('div');
                menu.className = 'admin-search-select-menu';
                const search = document.createElement('input');
                search.type = 'search';
                search.className = 'admin-search-select-search';
                search.placeholder = 'Buscar...';
                const list = document.createElement('div');
                const more = document.createElement('button');
                more.type = 'button';
                more.className = 'admin-search-select-more';
                more.textContent = 'Ver mas';
                let visible = pageSize;
                let query = '';
                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[ch]));

                select.classList.add('d-none');
                select.parentNode.insertBefore(wrapper, select);
                wrapper.appendChild(labelEl);
                wrapper.appendChild(button);
                document.body.appendChild(menu);
                menu.appendChild(search);
                menu.appendChild(list);
                menu.appendChild(more);
                wrapper.appendChild(select);

                const options = () => Array.from(select.options).filter((option) => option.value !== '');
                const selectedText = () => select.options[select.selectedIndex]?.text || label;
                const syncButton = () => {
                    button.innerHTML = `<span>${selectedText()}</span><i class="fas fa-chevron-down"></i>`;
                };
                const positionMenu = () => {
                    const rect = button.getBoundingClientRect();
                    const gap = 8;
                    const availableBelow = window.innerHeight - rect.bottom - gap;
                    const availableAbove = rect.top - gap;
                    const openAbove = availableBelow < 180 && availableAbove > availableBelow;
                    const available = openAbove ? availableAbove : availableBelow;
                    const preferredHeight = Math.min(360, Math.max(180, available - gap));
                    menu.style.left = `${rect.left}px`;
                    menu.style.width = `${rect.width}px`;
                    menu.style.maxHeight = `${Math.max(160, preferredHeight)}px`;
                    menu.style.top = openAbove
                        ? `${Math.max(gap, rect.top - Math.max(160, preferredHeight) - gap)}px`
                        : `${rect.bottom + gap}px`;
                };
                const openMenu = () => {
                    document.querySelectorAll('.admin-search-select-menu.open').forEach((openMenu) => {
                        if (openMenu !== menu) openMenu.classList.remove('open');
                    });
                    document.querySelectorAll('.admin-search-select.open').forEach((openWrapper) => {
                        if (openWrapper !== wrapper) openWrapper.classList.remove('open');
                    });
                    wrapper.classList.add('open');
                    menu.classList.add('open');
                    positionMenu();
                    search.focus();
                };
                const closeMenu = () => {
                    wrapper.classList.remove('open');
                    menu.classList.remove('open');
                };
                const render = () => {
                    const filtered = options().filter((option) => option.text.toLowerCase().includes(query.toLowerCase()));
                    list.innerHTML = filtered.slice(0, visible).map((option) => `
                        <button type="button" class="admin-search-select-option ${option.selected ? 'active' : ''}" data-value="${escapeHtml(option.value)}">
                            ${escapeHtml(option.text)}
                        </button>
                    `).join('') || '<div class="px-2 py-2 text-muted">Sin resultados</div>';
                    more.style.display = filtered.length > visible ? 'flex' : 'none';
                    syncButton();
                };

                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (menu.classList.contains('open')) {
                        closeMenu();
                        return;
                    }
                    openMenu();
                });
                search.addEventListener('input', () => {
                    query = search.value;
                    visible = pageSize;
                    render();
                });
                more.addEventListener('click', () => {
                    visible += pageSize;
                    render();
                });
                list.addEventListener('click', (event) => {
                    const optionButton = event.target.closest('.admin-search-select-option');
                    if (!optionButton) return;
                    select.value = optionButton.dataset.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    closeMenu();
                    render();
                });
                document.addEventListener('click', (event) => {
                    if (!wrapper.contains(event.target) && !menu.contains(event.target)) closeMenu();
                });
                window.addEventListener('resize', () => {
                    if (menu.classList.contains('open')) positionMenu();
                });
                window.addEventListener('scroll', () => {
                    if (menu.classList.contains('open')) positionMenu();
                }, true);
                select.addEventListener('change', render);
                render();
            });
        };

        document.addEventListener('DOMContentLoaded', function() {
            window.AdminUI.initCurrencyInputs();
            window.AdminUI.initSearchSelects();
        });

        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 8000);
    </script>
    <?= isset($customScripts) ? $customScripts : '' ?>
</body>
</html>
