<?php
$title = 'Estadísticas de Clientes - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h3 class="mb-1">
                        <i class="fas fa-chart-bar me-2"></i>Estadísticas de Clientes
                    </h3>
                    <p class="text-muted mb-0">Resumen de actividad y datos de clientes sincronizados desde la app de escritorio.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al panel
                    </a>
                    <button type="button" class="btn btn-outline-primary" id="refreshStats">
                        <i class="fas fa-rotate"></i>
                        Actualizar
                    </button>
                </div>
            </div>

            <!-- Resumen Global -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-building fa-2x opacity-75"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h3 class="mb-0" id="statTotalCustomers"><?= $globalStats['totalCustomers'] ?? 0 ?></h3>
                                    <small>Clientes Totales</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-users fa-2x opacity-75"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h3 class="mb-0" id="statTotalUsers"><?= $globalStats['totalUsers'] ?? 0 ?></h3>
                                    <small>Usuarios Registrados</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-id-card fa-2x opacity-75"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h3 class="mb-0" id="statActiveSubscriptions"><?= $globalStats['totalActiveSubscriptions'] ?? 0 ?></h3>
                                    <small>Membresías Activas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-warning text-dark h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-box fa-2x opacity-75"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h3 class="mb-0" id="statTotalProducts"><?= $globalStats['totalProducts'] ?? 0 ?></h3>
                                    <small>Productos</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Segunda fila de estadísticas globales -->
            <div class="row g-3 mb-4">
                <div class="col-md-4 col-sm-6">
                    <div class="card border-primary h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-door-open fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0" id="statTodayAttendances"><?= $globalStats['todayAttendances'] ?? 0 ?></h4>
                            <small class="text-muted">Asistencias de Hoy</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="card border-success h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-clipboard-check fa-2x text-success mb-2"></i>
                            <h4 class="mb-0" id="statTotalAttendances"><?= $globalStats['totalAttendances'] ?? 0 ?></h4>
                            <small class="text-muted">Asistencias Totales</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-12">
                    <div class="card border-info h-100">
                        <div class="card-body text-center">
                            <i class="fab fa-whatsapp fa-2x text-success mb-2"></i>
                            <h4 class="mb-0" id="statMonthlyMessages"><?= $globalStats['monthlyMessages'] ?? 0 ?></h4>
                            <small class="text-muted">Mensajes Este Mes</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros de tabla -->
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Buscar cliente por nombre, email o ID...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filterPlan">
                                <option value="">Todos los planes</option>
                                <?php
                                $plans = [];
                                foreach ($customersStats as $cs) {
                                    $plan = $cs['customer']['planCode'] ?? null;
                                    if ($plan && !in_array($plan, $plans)) {
                                        $plans[] = $plan;
                                    }
                                }
                                sort($plans);
                                foreach ($plans as $plan):
                                ?>
                                <option value="<?= htmlspecialchars($plan) ?>"><?= htmlspecialchars($plan) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filterStatus">
                                <option value="">Todos los estados</option>
                                <option value="active">Activos</option>
                                <option value="inactive">Inactivos</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de clientes con estadísticas -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="customersStatsTable">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Cliente</th>
                                    <th scope="col" class="text-center">Plan</th>
                                    <th scope="col" class="text-center">
                                        <i class="fas fa-users" title="Usuarios"></i> Usuarios
                                    </th>
                                    <th scope="col" class="text-center">
                                        <i class="fas fa-id-card" title="Membresías Activas"></i> Membresías
                                    </th>
                                    <th scope="col" class="text-center">
                                        <i class="fas fa-box" title="Productos"></i> Productos
                                    </th>
                                    <th scope="col" class="text-center">
                                        <i class="fas fa-door-open" title="Asistencias"></i> Asistencias
                                    </th>
                                    <th scope="col" class="text-center">
                                        <i class="fab fa-whatsapp" title="Mensajes del Mes"></i> Mensajes
                                    </th>
                                    <th scope="col" class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($customersStats)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                        No hay clientes registrados.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($customersStats as $cs): 
                                    $customer = $cs['customer'];
                                    $stats = $cs['stats'];
                                ?>
                                <tr class="customer-row" 
                                    data-name="<?= htmlspecialchars(strtolower($customer['name'] ?? '')) ?>"
                                    data-email="<?= htmlspecialchars(strtolower($customer['email'] ?? '')) ?>"
                                    data-id="<?= htmlspecialchars(strtolower($customer['customerId'] ?? '')) ?>"
                                    data-plan="<?= htmlspecialchars($customer['planCode'] ?? '') ?>"
                                    data-status="<?= $customer['isActive'] ? 'active' : 'inactive' ?>">
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($customer['name'] ?? 'Sin nombre') ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($customer['email'] ?? '') ?></small>
                                        <br>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($customer['customerId']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($customer['planCode']): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($customer['planCode']) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-success"><?= number_format($stats['users']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-info"><?= number_format($stats['activeSubscriptions']) ?></span>
                                        <small class="text-muted">/ <?= number_format($stats['totalSubscriptions']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-warning"><?= number_format($stats['products']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold"><?= number_format($stats['todayAttendances']) ?></span>
                                        <small class="text-muted">hoy</small>
                                        <br>
                                        <small class="text-muted"><?= number_format($stats['monthlyAttendances']) ?> este mes</small>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-success"><?= number_format($stats['messagesSentThisMonth']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($customer['isActive']): ?>
                                        <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                        <?php endif; ?>
                                        <?php if ($customer['lastSeen']): ?>
                                        <br>
                                        <small class="text-muted" title="Última actividad">
                                            <i class="fas fa-clock"></i>
                                            <?= date('d/m/Y H:i', strtotime($customer['lastSeen'])) ?>
                                        </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Se muestran <span id="visibleCount"><?= count($customersStats) ?></span> de <?= count($customersStats) ?> clientes.
                    Última actualización: <?= date('d/m/Y H:i:s') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$customStyles = <<<CSS
.customer-row {
    transition: background-color 0.15s ease;
}

.customer-row:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.badge {
    font-weight: 500;
}
CSS;
?>

<?php
$customScripts = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const filterPlan = document.getElementById('filterPlan');
    const filterStatus = document.getElementById('filterStatus');
    const rows = document.querySelectorAll('.customer-row');
    const visibleCount = document.getElementById('visibleCount');
    const refreshBtn = document.getElementById('refreshStats');

    function filterTable() {
        const search = searchInput.value.toLowerCase().trim();
        const plan = filterPlan.value;
        const status = filterStatus.value;
        let visible = 0;

        rows.forEach(row => {
            const name = row.dataset.name;
            const email = row.dataset.email;
            const id = row.dataset.id;
            const rowPlan = row.dataset.plan;
            const rowStatus = row.dataset.status;

            let show = true;

            // Filtro de búsqueda
            if (search) {
                show = name.includes(search) || email.includes(search) || id.includes(search);
            }

            // Filtro de plan
            if (show && plan) {
                show = rowPlan === plan;
            }

            // Filtro de estado
            if (show && status) {
                show = rowStatus === status;
            }

            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        visibleCount.textContent = visible;
    }

    searchInput.addEventListener('input', filterTable);
    filterPlan.addEventListener('change', filterTable);
    filterStatus.addEventListener('change', filterTable);

    refreshBtn.addEventListener('click', function() {
        window.location.reload();
    });
});
</script>
JS;
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
