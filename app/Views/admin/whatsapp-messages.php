<?php
$title = 'Mensajes WhatsApp - ClubCheck';
$rows = $messages['data'] ?? [];
$summary = $messages['summary'] ?? [];
$pagination = $messages['pagination'] ?? [];
$page = (int) ($pagination['page'] ?? 1);
$totalPages = (int) ($pagination['totalPages'] ?? 1);
$total = (int) ($summary['total'] ?? 0);
$perPage = (int) ($pagination['perPage'] ?? 50);
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$query = array_filter($filters, static fn ($value) => $value !== '');
$query['perPage'] = $perPage;
$pageUrl = static function (int $target) use ($query): string {
    return app_url('/admin/whatsapp/messages?' . http_build_query(array_merge($query, ['page' => $target])));
};
ob_start();
?>

<div class="container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fab fa-whatsapp text-success me-2"></i>Historial de mensajes WhatsApp</h1>
            <p class="text-muted mb-0">Intentos registrados de todos los clientes, ordenados del más reciente al más antiguo.</p>
        </div>
        <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Volver al panel</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Intentos con estos filtros</div><div class="fs-3 fw-semibold"><?= $total ?></div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Aceptados por la API</div><div class="fs-3 fw-semibold text-success"><?= (int) ($summary['successful'] ?? 0) ?></div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Fallidos</div><div class="fs-3 fw-semibold text-danger"><?= (int) ($summary['failed'] ?? 0) ?></div></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Clientes con fallos</div><div class="fs-3 fw-semibold"><?= (int) ($summary['customersWithErrors'] ?? 0) ?></div></div></div></div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="<?= app_url('/admin/whatsapp/messages') ?>" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label for="customerId" class="form-label">Cliente</label>
                    <select name="customerId" id="customerId" class="form-select">
                        <option value="">Todos los clientes</option>
                        <?php foreach ($customers as $customer): ?>
                            <?php $customerId = (string) ($customer['customerId'] ?? ''); ?>
                            <option value="<?= $escape($customerId) ?>" <?= ($filters['customerId'] ?? '') === $customerId ? 'selected' : '' ?>><?= $escape($customer['name'] ?? $customerId) ?> (<?= $escape($customerId) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="status" class="form-label">Estado</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="success" <?= ($filters['status'] ?? '') === 'success' ? 'selected' : '' ?>>Aceptados</option>
                        <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Fallidos</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="from" class="form-label">Desde</label>
                    <input type="date" name="from" id="from" class="form-control" value="<?= $escape($filters['from'] ?? '') ?>">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="to" class="form-label">Hasta</label>
                    <input type="date" name="to" id="to" class="form-control" value="<?= $escape($filters['to'] ?? '') ?>">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="perPage" class="form-label">Por página</label>
                    <select name="perPage" id="perPage" class="form-select">
                        <?php foreach ([25, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-8">
                    <label for="error" class="form-label">Buscar en el error</label>
                    <input type="search" name="error" id="error" class="form-control" maxlength="200" value="<?= $escape($filters['error'] ?? '') ?>" placeholder="Ej. límite de mensajes">
                </div>
                <div class="col-lg-4 d-flex gap-2 justify-content-lg-end">
                    <a href="<?= app_url('/admin/whatsapp/messages') ?>" class="btn btn-outline-secondary">Limpiar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 mb-0">Registros</h2>
            <span class="badge bg-light text-dark"><?= $total ?> en total</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Fecha</th><th>Cliente</th><th>Teléfono</th><th>Mensaje</th><th>Estado</th><th>Error</th><th>Comparación del límite</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">No hay mensajes con estos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php $debug = json_decode((string) ($row['Debug'] ?? ''), true); ?>
                    <tr>
                        <td class="text-nowrap"><?= $escape($row['DateSent'] ?? '') ?></td>
                        <td><div class="fw-semibold"><?= $escape($row['CustomerName'] ?? 'Cliente sin nombre') ?></div><small class="text-muted"><?= $escape($row['CustomerApiId'] ?? '') ?></small></td>
                        <td class="text-nowrap"><?= $escape($row['PhoneNumber'] ?? '') ?></td>
                        <td class="message-cell"><?= $escape($row['Message'] ?? '') ?></td>
                        <td><?= (int) ($row['Successful'] ?? 0) === 1 ? '<span class="badge bg-success">Aceptado</span>' : '<span class="badge bg-danger">Fallido</span>' ?></td>
                        <td class="error-cell text-danger"><?= $escape($row['ErrorMessage'] ?? '') ?></td>
                        <td class="text-nowrap">
                            <?php if (is_array($debug) && array_key_exists('messages_counted', $debug)): ?>
                                <div><strong>Contados:</strong> <?= (int) $debug['messages_counted'] ?></div>
                                <div><strong>Límite:</strong> <?= $debug['messages_limit'] === null ? 'null' : $escape($debug['messages_limit']) ?></div>
                                <small class="text-muted">Plan: <?= $escape($debug['plan_lookup_key'] ?? 'desconocido') ?></small>
                                <?php if (isset($debug['limit_rule_present']) && !$debug['limit_rule_present']): ?>
                                    <div class="text-danger small">Regla max_messages ausente</div>
                                <?php endif; ?>
                                <div class="text-muted small"><?= ($debug['count_source'] ?? '') === 'bulk_counter' ? 'Conteo de lote' : 'Consulta de base de datos' ?></div>
                                <?php if (!empty($debug['comparison_month'])): ?>
                                    <div class="text-muted small">Mes: <?= $escape($debug['comparison_month']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">Sin comparación registrada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted small">Página <?= $page ?> de <?= $totalPages ?><?= $total ? ' · Registros ' . (($page - 1) * $perPage + 1) . '–' . min($page * $perPage, $total) : '' ?></span>
            <div class="btn-group" aria-label="Paginación">
                <a href="<?= $escape($pageUrl(max(1, $page - 1))) ?>" class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>>Anterior</a>
                <a href="<?= $escape($pageUrl(min($totalPages, $page + 1))) ?>" class="btn btn-outline-secondary <?= $page >= $totalPages ? 'disabled' : '' ?>" <?= $page >= $totalPages ? 'aria-disabled="true"' : '' ?>>Siguiente</a>
            </div>
        </div>
    </div>
    <p class="text-muted small mt-3">“Aceptado” significa que la API de WhatsApp aceptó la solicitud; no confirma la entrega al destinatario. Los fallos incluyen intentos bloqueados antes del envío.</p>
</div>

<?php
$customStyles = '.message-cell, .error-cell { min-width: 220px; max-width: 360px; overflow-wrap: anywhere; }';
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
