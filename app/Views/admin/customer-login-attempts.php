<?php
$title = 'Intentos de Login Web';

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

    <?php $status = (string) ($filters['status'] ?? 'all'); ?>
    <div id="loginAttemptsFilter"></div>

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
        <div class="card-footer">
            <?php
                $query = $_GET;
                $currentPage = (int) ($pagination['page'] ?? 1);
                $totalPages = max(1, (int) ($pagination['totalPages'] ?? 1));
                $pageUrl = static function (int $page) use ($query): string {
                    $query['page'] = $page;
                    return app_url('/admin/customer-login-attempts?' . http_build_query($query));
                };
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
            ?>
            <nav class="admin-pagination" aria-label="Paginacion de intentos de login">
                <span class="admin-pagination-summary">Mostrando <?= count($attemptRows) ?> de <?= (int) ($pagination['total'] ?? 0) ?> registros</span>
                <div class="admin-pagination-controls">
                <a class="admin-pagination-button <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= $pageUrl(max(1, $currentPage - 1)) ?>" aria-label="Pagina anterior">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                    <a class="admin-pagination-button <?= $pageNumber === $currentPage ? 'active' : '' ?>" href="<?= $pageUrl($pageNumber) ?>"><?= $pageNumber ?></a>
                <?php endfor; ?>
                <a class="admin-pagination-button <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" href="<?= $pageUrl(min($totalPages, $currentPage + 1)) ?>" aria-label="Pagina siguiente">
                    <i class="fas fa-chevron-right"></i>
                </a>
                </div>
            </nav>
        </div>
    </div>
</div>

<?php
$customStyles = <<<CSS
.metric-card {
    background: #ffffff;
    border: 1px solid #d7eafd;
    border-radius: 16px;
    padding: 1.25rem;
    min-height: 92px;
    box-shadow: 0 8px 18px rgba(47, 128, 237, 0.06);
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

$loginAttemptFiltersJson = json_encode([
    'search' => (string) ($filters['search'] ?? ''),
    'status' => $status,
    'codeAccess' => (string) ($filters['codeAccess'] ?? ''),
    'from' => (string) ($filters['from'] ?? ''),
    'to' => (string) ($filters['to'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$loginAttemptsUrlJson = json_encode(app_url('/admin/customer-login-attempts'), JSON_UNESCAPED_SLASHES);
$customScripts = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.AdminFilters) return;
    window.AdminFilters.mount({
        container: '#loginAttemptsFilter',
        id: 'login-attempts-filter-drawer',
        title: 'Filtrar intentos de login',
        defaults: { search: '', status: 'all', codeAccess: '', from: '', to: '' },
        values: {$loginAttemptFiltersJson},
        fields: [
            { name: 'search', label: 'Buscar', type: 'search', placeholder: 'Usuario, codeAccess, IP o cliente' },
            { name: 'status', label: 'Estado', type: 'select', options: [
                { value: 'all', label: 'Todos' }, { value: 'failed', label: 'Fallidos' }, { value: 'success', label: 'Exitosos' }
            ], hideChipValues: ['all'] },
            { name: 'codeAccess', label: 'CodeAccess', type: 'text' },
            { name: 'from', label: 'Desde', type: 'date' },
            { name: 'to', label: 'Hasta', type: 'date' }
        ],
        onApply: function (values) {
            const url = new URL({$loginAttemptsUrlJson}, window.location.origin);
            Object.entries(values).forEach(([key, value]) => {
                if (value !== '' && value !== 'all') url.searchParams.set(key, value);
            });
            window.location.assign(url.toString());
        }
    });
});
</script>
JS;
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
