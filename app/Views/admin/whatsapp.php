<?php
$title = 'WhatsApp Business - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h3 class="mb-1"><i class="fab fa-whatsapp text-success me-2"></i>Configuración WhatsApp Business</h3>
                    <p class="text-muted mb-0">Administra los números de WhatsApp registrados para cada cliente.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al panel
                    </a>
                    <button type="button" class="btn btn-outline-secondary" id="refreshConfigs">
                        <i class="fas fa-rotate"></i> Actualizar
                    </button>
                    <button type="button" class="btn btn-success" id="addConfigBtn" data-bs-toggle="modal" data-bs-target="#configModal">
                        <i class="fab fa-whatsapp me-1"></i> Agregar número
                    </button>
                </div>
            </div>

            <div id="alertsContainer"></div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="configsTable">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Número</th>
                                    <th scope="col">Phone Number ID</th>
                                    <th scope="col">Negocio</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Creado</th>
                                    <th scope="col" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-circle-notch fa-spin me-2"></i>
                                        Cargando configuraciones...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Nota:</strong> El Phone Number ID se obtiene desde el Meta Business Manager cuando agregas un número de WhatsApp Business.
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear configuración -->
<div class="modal fade" id="configModal" tabindex="-1" aria-labelledby="configModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="configForm">
                <div class="modal-header" style="background-color: #25D366; color: white;">
                    <h5 class="modal-title" id="configModalLabel">
                        <i class="fab fa-whatsapp me-2"></i>Nueva configuración WhatsApp
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="configCustomerId" class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select" id="configCustomerId" required>
                                <option value="">Selecciona un cliente...</option>
                                <?php foreach ($customers ?? [] as $customer): ?>
                                <option value="<?= htmlspecialchars($customer['customerId'] ?? '') ?>">
                                    <?= htmlspecialchars($customer['name'] ?? $customer['customerId'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">El cliente al que se asociará este número de WhatsApp.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="configPhoneNumber" class="form-label">Número de teléfono <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="configPhoneNumber" placeholder="+52 1234567890" required>
                            <div class="form-text">Número completo con código de país.</div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="configPhoneNumberId" class="form-label">Phone Number ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="configPhoneNumberId" placeholder="123456789012345" required>
                            <div class="form-text">ID del número en Meta Business Manager.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="configAccessToken" class="form-label">Access Token</label>
                            <input type="text" class="form-control" id="configAccessToken" placeholder="EAABs...">
                            <div class="form-text">Token de acceso para la API de WhatsApp (opcional).</div>
                        </div>
                    </div>
                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-building me-2"></i>Información del negocio</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="configBusinessName" class="form-label">Nombre del negocio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="configBusinessName" placeholder="Mi Gimnasio" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="configEmail" class="form-label">Email del negocio</label>
                            <input type="email" class="form-control" id="configEmail" placeholder="contacto@gimnasio.com">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="configAddress" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="configAddress" placeholder="Calle 123, Ciudad">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="configDescription" class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="configDescription" placeholder="Gimnasio y centro fitness">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="configRegisterInWhatsApp">
                        <label class="form-check-label" for="configRegisterInWhatsApp">
                            <i class="fas fa-check-circle text-success me-1"></i>
                            Registrar número en WhatsApp (activarlo en Meta)
                        </label>
                        <div class="form-text">Marca esta opción para que el número aparezca como "activo" en Meta Business Manager.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Guardar configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Confirmar eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-trash-alt me-2"></i>Confirmar eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar esta configuración de WhatsApp?</p>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Cliente:</strong> <span id="deleteCustomerName"></span><br>
                    <strong>Número:</strong> <span id="deletePhoneNumber"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash-alt me-1"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$endpointsJson = json_encode([
    'base' => app_url('/admin/api/whatsapp'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$customStyles = <<<CSS
.table td .badge {
    font-size: 0.75rem;
}

.table td code {
    font-size: 0.85rem;
    background-color: rgba(37, 211, 102, 0.08);
    padding: 0.15rem 0.35rem;
    border-radius: 4px;
}

.action-buttons .btn {
    min-width: 40px;
}

#alertsContainer .alert {
    margin-bottom: 1rem;
}

.whatsapp-badge {
    background-color: #25D366;
}
CSS;

ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const endpoints = <?= $endpointsJson ?>;
    const baseUrl = endpoints.base;
    console.log('Base URL:', baseUrl);
    
    let configs = [];
    let deleteId = null;

    const tableBody = document.querySelector('#configsTable tbody');
    const refreshButton = document.getElementById('refreshConfigs');
    const alertsContainer = document.getElementById('alertsContainer');
    const configModalEl = document.getElementById('configModal');
    const configModal = configModalEl ? new bootstrap.Modal(configModalEl) : null;
    const configForm = document.getElementById('configForm');
    const deleteModalEl = document.getElementById('deleteModal');
    const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('es-MX', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function showAlert(message, type = 'success') {
        const alertId = 'alert-' + Date.now();
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert" id="${alertId}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        `;
        alertsContainer.insertAdjacentHTML('beforeend', alertHtml);
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) alert.remove();
        }, 5000);
    }

    function renderTable() {
        if (!configs || configs.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fab fa-whatsapp fa-2x mb-3 d-block" style="color: #25D366;"></i>
                        No hay configuraciones de WhatsApp registradas.<br>
                        <small>Haz clic en "Agregar número" para comenzar.</small>
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = configs.map(config => `
            <tr data-id="${escapeHtml(config.Id)}">
                <td>
                    <strong>${escapeHtml(config.CustomerName || 'Sin nombre')}</strong>
                    <br><small class="text-muted">${escapeHtml(config.CustomerId)}</small>
                </td>
                <td>
                    <code>${escapeHtml(config.PhoneNumber)}</code>
                </td>
                <td>
                    <code class="text-primary">${escapeHtml(config.PhoneNumberId)}</code>
                </td>
                <td>
                    ${escapeHtml(config.BusinessName || '-')}
                    ${config.BusinessEmail ? '<br><small class="text-muted">' + escapeHtml(config.BusinessEmail) + '</small>' : ''}
                </td>
                <td>
                    ${config.IsActive 
                        ? '<span class="badge whatsapp-badge"><i class="fas fa-check me-1"></i>Activo</span>' 
                        : '<span class="badge bg-secondary">Inactivo</span>'}
                    ${config.AccessToken 
                        ? '<br><small class="text-success"><i class="fas fa-key me-1"></i>Con token</small>' 
                        : '<br><small class="text-muted"><i class="fas fa-key me-1"></i>Sin token</small>'}
                </td>
                <td>
                    <small class="text-muted">${formatDate(config.CreatedAt)}</small>
                </td>
                <td class="text-end">
                    <div class="btn-group action-buttons">
                        <button class="btn btn-sm btn-outline-success" title="Registrar en WhatsApp" onclick="registerNumber('${escapeHtml(config.Id)}')">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info" title="Ver estado" onclick="checkStatus('${escapeHtml(config.Id)}')">
                            <i class="fas fa-info-circle"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="confirmDelete('${escapeHtml(config.Id)}', '${escapeHtml(config.CustomerName || config.CustomerId)}', '${escapeHtml(config.PhoneNumber)}')">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    async function loadConfigs() {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <i class="fas fa-circle-notch fa-spin me-2"></i>
                    Cargando configuraciones...
                </td>
            </tr>
        `;

        console.log('Loading configs from:', baseUrl);
        
        try {
            const response = await fetch(baseUrl, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            const data = await response.json();
            console.log('Response data:', data);
            
            if (data.success) {
                configs = data.configurations || [];
                console.log('Loaded configs:', configs.length);
                renderTable();
            } else {
                console.error('API error:', data.error);
                showAlert(data.error || 'Error al cargar configuraciones', 'danger');
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-5 text-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error al cargar configuraciones
                        </td>
                    </tr>
                `;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            showAlert('Error de conexión al cargar configuraciones: ' + error.message, 'danger');
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error de conexión: ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }
    }

    // Crear nueva configuración
    if (configForm) {
        configForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const payload = {
                customerId: document.getElementById('configCustomerId').value,
                phoneNumber: document.getElementById('configPhoneNumber').value,
                phoneNumberId: document.getElementById('configPhoneNumberId').value,
                accessToken: document.getElementById('configAccessToken').value,
                businessName: document.getElementById('configBusinessName').value,
                email: document.getElementById('configEmail').value,
                address: document.getElementById('configAddress').value,
                description: document.getElementById('configDescription').value,
                registerInWhatsApp: document.getElementById('configRegisterInWhatsApp').checked
            };

            try {
                const response = await fetch(baseUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();

                if (data.success) {
                    if (configModal) configModal.hide();
                    configForm.reset();
                    
                    let message = 'Configuración creada correctamente';
                    if (data.whatsappRegistered) {
                        message += ' y número registrado en WhatsApp';
                    } else if (data.whatsappError) {
                        message += '. Advertencia al registrar en WhatsApp: ' + data.whatsappError;
                    }
                    showAlert(message, data.whatsappError ? 'warning' : 'success');
                    loadConfigs();
                } else {
                    showAlert(data.error || 'Error al crear configuración', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error de conexión', 'danger');
            }
        });
    }

    // Confirmar eliminación
    window.confirmDelete = function(id, customerName, phoneNumber) {
        deleteId = id;
        const customerNameEl = document.getElementById('deleteCustomerName');
        const phoneNumberEl = document.getElementById('deletePhoneNumber');
        
        if (customerNameEl) customerNameEl.textContent = customerName;
        if (phoneNumberEl) phoneNumberEl.textContent = phoneNumber;
        
        if (deleteModal) {
            deleteModal.show();
        } else {
            console.error('Delete modal not found');
        }
    };

    // Ejecutar eliminación
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async function() {
            if (!deleteId) return;

            try {
                const response = await fetch(`${baseUrl}/${deleteId}/delete`, {
                    method: 'POST',
                    headers: { 
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();

                if (data.success) {
                    deleteModal.hide();
                    showAlert('Configuración eliminada correctamente', 'success');
                    loadConfigs();
                } else {
                    showAlert(data.error || 'Error al eliminar', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error de conexión', 'danger');
            }
            deleteId = null;
        });
    }

    // Registrar número en WhatsApp
    window.registerNumber = async function(id) {
        try {
            const response = await fetch(`${baseUrl}/${id}/register`, {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (data.success) {
                showAlert('Número registrado correctamente en WhatsApp', 'success');
            } else {
                showAlert(data.error || 'Error al registrar en WhatsApp', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Error de conexión', 'danger');
        }
    };

    // Verificar estado del número
    window.checkStatus = async function(id) {
        try {
            const response = await fetch(`${baseUrl}/${id}/status`, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (data.success) {
                const statusInfo = data.data ? JSON.stringify(data.data, null, 2) : 'Sin datos adicionales';
                showAlert(`Estado: ${data.status || 'Desconocido'}`, 'info');
                console.log('Estado del número:', data);
            } else {
                showAlert(data.error || 'Error al verificar estado', 'danger');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Error de conexión', 'danger');
        }
    };

    // Event listeners
    if (refreshButton) {
        refreshButton.addEventListener('click', loadConfigs);
    }

    // Cargar al iniciar
    loadConfigs();
});
</script>
<?php
$customScripts = ob_get_clean();
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
