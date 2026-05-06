<?php
$title = 'Tablas Desktop - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <h3 class="mb-1"><i class="fas fa-database me-2"></i>Tablas Desktop</h3>
                    <p class="text-muted mb-0">Consulta y visualiza los datos de las tablas desktop por cliente.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al panel
                    </a>
                </div>
            </div>

            <!-- Filtro de clientes -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-10">
                            <label for="customerFilter" class="form-label fw-semibold">
                                <i class="fas fa-filter me-1"></i>Filtrar por Cliente
                            </label>
                            <select class="form-select" id="customerFilter">
                                <option value="">Selecciona un cliente para ver sus datos...</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= htmlspecialchars($customer['customerId']) ?>">
                                        <?= htmlspecialchars($customer['name']) ?> 
                                        (<?= htmlspecialchars($customer['customerId']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" id="clearFilterBtn">
                                <i class="fas fa-times me-1"></i>Limpiar
                            </button>
                        </div>
                    </div>
                    <div id="selectedCustomerInfo" class="mt-3 alert alert-info" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Cliente seleccionado:</strong> <span id="selectedCustomerName"></span>
                    </div>
                </div>
            </div>

            <!-- Grid de tablas -->
            <div class="row g-3">
                <?php foreach ($desktopTables as $tableKey => $tableInfo): ?>
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="card h-100 shadow-sm desktop-table-card" data-table="<?= htmlspecialchars($tableKey) ?>">
                            <div class="card-body d-flex flex-column">
                                <div class="text-center mb-3">
                                    <div class="display-4 text-primary mb-2">
                                        <i class="fas <?= htmlspecialchars($tableInfo['icon']) ?>"></i>
                                    </div>
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($tableInfo['name']) ?></h5>
                                    <small class="text-muted"><?= htmlspecialchars($tableInfo['table']) ?></small>
                                </div>
                                <p class="card-text text-muted small mb-3 flex-grow-1">
                                    <?= htmlspecialchars($tableInfo['description']) ?>
                                </p>
                                <a href="#" class="btn btn-outline-primary btn-sm view-table-btn" 
                                   data-table="<?= htmlspecialchars($tableKey) ?>">
                                    <i class="fas fa-eye me-1"></i>Ver datos
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.desktop-table-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}

.desktop-table-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.desktop-table-card:hover .card-title {
    color: var(--bs-primary);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerFilter = document.getElementById('customerFilter');
    const clearFilterBtn = document.getElementById('clearFilterBtn');
    const selectedCustomerInfo = document.getElementById('selectedCustomerInfo');
    const selectedCustomerName = document.getElementById('selectedCustomerName');
    const viewTableBtns = document.querySelectorAll('.view-table-btn');

    let currentCustomerId = '';

    // Manejar cambio de filtro de cliente
    customerFilter.addEventListener('change', function() {
        currentCustomerId = this.value;
        
        if (currentCustomerId) {
            const selectedOption = this.options[this.selectedIndex];
            selectedCustomerName.textContent = selectedOption.text;
            selectedCustomerInfo.style.display = 'block';
        } else {
            selectedCustomerInfo.style.display = 'none';
        }
    });

    // Limpiar filtro
    clearFilterBtn.addEventListener('click', function() {
        customerFilter.value = '';
        currentCustomerId = '';
        selectedCustomerInfo.style.display = 'none';
    });

    // Manejar click en botones de ver tabla
    viewTableBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const tableKey = this.getAttribute('data-table');
            
            if (!currentCustomerId) {
                alert('Por favor, selecciona un cliente primero.');
                customerFilter.focus();
                return;
            }

            // Redirigir a la vista de la tabla
            window.location.href = '<?= app_url('/admin/desktop-tables/view') ?>?table=' + 
                                    encodeURIComponent(tableKey) + 
                                    '&customer=' + encodeURIComponent(currentCustomerId);
        });
    });

    // También permitir click en toda la tarjeta
    document.querySelectorAll('.desktop-table-card').forEach(function(card) {
        card.addEventListener('click', function(e) {
            // Solo si no se hizo click en el botón directamente
            if (!e.target.closest('.view-table-btn')) {
                const tableKey = this.getAttribute('data-table');
                if (!currentCustomerId) {
                    alert('Por favor, selecciona un cliente primero.');
                    customerFilter.focus();
                    return;
                }
                window.location.href = '<?= app_url('/admin/desktop-tables/view') ?>?table=' + 
                                        encodeURIComponent(tableKey) + 
                                        '&customer=' + encodeURIComponent(currentCustomerId);
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/app.php';
?>
