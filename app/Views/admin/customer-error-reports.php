<?php
$title = 'Errores de clientes';

$reportRows = $reports['data'] ?? [];
$pagination = $reports['pagination'] ?? ['page' => 1, 'perPage' => 30, 'total' => 0, 'totalPages' => 1];
$filters = $filters ?? [];
$summary = $summary ?? [];

function customer_error_filter(array $filters, string $key): string
{
    return htmlspecialchars((string) ($filters[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

function customer_error_badge(string $type): string
{
    $classes = [
        'client' => 'bg-primary',
        'server' => 'bg-danger',
        'internal' => 'bg-warning text-dark',
    ];

    return '<span class="badge ' . ($classes[$type] ?? 'bg-secondary') . '">' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '</span>';
}

ob_start();
?>

<div class="container mt-4">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-triangle-exclamation me-2"></i>
                Errores de clientes
            </h1>
            <p class="text-muted mb-0">Reportes recibidos desde el cliente de escritorio o procesos internos asociados al cliente.</p>
        </div>
        <a href="<?= app_url('/admin/dashboard') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-chart-line me-2"></i>Dashboard
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
                <span>Sin leer</span>
                <strong class="text-danger" id="summaryUnread"><?= (int) ($summary['unread'] ?? 0) ?></strong>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <span>Cliente sin leer</span>
                <strong class="text-primary" id="summaryUnreadClient"><?= (int) ($summary['unreadClient'] ?? 0) ?></strong>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card">
                <span>Servidor sin leer</span>
                <strong class="text-warning" id="summaryUnreadServer"><?= (int) ($summary['unreadServer'] ?? 0) ?></strong>
            </div>
        </div>
    </div>

    <?php
        $status = (string) ($filters['status'] ?? 'all');
        $type = (string) ($filters['type'] ?? 'all');
    ?>
    <div id="customerErrorsFilter"></div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h2 class="h5 mb-0">Reportes</h2>
            <span class="badge bg-light text-dark"><?= (int) ($pagination['total'] ?? 0) ?> registros</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Cliente</th>
                        <th>Error</th>
                        <th>Ambiente</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportRows)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No hay errores con los filtros actuales.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($reportRows as $report): ?>
                        <?php $isRead = (bool) ($report['isRead'] ?? false); ?>
                        <tr data-report-row="<?= htmlspecialchars((string) $report['id'], ENT_QUOTES, 'UTF-8') ?>" class="<?= $isRead ? '' : 'table-warning' ?>">
                            <td class="text-nowrap"><?= htmlspecialchars((string) ($report['createdAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?= customer_error_badge((string) ($report['errorType'] ?? 'client')) ?>
                                <div><small class="text-muted"><?= htmlspecialchars((string) ($report['severity'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars((string) ($report['customerName'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8') ?></div>
                                <small class="text-muted d-block"><?= htmlspecialchars((string) ($report['customerId'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                <?php if (!empty($report['customerCodeAccess'])): ?>
                                    <code><?= htmlspecialchars((string) $report['customerCodeAccess'], ENT_QUOTES, 'UTF-8') ?></code>
                                <?php endif; ?>
                            </td>
                            <td class="error-detail-cell">
                                <div class="fw-semibold"><?= htmlspecialchars((string) ($report['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <details class="mt-2">
                                    <summary>Ver detalles</summary>
                                    <?php if (!empty($report['stackTrace'])): ?>
                                        <pre><?= htmlspecialchars((string) $report['stackTrace'], ENT_QUOTES, 'UTF-8') ?></pre>
                                    <?php endif; ?>
                                    <?php if (!empty($report['context'])): ?>
                                        <pre><?= htmlspecialchars(json_encode($report['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                                    <?php endif; ?>
                                </details>
                            </td>
                            <td>
                                <div><?= htmlspecialchars((string) ($report['clientVersion'] ?? 'Sin version'), ENT_QUOTES, 'UTF-8') ?></div>
                                <small class="text-muted d-block"><?= htmlspecialchars((string) ($report['deviceName'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                <small class="text-muted d-block"><?= htmlspecialchars((string) ($report['ipAddress'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                            </td>
                            <td data-report-status>
                                <?php if ($isRead): ?>
                                    <span class="badge bg-success">Visto</span>
                                    <small class="text-muted d-block"><?= htmlspecialchars((string) ($report['readAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                                <?php else: ?>
                                    <span class="badge bg-danger">Sin leer</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-success" data-mark-read="<?= htmlspecialchars((string) $report['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $isRead ? 'disabled' : '' ?>>
                                    <i class="fas fa-check me-1"></i>Visto
                                </button>
                            </td>
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
                    return app_url('/admin/customer-error-reports?' . http_build_query($query));
                };
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
            ?>
            <nav class="admin-pagination" aria-label="Paginacion de reportes">
                <span class="admin-pagination-summary">Mostrando <?= count($reportRows) ?> de <?= (int) ($pagination['total'] ?? 0) ?> registros</span>
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

<script>
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-mark-read]');
    if (!button) return;

    const id = button.dataset.markRead;
    button.disabled = true;

    try {
        const response = await fetch(`<?= app_url('/admin/api/customer-error-reports') ?>/${encodeURIComponent(id)}/read`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: '{}'
        });
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'No se pudo marcar como visto');

        const row = button.closest('[data-report-row]');
        if (row) {
            row.classList.remove('table-warning');
            const status = row.querySelector('[data-report-status]');
            if (status) status.innerHTML = '<span class="badge bg-success">Visto</span>';
        }

        button.disabled = true;
        document.getElementById('summaryUnread').textContent = data.summary?.unread ?? 0;
        document.getElementById('summaryUnreadClient').textContent = data.summary?.unreadClient ?? 0;
        document.getElementById('summaryUnreadServer').textContent = data.summary?.unreadServer ?? 0;
    } catch (error) {
        button.disabled = false;
        alert(error.message);
    }
});
</script>

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

.error-detail-cell {
    min-width: 300px;
}

.error-detail-cell pre {
    max-width: 520px;
    max-height: 260px;
    overflow: auto;
    margin: 0.75rem 0 0;
    padding: 0.75rem;
    border-radius: 4px;
    background: #f8f9fa;
    color: #212529;
    font-size: 0.8rem;
    white-space: pre-wrap;
}
CSS;

$customerErrorFiltersJson = json_encode([
    'search' => (string) ($filters['search'] ?? ''),
    'status' => $status,
    'type' => $type,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$customerErrorsUrlJson = json_encode(app_url('/admin/customer-error-reports'), JSON_UNESCAPED_SLASHES);
$customScripts = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.AdminFilters) return;
    window.AdminFilters.mount({
        container: '#customerErrorsFilter',
        id: 'customer-errors-filter-drawer',
        title: 'Filtrar reportes',
        defaults: { search: '', status: 'all', type: 'all' },
        values: {$customerErrorFiltersJson},
        fields: [
            { name: 'search', label: 'Buscar', type: 'search', placeholder: 'Mensaje, cliente, email o codeAccess' },
            { name: 'status', label: 'Estado', type: 'select', options: [
                { value: 'all', label: 'Todos' }, { value: 'unread', label: 'Sin leer' }, { value: 'read', label: 'Vistos' }
            ], hideChipValues: ['all'] },
            { name: 'type', label: 'Tipo', type: 'select', options: [
                { value: 'all', label: 'Todos' }, { value: 'client', label: 'Cliente' }, { value: 'server_group', label: 'Servidor' }
            ], hideChipValues: ['all'] }
        ],
        onApply: function (values) {
            const url = new URL({$customerErrorsUrlJson}, window.location.origin);
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
