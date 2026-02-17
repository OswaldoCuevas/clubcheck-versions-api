<?php
$title = 'Panel Administrativo - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">
                            <i class="fas fa-cogs me-2"></i>
                            Panel de Administración
                        </h4>
                        <small class="text-light opacity-75">Bienvenido de nuevo, <?= htmlspecialchars($currentUser['name'] ?? $currentUser['username']) ?></small>
                    </div>
                    <span class="badge bg-success text-uppercase">Sesión activa</span>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="border rounded p-3 h-100">
                                <h6 class="text-muted text-uppercase fw-semibold mb-3">Tu sesión</h6>
                                <ul class="list-unstyled mb-0">
                                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <span class="text-secondary">
                                            <i class="fas fa-user me-2"></i>Usuario
                                        </span>
                                        <strong><?= htmlspecialchars($currentUser['username']) ?></strong>
                                    </li>
                                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <span class="text-secondary">
                                            <i class="fas fa-user-shield me-2"></i>Rol
                                        </span>
                                        <strong class="text-capitalize"><?= htmlspecialchars($currentUser['role']) ?></strong>
                                    </li>
                                    <li class="d-flex justify-content-between align-items-center py-2">
                                        <span class="text-secondary">
                                            <i class="fas fa-clock me-2"></i>Inicio sesión
                                        </span>
                                        <strong><?= isset($currentUser['login_time']) ? date('Y-m-d H:i:s', $currentUser['login_time']) : date('Y-m-d H:i:s') ?></strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="border rounded p-3 h-100">
                                <h6 class="text-muted text-uppercase fw-semibold mb-3">Accesos rápidos</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card h-100 border-0 shadow-sm hover-shadow">
                                            <div class="card-body d-flex flex-column">
                                                <div class="d-flex align-items-center mb-3">
                                                    <span class="icon-circle bg-primary text-white me-3">
                                                        <i class="fas fa-upload"></i>
                                                    </span>
                                                    <div>
                                                        <h5 class="card-title mb-1">Gestionar Versiones</h5>
                                                        <small class="text-muted">Sube y publica nuevas versiones del instalador</small>
                                                    </div>
                                                </div>
                                                <a href="<?= app_url('/') ?>" class="btn btn-primary mt-auto">
                                                    Ir al gestor <i class="fas fa-arrow-right ms-2"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card h-100 border-0 shadow-sm hover-shadow">
                                            <div class="card-body d-flex flex-column">
                                                <div class="d-flex align-items-center mb-3">
                                                    <span class="icon-circle bg-success text-white me-3">
                                                        <i class="fas fa-users"></i>
                                                    </span>
                                                    <div>
                                                        <h5 class="card-title mb-1">Clientes y tokens</h5>
                                                        <small class="text-muted">Administra IDs, tokens y solicitudes de cambio</small>
                                                    </div>
                                                </div>
                                                    <a href="<?= app_url('/admin/customers') ?>" class="btn btn-success mt-auto">
                                                        Abrir panel <i class="fas fa-users-gear ms-2"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card h-100 border-0 shadow-sm hover-shadow">
                                            <div class="card-body d-flex flex-column">
                                                <div class="d-flex align-items-center mb-3">
                                                    <span class="icon-circle bg-info text-white me-3">
                                                        <i class="fas fa-book"></i>
                                                    </span>
                                                    <div>
                                                        <h5 class="card-title mb-1">Referencia API</h5>
                                                        <small class="text-muted">Consulta endpoints y ejemplos listos para copiar</small>
                                                    </div>
                                                </div>
                                                <a href="<?= app_url('/admin/api-docs') ?>" class="btn btn-info text-white mt-auto">
                                                    Abrir documentación <i class="fas fa-book-open ms-2"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card h-100 border-0 shadow-sm hover-shadow">
                                            <div class="card-body d-flex flex-column">
                                                <div class="d-flex align-items-center mb-3">
                                                    <span class="icon-circle bg-warning text-white me-3">
                                                        <i class="fas fa-key"></i>
                                                    </span>
                                                    <div>
                                                        <h5 class="card-title mb-1">Generador de contraseñas</h5>
                                                        <small class="text-muted">Crea credenciales seguras para clientes</small>
                                                    </div>
                                                </div>
                                                <a href="<?= app_url('/password-generator') ?>" class="btn btn-warning mt-auto">
                                                    Abrir herramienta <i class="fas fa-wand-magic-sparkles ms-2"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card h-100 border-0 shadow-sm hover-shadow">
                                            <div class="card-body d-flex flex-column">
                                                <div class="d-flex align-items-center mb-3">
                                                    <span class="icon-circle bg-secondary text-white me-3">
                                                        <i class="fas fa-folder-open"></i>
                                                    </span>
                                                    <div>
                                                        <h5 class="card-title mb-1">Repositorio de archivos</h5>
                                                        <small class="text-muted">Explora los instaladores y recursos cargados</small>
                                                    </div>
                                                </div>
                                                <a href="<?= app_url('/uploads/') ?>" class="btn btn-outline-secondary mt-auto">
                                                    Ver archivos <i class="fas fa-folder-tree ms-2"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$customStyles = <<<CSS
.icon-circle {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 1.25rem;
}

.hover-shadow {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.hover-shadow:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 20px rgba(0, 0, 0, 0.12);
}
CSS;
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
