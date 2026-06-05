<?php
$title = 'Intentos de Login Web - ClubCheck';

$attemptRows = $attempts['data'] ?? [];
$pagination = $attempts['pagination'] ?? ['page' => 1, 'perPage' => 50, 'total' => 0, 'totalPages' => 1];
$filters = $filters ?? [];
$summary = $summary ?? [];

function web_login_attempt_filter(array $filters, string $key): string
{
    return htmlspecialchars((string) ($filters[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

function web_login_attempt_badge(bool $wasSuccessful): string
{
    return $wasSuccessful
        ? '<span class="badge bg-success">Exitoso</span>'
        : '<span class="badge bg-danger">Fallido</span>';
}

ob_start();
?>

<div class="container mt-4">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-right-to-bracket me-2"></i>
                Intentos de login web
            </h1>
            <p class="text-muted mb-0">Registros del endpoint /api/desktop/login por usuario y codeAccess.</p>
        </div>
        <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="metric-card">
                <span>Total</span>
                <strong><?= (int) ($summary['total'] ?? 0) ?></strong>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <span>Exitosos</span>
                <strong class="text-success"><?= (int) ($summary['successful'] ?? 0) ?></strong>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <span>Fallidos</span>
                <strong class="text-danger"><?= (int) ($summary['failed'] ?? 0) ?></strong>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <span>Fallidos ultimos 10 min</span>
                <strong class="text-warning"><?= (int) ($summary['failedLast10Minutes'] ?? 0) ?></strong>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="get" action="<?= app_url('/admin/customer-login-attempts') ?>">
                <div class="col-lg-4">
                    <label class="form-label" for="search">Buscar</label>
                    <input type="search" class="form-control" id="search" name="search" value="<?= web_login_attempt_filter($filters, 'search') ?>" placeholder="Usuario, codeAccess, IP o cliente">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="status">Estado</label>
                    <?php $status = (string) ($filters['status'] ?? 'all'); ?>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Fallidos</option>
                        <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Exitosos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="codeAccess">CodeAccess</label>
                    <input type="text" class="form-control" id="codeAccess" name="codeAccess" value="<?= web_login_attempt_filter($filters, 'codeAccess') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="from">Desde</label>
                    <input type="date" class="form-control" id="from" name="from" value="<?= web_login_attempt_filter($filters, 'from') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="to">Hasta</label>
                    <input type="date" class="form-control" id="to" name="to" value="<?= web_login_attempt_filter($filters, 'to') ?>">
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end">
                    <a href="<?= app_url('/admin/customer-login-attempts') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-rotate-left me-2"></i>Limpiar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h2 class="h5 mb-0">Historial</h2>
            <span class="badge bg-light text-dark"><?= (int) ($pagination['total'] ?? 0) ?> registros</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Login</th>
                        <th>CodeAccess</th>
                        <th>Cliente</th>
                        <th>Administrador</th>
                        <th>IP</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attemptRows)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">No hay intentos con los filtros actuales.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($attemptRows as $attempt): ?>
                        <tr>
                            <td class="text-nowrap"><?= htmlspecialchars((string) ($attempt['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= web_login_attempt_badge((bool) ($attempt['wasSuccessful'] ?? false)) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars((string) ($attempt['loginIdentifier'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars((string) ($attempt['codeAccess'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td>
                                <div><?= htmlspecialchars((string) ($attempt['customerName'] ?? 'Sin cliente'), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($attempt['customerId'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars((string) $attempt['customerId'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars((string) ($attempt['adminUsername'] ?? 'Sin usuario'), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($attempt['adminEmail'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars((string) $attempt['adminEmail'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="text-nowrap"><?= htmlspecialchars((string) ($attempt['ipAddress'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php if (!empty($attempt['userAgent'])): ?>
                                    <small class="text-muted user-agent-text"><?= htmlspecialchars((string) $attempt['userAgent'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-muted"><?= htmlspecialchars((string) ($attempt['failureReason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span class="text-muted">
                Pagina <?= (int) ($pagination['page'] ?? 1) ?> de <?= max(1, (int) ($pagination['totalPages'] ?? 1)) ?>
            </span>
            <?php
                $query = $_GET;
                $currentPage = (int) ($pagination['page'] ?? 1);
                $totalPages = max(1, (int) ($pagination['totalPages'] ?? 1));
                $query['page'] = max(1, $currentPage - 1);
                $previousUrl = app_url('/admin/customer-login-attempts?' . http_build_query($query));
                $query['page'] = min($totalPages, $currentPage + 1);
                $nextUrl = app_url('/admin/customer-login-attempts?' . http_build_query($query));
            ?>
            <div class="btn-group">
                <a class="btn btn-outline-secondary <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= $previousUrl ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a class="btn btn-outline-secondary <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" href="<?= $nextUrl ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$customStyles = <<<CSS
.metric-card {
    background: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 1rem;
    min-height: 92px;
}

.metric-card span {
    display: block;
    color: #6c757d;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.metric-card strong {
    display: block;
    margin-top: 0.35rem;
    font-size: 1.75rem;
    line-height: 1.1;
}

.user-agent-text {
    display: block;
    max-width: 240px;
    overflow-wrap: anywhere;
}
CSS;
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
