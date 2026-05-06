<?php
$title = $tableInfo['name'] . ' - Tablas Desktop';

ob_start();
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Encabezado -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h3 class="mb-1">
                        <i class="fas <?= htmlspecialchars($tableInfo['icon']) ?> me-2"></i>
                        <?= htmlspecialchars($tableInfo['name']) ?>
                    </h3>
                    <p class="text-muted mb-0">
                        <small>Tabla: <code><?= htmlspecialchars($tableInfo['table']) ?></code></small>
                        <span class="mx-2">|</span>
                        <small><?= htmlspecialchars($tableInfo['description']) ?></small>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= app_url('/admin/desktop-tables') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                    <?php if (!empty($customerApiId)): ?>
                        <button type="button" class="btn btn-outline-primary" id="refreshDataBtn">
                            <i class="fas fa-sync-alt me-1"></i>Actualizar
                        </button>
                        <button type="button" class="btn btn-outline-success" id="exportDataBtn">
                            <i class="fas fa-file-export me-1"></i>Exportar JSON
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Selector de cliente -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-10">
                            <label for="customerSelect" class="form-label fw-semibold">
                                <i class="fas fa-user me-1"></i>Seleccionar Cliente
                            </label>
                            <select class="form-select" id="customerSelect">
                                <option value="">-- Selecciona un cliente --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= htmlspecialchars($customer['customerId']) ?>"
                                            <?= $customer['customerId'] === $customerApiId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($customer['name']) ?> 
                                        (<?= htmlspecialchars($customer['customerId']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" id="loadDataBtn">
                                <i class="fas fa-search me-1"></i>Cargar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información del cliente seleccionado -->
            <?php if ($selectedCustomer): ?>
                <div class="alert alert-info mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <strong><i class="fas fa-building me-2"></i>Cliente:</strong> 
                            <?= htmlspecialchars($selectedCustomer['name']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-key me-2"></i>ID:</strong> 
                            <?= htmlspecialchars($selectedCustomer['customerId']) ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-database me-2"></i>Registros:</strong> 
                            <span class="badge bg-primary"><?= count($records) ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tabla de datos -->
            <?php if (!empty($customerApiId) && $selectedCustomer): ?>
                <?php if (empty($records)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No se encontraron registros para este cliente en la tabla 
                        <strong><?= htmlspecialchars($tableInfo['name']) ?></strong>.
                    </div>
                <?php else: ?>
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0">
                                        <i class="fas fa-table me-2"></i>
                                        Datos de la Tabla
                                    </h5>
                                </div>
                                <div class="col-md-6 text-end">
                                    <input type="text" class="form-control form-control-sm d-inline-block" 
                                           id="searchInput" placeholder="Buscar en la tabla..." 
                                           style="max-width: 300px;">
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle mb-0" id="dataTable">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th scope="col" style="width: 50px;">#</th>
                                            <?php 
                                            // Obtener las columnas del primer registro
                                            $columns = !empty($records) ? array_keys($records[0]) : [];
                                            foreach ($columns as $column): 
                                            ?>
                                                <th scope="col" class="text-nowrap">
                                                    <?= htmlspecialchars($column) ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $index => $record): ?>
                                            <tr>
                                                <td class="text-muted"><?= $index + 1 ?></td>
                                                <?php foreach ($record as $key => $value): ?>
                                                    <td>
                                                        <?php 
                                                        // Formatear valores especiales
                                                        if (is_null($value)) {
                                                            echo '<span class="text-muted fst-italic">NULL</span>';
                                                        } elseif (is_bool($value)) {
                                                            echo $value 
                                                                ? '<span class="badge bg-success">TRUE</span>' 
                                                                : '<span class="badge bg-secondary">FALSE</span>';
                                                        } elseif ($value === '0' || $value === '1') {
                                                            // Detectar booleanos numéricos
                                                            if (stripos($key, 'is') === 0 || 
                                                                stripos($key, 'active') !== false || 
                                                                stripos($key, 'deleted') !== false) {
                                                                echo $value === '1' 
                                                                    ? '<span class="badge bg-success">Sí</span>' 
                                                                    : '<span class="badge bg-secondary">No</span>';
                                                            } else {
                                                                echo htmlspecialchars($value);
                                                            }
                                                        } elseif (strlen($value) > 100) {
                                                            // Truncar valores largos
                                                            echo '<span title="' . htmlspecialchars($value) . '">' 
                                                                 . htmlspecialchars(substr($value, 0, 100)) . '...</span>';
                                                        } else {
                                                            echo htmlspecialchars($value);
                                                        }
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        Total de registros: <strong><?= count($records) ?></strong>
                                    </small>
                                </div>
                                <div>
                                    <small class="text-muted">
                                        Última actualización: <strong><?= date('d/m/Y H:i:s') ?></strong>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-info-circle fa-3x mb-3 text-primary"></i>
                    <h4>Selecciona un cliente</h4>
                    <p class="mb-0">Por favor, selecciona un cliente del menú desplegable para ver sus datos.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.table-responsive {
    max-height: 70vh;
    overflow-y: auto;
}

.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
}

.table td, .table th {
    white-space: nowrap;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.table tbody tr:hover {
    background-color: rgba(13, 110, 253, 0.05);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.getElementById('customerSelect');
    const loadDataBtn = document.getElementById('loadDataBtn');
    const refreshDataBtn = document.getElementById('refreshDataBtn');
    const exportDataBtn = document.getElementById('exportDataBtn');
    const searchInput = document.getElementById('searchInput');
    const tableKey = '<?= htmlspecialchars($tableKey) ?>';

    // Cargar datos
    loadDataBtn?.addEventListener('click', function() {
        const customerId = customerSelect.value;
        if (!customerId) {
            alert('Por favor, selecciona un cliente.');
            return;
        }
        window.location.href = '<?= app_url('/admin/desktop-tables/view') ?>?table=' + 
                                encodeURIComponent(tableKey) + 
                                '&customer=' + encodeURIComponent(customerId);
    });

    // Actualizar datos
    refreshDataBtn?.addEventListener('click', function() {
        window.location.reload();
    });

    // Exportar a JSON
    exportDataBtn?.addEventListener('click', async function() {
        const customerId = customerSelect.value;
        if (!customerId) {
            alert('No hay datos para exportar.');
            return;
        }

        try {
            const response = await fetch(
                '<?= app_url('/admin/desktop-tables/api/data') ?>?table=' + 
                encodeURIComponent(tableKey) + 
                '&customer=' + encodeURIComponent(customerId)
            );
            
            if (!response.ok) {
                throw new Error('Error al obtener los datos');
            }

            const data = await response.json();
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = tableKey + '_' + customerId + '_' + Date.now() + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Error:', error);
            alert('Error al exportar los datos: ' + error.message);
        }
    });

    // Búsqueda en la tabla
    searchInput?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const table = document.getElementById('dataTable');
        const rows = table?.querySelectorAll('tbody tr');

        rows?.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Cambio de cliente con Enter
    customerSelect?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            loadDataBtn?.click();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/app.php';
?>
