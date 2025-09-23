<?php
$title = 'Panel Administrativo - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fas fa-cogs me-2"></i>
                        Panel de Administración
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        ¡Acceso autorizado! Solo los administradores pueden ver esta página.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Información del Usuario</h5>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Usuario:</span>
                                    <strong><?= htmlspecialchars($currentUser['username']) ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Rol:</span>
                                    <strong><?= htmlspecialchars($currentUser['role']) ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>Último acceso:</span>
                                    <strong><?= date('Y-m-d H:i:s') ?></strong>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5>Acciones Administrativas</h5>
                            <div class="d-grid gap-2">
                                        <a href="<?= app_url('/') ?>" class="btn btn-secondary mb-3">
                                    <i class="fas fa-upload me-2"></i>
                                    Gestionar Versiones
                                </a>
                                <a href="/clubcheck/password-generator" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>
                                    Generar Contraseñas
                                </a>
                                <a href="/clubcheck/uploads/" class="btn btn-outline-secondary">
                                    <i class="fas fa-folder me-2"></i>
                                    Ver Archivos
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
