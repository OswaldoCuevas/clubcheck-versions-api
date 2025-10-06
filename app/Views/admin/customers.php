<?php
$title = 'Clientes - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h3 class="mb-1">Clientes registrados</h3>
                    <p class="text-muted mb-0">Gestiona los tokens y el estado de cada cliente de escritorio.</p>
                </div>
                <div class="d-flex gap-2">
                     <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al panel
                    </a>
                    <button type="button" class="btn btn-outline-secondary" id="refreshCustomers">
                        <i class="fas fa-rotate"></i>
                        Actualizar
                    </button>
                    <button type="button" class="btn btn-success" id="addCustomerBtn" data-bs-toggle="modal" data-bs-target="#customerModal">
                        <i class="fas fa-user-plus"></i>
                        Agregar cliente
                    </button>
                </div>
            </div>

            <div id="alertsContainer"></div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="customersTable">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Token actual</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Esperando nuevo token</th>
                                    <th scope="col">Actualizaciones</th>
                                    <th scope="col" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-circle-notch fa-spin me-2"></i>
                                        Cargando clientes...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear/Editar cliente -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="customerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalLabel">Nuevo cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="customerId" class="form-label">ID de cliente</label>
                        <input type="text" class="form-control" id="customerId" required>
                        <div class="form-text">Ejemplo: CLUB-001</div>
                    </div>
                    <div class="mb-3">
                        <label for="customerName" class="form-label">Nombre del cliente</label>
                        <input type="text" class="form-control" id="customerName" placeholder="Nombre legal o comercial">
                    </div>
                    <div class="mb-3">
                        <label for="customerDeviceName" class="form-label">Nombre del dispositivo</label>
                        <input type="text" class="form-control" id="customerDeviceName" placeholder="Ejemplo: POS-01" autocomplete="off">
                        <div class="form-text">Identifica la terminal o estación donde está instalado el escritorio.</div>
                    </div>
                    <div class="mb-3">
                        <label for="customerToken" class="form-label">Token actual (opcional)</label>
                        <input type="text" class="form-control" id="customerToken" placeholder="Token asignado al cliente">
                        <div class="form-text">Solo úsalo si deseas registrar un token manualmente.</div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="customerActive" checked>
                        <label class="form-check-label" for="customerActive">
                            Cliente activo
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$initialCustomersJson = json_encode($customers ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$endpointsJson = json_encode([
    'list' => app_url('/admin/api/customers'),
    'save' => app_url('/api/customers/save'),
    'await' => app_url('/api/customers/token/await'),
    'register' => app_url('/api/customers/token/register'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$customStyles = <<<CSS
.table td .badge {
    font-size: 0.75rem;
}

.table td code {
    font-size: 0.85rem;
    background-color: rgba(52, 152, 219, 0.08);
    padding: 0.15rem 0.35rem;
    border-radius: 4px;
}

.action-buttons .btn {
    min-width: 40px;
}

#alertsContainer .alert {
    margin-bottom: 1rem;
}
CSS;

ob_start();
?>
<script>
(function() {
    const endpoints = <?= $endpointsJson ?>;
    let customers = <?= $initialCustomersJson ?>;
    customers = Array.isArray(customers) ? customers : [];

    const tableBody = document.querySelector('#customersTable tbody');
    const refreshButton = document.getElementById('refreshCustomers');
    const addButton = document.getElementById('addCustomerBtn');
    const alertsContainer = document.getElementById('alertsContainer');
    const customerModalEl = document.getElementById('customerModal');
    const customerModal = customerModalEl ? new bootstrap.Modal(customerModalEl) : null;
    const customerForm = document.getElementById('customerForm');
    const customerIdInput = document.getElementById('customerId');
    const customerNameInput = document.getElementById('customerName');
    const customerDeviceInput = document.getElementById('customerDeviceName');
    const customerTokenInput = document.getElementById('customerToken');
    const customerActiveInput = document.getElementById('customerActive');
    const customerModalLabel = document.getElementById('customerModalLabel');

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function relativeTime(timestamp) {
        if (!timestamp) {
            return 'sin datos';
        }
        const now = Date.now() / 1000;
        const diff = now - timestamp;
        if (diff < 60) {
            return 'hace ' + Math.round(diff) + 's';
        }
        const minutes = Math.floor(diff / 60);
        if (minutes < 60) {
            return 'hace ' + minutes + 'm';
        }
        const hours = Math.floor(minutes / 60);
        if (hours < 24) {
            return 'hace ' + hours + 'h';
        }
        const days = Math.floor(hours / 24);
        return 'hace ' + days + 'd';
    }

    function pushAlert(type, message) {
        if (!alertsContainer) return;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        `;
        alertsContainer.appendChild(wrapper);
        setTimeout(() => {
            const alert = bootstrap.Alert.getOrCreateInstance(wrapper.querySelector('.alert'));
            alert.close();
        }, 6000);
    }

    function renderCustomers() {
        if (!tableBody) {
            return;
        }

        if (!customers.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="fas fa-users-slash me-2"></i>
                        No hay clientes registrados todavía
                    </td>
                </tr>
            `;
            return;
        }

        const rows = customers.map((customer) => {
            const token = customer.token ? escapeHtml(customer.token) : null;
            const tokenHtml = token
                ? `<code>${token}</code>`
                : '<span class="text-muted">Sin token</span>';
            const tokenMeta = customer.tokenUpdatedAt
                ? `<div class="text-muted small">Actualizado ${escapeHtml(relativeTime(customer.tokenUpdatedAt))}</div>`
                : '';

            const waiting = customer.waitingForToken ? true : false;
            const waitingBadge = waiting
                ? '<span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i>Esperando nuevo token</span>'
                : '<span class="badge bg-secondary"><i class="fas fa-check me-1"></i>Sin solicitud</span>';
            const waitingMeta = customer.waitingSince
                ? `<div class="text-muted small">desde ${escapeHtml(relativeTime(customer.waitingSince))}</div>`
                : '';

            const activeBadge = customer.isActive
                ? '<span class="badge bg-success"><i class="fas fa-circle me-1"></i>Activo</span>'
                : '<span class="badge bg-danger"><i class="fas fa-circle me-1"></i>Inactivo</span>';

            const lastSeen = customer.lastSeen ? `Último latido ${escapeHtml(relativeTime(customer.lastSeen))}` : '';
            const lastToken = customer.tokenUpdatedAt ? `Token ${escapeHtml(relativeTime(customer.tokenUpdatedAt))}` : '';
            const updates = [lastSeen, lastToken].filter(Boolean).join(' · ');
            const deviceDisplay = customer.deviceName ? customer.deviceName : 'Sin dispositivo';

            return `
                <tr data-customer-id="${escapeHtml(customer.customerId)}">
                    <td>
                        <div class="fw-semibold">${escapeHtml(customer.name || '—')}</div>
                        <div class="text-muted small">ID: ${escapeHtml(customer.customerId)}</div>
                        <div class="text-muted small">Equipo: ${escapeHtml(deviceDisplay)}</div>
                    </td>
                    <td>
                        ${tokenHtml}
                        ${tokenMeta}
                    </td>
                    <td>
                        ${activeBadge}
                        <div class="text-muted small">${customer.isActive ? 'Disponible para operar' : 'Bloqueado temporalmente'}</div>
                    </td>
                    <td>
                        ${waitingBadge}
                        ${waitingMeta}
                    </td>
                    <td>
                        ${updates ? escapeHtml(updates) : '<span class="text-muted">Sin actividad reciente</span>'}
                    </td>
                    <td class="text-end">
                        <div class="btn-group action-buttons" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-action="edit" title="Editar cliente">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button type="button" class="btn btn-sm ${waiting ? 'btn-outline-secondary' : 'btn-outline-success'}" data-action="await" data-waiting="${waiting ? '0' : '1'}" title="${waiting ? 'Cancelar espera' : 'Solicitar nuevo token'}">
                                <i class="${waiting ? 'fas fa-ban' : 'fas fa-rotate'}"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-dark" data-action="register-token" title="Registrar token manual">
                                <i class="fas fa-key"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="copy-token" title="Copiar token" ${token ? '' : 'disabled'}>
                                <i class="fas fa-copy"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-${customer.isActive ? 'warning' : 'success'}" data-action="toggle-active" title="${customer.isActive ? 'Desactivar cliente' : 'Activar cliente'}">
                                <i class="${customer.isActive ? 'fas fa-user-slash' : 'fas fa-user-check'}"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tableBody.innerHTML = rows.join('');
    }

    async function fetchCustomers(showNotification = false) {
        try {
            const response = await fetch(endpoints.list, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error('No se pudo obtener la lista de clientes');
            }

            const payload = await response.json();
            customers = Array.isArray(payload.customers) ? payload.customers : [];
            renderCustomers();
            if (showNotification) {
                pushAlert('success', 'Lista de clientes actualizada');
            }
        } catch (error) {
            console.error(error);
            pushAlert('danger', error.message || 'Error al actualizar los clientes');
        }
    }

    async function saveCustomer(data) {
        try {
            const response = await fetch(endpoints.save, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'No se pudo guardar el cliente');
            }

            pushAlert('success', payload.status === 'created' ? 'Cliente creado correctamente' : 'Cliente actualizado');
            if (customerModal) {
                customerModal.hide();
            }
            await fetchCustomers();
        } catch (error) {
            console.error(error);
            pushAlert('danger', error.message || 'Error al guardar el cliente');
        }
    }

    async function setWaitingForToken(customerId, waiting) {
        try {
            const response = await fetch(endpoints.await, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ customerId, waiting })
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'No se pudo actualizar la solicitud');
            }

            pushAlert('success', waiting ? 'El cliente ahora espera un nuevo token.' : 'Solicitud de nuevo token cancelada.');
            await fetchCustomers();
        } catch (error) {
            console.error(error);
            pushAlert('danger', error.message || 'Error al actualizar la solicitud de token');
        }
    }

    async function registerTokenManual(customer) {
        const tokenInput = window.prompt('Ingresa el nuevo token para el cliente ' + customer.customerId, customer.token || '');
        if (tokenInput === null) {
            return;
        }

        const token = tokenInput.trim();

        if (!token) {
            pushAlert('warning', 'Debes ingresar un token válido');
            return;
        }

        const deviceInput = window.prompt('Nombre del dispositivo para ' + customer.customerId, customer.deviceName || '');
        if (deviceInput === null) {
            return;
        }

        const deviceNameValue = deviceInput.trim();

        const payload = {
            customerId: customer.customerId,
            token,
            deviceName: deviceNameValue !== '' ? deviceNameValue : null
        };

        try {
            const response = await fetch(endpoints.register, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'No se pudo registrar el token');
            }

            pushAlert('success', 'Token registrado correctamente');
            await fetchCustomers();
        } catch (error) {
            console.error(error);
            pushAlert('danger', error.message || 'Error al registrar el token');
        }
    }

    async function toggleActiveStatus(customerId, isActive) {
        await saveCustomer({
            customerId,
            isActive
        });
    }

    function copyToken(token) {
        if (!token) {
            pushAlert('warning', 'No hay token para copiar');
            return;
        }

        if (!navigator.clipboard) {
            pushAlert('warning', 'El navegador no soporta copiado al portapapeles');
            return;
        }

        navigator.clipboard.writeText(token).then(() => {
            pushAlert('success', 'Token copiado al portapapeles');
        }).catch(() => {
            pushAlert('danger', 'No se pudo copiar el token');
        });
    }

    function openCreateModal() {
        if (customerModalLabel) {
            customerModalLabel.textContent = 'Nuevo cliente';
        }
        customerIdInput.removeAttribute('disabled');
        customerIdInput.value = '';
        customerNameInput.value = '';
        if (customerDeviceInput) {
            customerDeviceInput.value = '';
        }
        customerTokenInput.value = '';
        customerActiveInput.checked = true;
    }

    function openEditModal(customer) {
        if (customerModalLabel) {
            customerModalLabel.textContent = 'Editar cliente';
        }
        customerIdInput.value = customer.customerId;
        customerIdInput.setAttribute('disabled', 'disabled');
        customerNameInput.value = customer.name || '';
        if (customerDeviceInput) {
            customerDeviceInput.value = customer.deviceName || '';
        }
        customerTokenInput.value = customer.token || '';
        customerActiveInput.checked = customer.isActive !== false;
        if (customerModal) {
            customerModal.show();
        }
    }

    if (customerForm) {
        customerForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const customerId = customerIdInput.value.trim();
            const name = customerNameInput.value.trim();
            const deviceName = customerDeviceInput ? customerDeviceInput.value.trim() : '';
            const token = customerTokenInput.value.trim();
            const isActive = customerActiveInput.checked;

            if (!customerId) {
                pushAlert('warning', 'El ID del cliente es obligatorio');
                return;
            }

            const payload = {
                customerId,
                name: name || null,
                isActive
            };

            if (customerDeviceInput) {
                payload.deviceName = deviceName || null;
            }

            if (token) {
                payload.token = token;
            }

            await saveCustomer(payload);
        });
    }

    if (tableBody) {
        tableBody.addEventListener('click', function(event) {
            const button = event.target.closest('button[data-action]');
            if (!button) {
                return;
            }

            const row = button.closest('tr[data-customer-id]');
            if (!row) {
                return;
            }

            const customerId = row.getAttribute('data-customer-id');
            const customer = customers.find((item) => item.customerId === customerId);
            const action = button.getAttribute('data-action');

            if (!customer) {
                pushAlert('danger', 'No se encontró la información del cliente');
                return;
            }

            switch (action) {
                case 'edit':
                    openEditModal(customer);
                    break;
                case 'await': {
                    const waiting = button.getAttribute('data-waiting') === '1';
                    setWaitingForToken(customerId, waiting);
                    break;
                }
                case 'register-token':
                    registerTokenManual(customer);
                    break;
                case 'copy-token':
                    copyToken(customer.token || '');
                    break;
                case 'toggle-active':
                    toggleActiveStatus(customerId, !customer.isActive);
                    break;
            }
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => fetchCustomers(true));
    }

    if (addButton) {
        addButton.addEventListener('click', () => {
            openCreateModal();
            if (customerModal) {
                customerModal.show();
            }
        });
    }

    renderCustomers();
    fetchCustomers();
})();
</script>
<?php
$customScripts = ob_get_clean();
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
