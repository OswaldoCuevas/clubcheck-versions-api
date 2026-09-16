<?php
$title = 'Clientes';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-end align-items-center mb-3 gap-2">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="refreshCustomers">
                        <i class="fas fa-rotate"></i>
                        Actualizar
                    </button>
                    <button type="button" class="btn btn-primary" id="addCustomerBtn">
                        <i class="fas fa-user-plus"></i>
                        Agregar cliente
                    </button>
                </div>
            </div>

            <div id="alertsContainer"></div>

            <section class="admin-form-panel mb-3 d-none" id="customerFormPanel">
                <form id="customerForm">
                    <div class="admin-form-panel-title">
                        <div>
                            <span>Clientes</span>
                            <h2 id="customerFormTitle">Nuevo cliente</h2>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="closeCustomerFormBtn">Cerrar</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="customerId" class="form-label">ID de cliente</label>
                            <input type="text" class="form-control" id="customerId">
                            <div class="form-text">Dejalo vacio para generar un identificador automatico.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="customerName" class="form-label">Nombre del cliente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customerName" placeholder="Nombre legal o comercial" required>
                        </div>
                        <div class="col-md-6">
                            <label for="customerEmail" class="form-label">Correo <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="customerEmail" placeholder="cliente@dominio.com" autocomplete="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="customerPhone" class="form-label">Telefono <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="customerPhone" placeholder="+52 614 123 4567" autocomplete="tel" maxlength="30" required>
                        </div>
                        <div class="col-md-6">
                            <label for="customerAccessCode" class="form-label">AccessCode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="customerAccessCode" placeholder="Ejemplo: club_centro" maxlength="100" autocomplete="off" required>
                            <div class="form-text">Debe ser unico; se normaliza a minusculas, numeros y guiones bajos.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="customerDeviceName" class="form-label">Nombre del dispositivo</label>
                            <input type="text" class="form-control" id="customerDeviceName" placeholder="Ejemplo: POS-01" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="customerBillingId" class="form-label">ID de facturacion</label>
                            <input type="text" class="form-control" id="customerBillingId" placeholder="Ejemplo: FACT-001" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label for="customerPlanCode" class="form-label">Plan contratado</label>
                            <input type="text" class="form-control" id="customerPlanCode" placeholder="Ejemplo: PREMIUM-2025" maxlength="50" autocomplete="off">
                        </div>
                        <div class="col-12">
                            <label for="customerToken" class="form-label">Token actual (opcional)</label>
                            <input type="text" class="form-control" id="customerToken" placeholder="Token asignado al cliente">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="customerActive" checked>
                                <label class="form-check-label" for="customerActive">Cliente activo</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-outline-primary" id="cancelCustomerFormBtn">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </section>

            <section id="customersListPanel">
                <div id="customersFilter"></div>
                <div class="customers-list-meta">
                    <span id="customersTotalLabel">0 clientes</span>
                    <span id="customersPageLabel">Pagina 1</span>
                </div>
                <div id="customersGrid" class="customers-grid">
                    <div class="customers-empty">
                        <i class="fas fa-circle-notch fa-spin me-2"></i>
                        Cargando clientes...
                    </div>
                </div>
                <div id="customersPagination"></div>
            </section>
        </div>
    </div>
</div>

<!-- Modal: Mostrar Access Key -->
<div class="modal fade" id="accessKeyModal" tabindex="-1" aria-labelledby="accessKeyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="accessKeyModalLabel">
                    <i class="fas fa-key me-2"></i>Access Key Generado
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>¡Importante!</strong> Guarda este Access Key ahora. No se volverá a mostrar en texto plano.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Cliente:</label>
                    <div id="accessKeyCustomerName" class="text-muted"></div>
                    <small id="accessKeyCustomerId" class="text-muted"></small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Access Key:</label>
                    <div class="input-group">
                        <input type="text" class="form-control font-monospace fs-5 fw-bold text-primary" id="accessKeyValue" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="copyAccessKeyBtn">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Comparte este Access Key con el cliente para que pueda conectar su aplicación de escritorio.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<?php
$initialCustomersJson = json_encode($customers ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$endpointsJson = json_encode([
    'list' => app_url('/admin/api/customers'),
    'save' => app_url('/admin/api/customers/save'),
    'await' => app_url('/api/customers/token/await'),
    'register' => app_url('/api/customers/token/register'),
    'regenerateAccessKey' => app_url('/admin/api/customers/regenerate-access-key'),
    'delete' => app_url('/admin/api/customers/:customerId/delete'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$customStyles = <<<CSS
.customers-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

.customers-list-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 4px 0 14px;
    color: #5a7490;
    font-size: 13px;
    font-weight: 800;
}

.customer-card {
    position: relative;
    min-height: 100%;
    padding: 18px;
    border: 1px solid #d7eafd;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 16px 34px rgba(47, 128, 237, 0.08);
}

.customer-card-header {
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr) auto auto;
    align-items: start;
    gap: 12px;
}

.customer-avatar {
    width: 46px;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: #eaf8ff;
    color: #087cba;
    font-weight: 800;
}

.customer-title {
    min-width: 0;
}

.customer-title strong {
    display: block;
    color: #0f2740;
    font-size: 16px;
    line-height: 1.25;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.customer-title small,
.customer-meta {
    color: #5a7490;
    font-size: 12px;
}

.customer-summary-line {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.customer-summary-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 26px;
    padding: 0 9px;
    border-radius: 999px;
    background: #f0f8ff;
    color: #075f8e;
    font-size: 12px;
    font-weight: 800;
}

.customer-card-toggle {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #cfe8f8;
    border-radius: 12px;
    background: #ffffff;
    color: #087cba;
    box-shadow: 0 10px 22px rgba(47, 128, 237, 0.08);
}

.customer-card-toggle:hover {
    background: #eaf8ff;
    border-color: #8cd4f4;
    color: #075f8e;
}

.customer-card-toggle i {
    transition: transform 0.18s ease;
}

.customer-card.expanded .customer-card-toggle i {
    transform: rotate(180deg);
}

.customer-card code {
    display: inline-block;
    max-width: 100%;
    padding: 0.15rem 0.35rem;
    border-radius: 7px;
    background: rgba(18, 153, 220, 0.08);
    color: #075f8e;
    font-size: 0.8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: bottom;
}

.customer-card-body {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 16px;
}

.customer-card:not(.expanded) .customer-card-body {
    display: none;
}

.customer-card-field {
    min-width: 0;
    padding: 10px;
    border-radius: 12px;
    background: #f8fbff;
}

.customer-card-field span {
    display: block;
    margin-bottom: 4px;
    color: #5a7490;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}

.customer-card-field strong {
    display: flex;
    min-width: 0;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    color: #0f2740;
    font-size: 13px;
}

.customer-copy-value {
    min-width: 0;
    display: inline-block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.customer-copy-btn {
    width: 30px;
    height: 30px;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #cfe8f8;
    border-radius: 10px;
    background: #ffffff;
    color: #087cba;
}

.customer-copy-btn:hover {
    background: #eaf8ff;
    border-color: #8cd4f4;
    color: #075f8e;
}

.customer-copy-hint {
    min-height: 16px;
    margin-top: 4px;
    color: #18a058;
    font-size: 11px;
    font-weight: 800;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.customer-copy-hint.show {
    opacity: 1;
}

.customer-card-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #e5f2fb;
}

.customer-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 28px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
}

.customer-status.active {
    background: #e7f9ef;
    color: #147a42;
}

.customer-status.inactive {
    background: #fff1f3;
    color: #c62840;
}

.customer-status.waiting {
    background: #fff7df;
    color: #936300;
}

.customers-empty {
    grid-column: 1 / -1;
    padding: 42px 18px;
    border: 1px dashed #bde2f8;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.72);
    color: #5a7490;
    text-align: center;
    font-weight: 700;
}

@media (max-width: 480px) {
    .customers-grid {
        grid-template-columns: 1fr;
    }

    .customer-card-header {
        grid-template-columns: 42px minmax(0, 1fr) auto;
    }

    .customer-card-header .admin-action-menu {
        grid-column: 3;
    }

    .customer-card-toggle {
        grid-column: 1 / -1;
        width: 100%;
    }

    .customer-card-body {
        grid-template-columns: 1fr;
    }
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
    let customerFilters = {
        search: '',
        status: 'active',
        waiting: 'all',
        sortBy: 'name',
        direction: 'asc'
    };
    let currentPage = 1;
    const pageSize = 10;
    const expandedCustomers = new Set();

    const customerGrid = document.getElementById('customersGrid');
    const customersPagination = document.getElementById('customersPagination');
    const customersTotalLabel = document.getElementById('customersTotalLabel');
    const customersPageLabel = document.getElementById('customersPageLabel');
    const refreshButton = document.getElementById('refreshCustomers');
    const addButton = document.getElementById('addCustomerBtn');
    const alertsContainer = document.getElementById('alertsContainer');
    const customersListPanel = document.getElementById('customersListPanel');
    const customerFormPanel = document.getElementById('customerFormPanel');
    const closeCustomerFormBtn = document.getElementById('closeCustomerFormBtn');
    const cancelCustomerFormBtn = document.getElementById('cancelCustomerFormBtn');
    const customerForm = document.getElementById('customerForm');
    const customerIdInput = document.getElementById('customerId');
    const customerNameInput = document.getElementById('customerName');
    const customerEmailInput = document.getElementById('customerEmail');
    const customerPhoneInput = document.getElementById('customerPhone');
    const customerAccessCodeInput = document.getElementById('customerAccessCode');
    const customerDeviceInput = document.getElementById('customerDeviceName');
    const customerBillingInput = document.getElementById('customerBillingId');
    const customerPlanInput = document.getElementById('customerPlanCode');
    const customerTokenInput = document.getElementById('customerToken');
    const customerActiveInput = document.getElementById('customerActive');
    const customerFormTitle = document.getElementById('customerFormTitle');

    const accessKeyModalEl = document.getElementById('accessKeyModal');
    const accessKeyModal = accessKeyModalEl ? new bootstrap.Modal(accessKeyModalEl) : null;
    const accessKeyValue = document.getElementById('accessKeyValue');
    const accessKeyCustomerName = document.getElementById('accessKeyCustomerName');
    const accessKeyCustomerId = document.getElementById('accessKeyCustomerId');
    const copyAccessKeyBtn = document.getElementById('copyAccessKeyBtn');

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
        if (window.AdminToast && typeof window.AdminToast.show === 'function') {
            window.AdminToast.show(message, type, { duration: 5000 });
            return;
        }

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

    function initials(name, fallback) {
        const source = String(name || fallback || '?').trim();
        return source.split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || '?';
    }

    function renderCopyField(label, value, emptyText = 'Sin dato', options = {}) {
        const rawValue = value === null || value === undefined || value === '' ? '' : String(value);
        const displayValue = rawValue || emptyText;
        const codeClass = options.code ? ' as-code' : '';
        const codeOpen = options.code ? '<code>' : '';
        const codeClose = options.code ? '</code>' : '';
        const disabled = rawValue ? '' : 'disabled';
        const meta = options.meta ? `<small class="customer-meta">${escapeHtml(options.meta)}</small>` : '';

        return `
            <div class="customer-card-field">
                <span>${escapeHtml(label)}</span>
                <strong>
                    <span class="customer-copy-value${codeClass}" title="${escapeHtml(displayValue)}">${codeOpen}${escapeHtml(displayValue)}${codeClose}</span>
                    <button type="button" class="customer-copy-btn" data-copy-value="${escapeHtml(rawValue)}" ${disabled} aria-label="Copiar ${escapeHtml(label)}">
                        <i class="fas fa-copy"></i>
                    </button>
                </strong>
                ${meta}
                <div class="customer-copy-hint">Copiado</div>
            </div>
        `;
    }

    function customerActions(customer, waiting, token) {
        return window.AdminActionMenu.render([
            { name: 'Editar cliente', icon: 'fas fa-pen', action: 'edit' },
            // { name: 'Generar Access Key', icon: 'fas fa-key', action: 'regenerate-access-key' },
            // {
            //     name: waiting ? 'Cancelar espera' : 'Solicitar token',
            //     icon: waiting ? 'fas fa-ban' : 'fas fa-rotate',
            //     action: 'await',
            //     attrs: { 'data-waiting': waiting ? '0' : '1' }
            // },
            // { name: 'Registrar token manual', icon: 'fas fa-key', action: 'register-token' },
            // { name: 'Copiar token', icon: 'fas fa-copy', action: 'copy-token', disabled: !token },
            {
                name: customer.isActive ? 'Desactivar cliente' : 'Activar cliente',
                icon: customer.isActive ? 'fas fa-user-slash' : 'fas fa-user-check',
                action: 'toggle-active'
            },
            { name: 'Eliminar cliente', icon: 'fas fa-trash', action: 'delete', tone: 'danger' }
        ], { label: 'Opciones del cliente' });
    }

    function customerSortValue(customer, field) {
        const values = {
            name: customer.name || customer.customerId || '',
            email: customer.email || '',
            phone: customer.phone || '',
            accessCode: customer.codeAccess || '',
            plan: customer.planCode || '',
            version: customer.clientVersion || '',
            lastSeen: customer.lastSeen || 0
        };

        return values[field] ?? values.name;
    }

    function filteredCustomers() {
        const search = String(customerFilters.search || '').trim().toLowerCase();
        const filtered = customers.filter((customer) => {
            const matchesSearch = !search || [
                customer.name,
                customer.customerId,
                customer.email,
                customer.phone,
                customer.codeAccess,
                customer.billingId,
                customer.planCode,
                customer.deviceName,
                customer.clientVersion
            ].some((value) => String(value || '').toLowerCase().includes(search));

            const matchesStatus = customerFilters.status === 'all'
                || (customerFilters.status === 'active' && customer.isActive)
                || (customerFilters.status === 'inactive' && !customer.isActive);

            const matchesWaiting = customerFilters.waiting === 'all'
                || (customerFilters.waiting === 'waiting' && customer.waitingForToken)
                || (customerFilters.waiting === 'ready' && !customer.waitingForToken);

            return matchesSearch && matchesStatus && matchesWaiting;
        });

        const direction = customerFilters.direction === 'desc' ? -1 : 1;
        return filtered.sort((a, b) => {
            const sortBy = customerFilters.sortBy || 'name';
            const aValue = customerSortValue(a, sortBy);
            const bValue = customerSortValue(b, sortBy);
            if (sortBy === 'lastSeen') {
                return (Number(aValue) - Number(bValue)) * direction;
            }

            return String(aValue).localeCompare(String(bValue), 'es', { sensitivity: 'base' }) * direction;
        });
    }

    function renderCustomers() {
        if (!customerGrid) {
            return;
        }

        const visibleCustomers = filteredCustomers();
        const page = window.AdminPagination
            ? window.AdminPagination.range(visibleCustomers, currentPage, pageSize)
            : { items: visibleCustomers, page: 1, pageSize, totalItems: visibleCustomers.length, totalPages: 1, from: visibleCustomers.length ? 1 : 0, to: visibleCustomers.length };
        currentPage = page.page;

        if (customersTotalLabel) {
            const totalText = page.totalItems === customers.length
                ? (page.totalItems === 1 ? '1 cliente' : `${page.totalItems} clientes`)
                : `${page.totalItems} de ${customers.length} clientes`;
            customersTotalLabel.textContent = totalText;
        }

        if (customersPageLabel) {
            customersPageLabel.textContent = `Pagina ${page.page} de ${page.totalPages}`;
        }

        if (window.AdminPagination) {
            window.AdminPagination.render({
                container: customersPagination,
                page: page.page,
                pageSize: page.pageSize,
                totalItems: page.totalItems,
                summaryLabel: 'Mostrando clientes',
                label: 'Paginacion de clientes',
                onChange: (nextPage) => {
                    currentPage = nextPage;
                    renderCustomers();
                    if (customerGrid) {
                        customerGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        }

        if (!visibleCustomers.length) {
            customerGrid.innerHTML = `
                <div class="customers-empty">
                    <i class="fas fa-users-slash me-2"></i>
                    No hay clientes con esos filtros
                </div>
            `;
            return;
        }

        customerGrid.innerHTML = page.items.map((customer) => {
            const token = customer.token ? String(customer.token) : '';
            const tokenMeta = customer.tokenUpdatedAt ? `Actualizado ${escapeHtml(relativeTime(customer.tokenUpdatedAt))}` : 'Sin actualizacion';
            const waiting = customer.waitingForToken ? true : false;
            const waitingText = waiting ? 'Esperando nuevo token' : 'Sin solicitud';
            const waitingMeta = customer.waitingSince ? `desde ${escapeHtml(relativeTime(customer.waitingSince))}` : 'Listo para operar';
            const activeClass = customer.isActive ? 'active' : 'inactive';
            const activeText = customer.isActive ? 'Activo' : 'Inactivo';
            const lastSeen = customer.lastSeen ? `Ultimo latido ${escapeHtml(relativeTime(customer.lastSeen))}` : 'Sin actividad reciente';
            const phoneDisplay = customer.phone ? customer.phone : 'Sin telefono';
            const planDisplay = customer.planCode ? customer.planCode : 'Sin plan';
            const versionMeta = customer.clientVersionUpdatedAt ? `Actualizado ${escapeHtml(relativeTime(customer.clientVersionUpdatedAt))}` : 'Sin actualizacion';
            const expanded = expandedCustomers.has(customer.customerId);

            return `
                <article class="customer-card ${expanded ? 'expanded' : ''}" data-customer-id="${escapeHtml(customer.customerId)}">
                    <div class="customer-card-header">
                        <div class="customer-avatar">${escapeHtml(initials(customer.name, customer.customerId))}</div>
                        <div class="customer-title">
                            <strong title="${escapeHtml(customer.name || customer.customerId || '')}">${escapeHtml(customer.name || 'Sin nombre')}</strong>
                            <small>ID: ${escapeHtml(customer.customerId)}</small>
                            <div class="customer-meta">${escapeHtml(customer.email || 'Sin email')}</div>
                            <div class="customer-meta">${escapeHtml(phoneDisplay)}</div>
                            <div class="customer-summary-line">
                                <span class="customer-summary-pill"><i class="fas fa-layer-group"></i>${escapeHtml(planDisplay)}</span>
                                <span class="customer-summary-pill"><i class="fas fa-key"></i>${escapeHtml(customer.codeAccess || 'Sin AccessCode')}</span>
                            </div>
                        </div>
                        ${customerActions(customer, waiting, token)}
                        <button type="button" class="customer-card-toggle" data-toggle-customer="${escapeHtml(customer.customerId)}" aria-label="${expanded ? 'Contraer cliente' : 'Desplegar cliente'}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>

                    <div class="customer-card-body">
                        ${renderCopyField('Customer ID', customer.customerId, 'Sin ID', { code: true })}
                        ${renderCopyField('AccessCode', customer.codeAccess, 'Sin AccessCode', { code: true })}
                        ${renderCopyField('Correo', customer.email, 'Sin email')}
                        ${renderCopyField('Telefono', customer.phone, 'Sin telefono')}
                        ${renderCopyField('Version', customer.clientVersion, 'Sin version', { meta: versionMeta })}
                        ${renderCopyField('Plan', customer.planCode, 'Sin plan')}
                        ${renderCopyField('Facturacion', customer.billingId, 'Sin billing')}
                        ${renderCopyField('Equipo', customer.deviceName, 'Sin dispositivo')}
                        ${renderCopyField('Token', token, 'Sin token', { code: true, meta: tokenMeta })}
                    </div>

                    <div class="customer-card-footer">
                        <span class="customer-status ${activeClass}"><i class="fas fa-circle"></i>${activeText}</span>
                        <span class="customer-status ${waiting ? 'waiting' : 'active'}"><i class="${waiting ? 'fas fa-hourglass-half' : 'fas fa-check'}"></i>${waitingText}</span>
                        <span class="customer-meta">${waiting ? waitingMeta : lastSeen}</span>
                    </div>
                </article>
            `;
        }).join('');
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

            const created = (payload.status === 'created');
            let message = created ? 'Cliente creado correctamente.' : 'Cliente actualizado.';

            if (payload.accessKey) {
                message += ' AccessKey: ' + payload.accessKey + ' (guárdala, no se volverá a mostrar).';
            }

            pushAlert('success', message);
            showCustomerList();
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

    async function regenerateAccessKey(customer) {
        const confirmed = window.confirm(
            `¿Estás seguro de generar un nuevo Access Key para ${customer.name || customer.customerId}?\n\n` +
            'El Access Key anterior quedará inválido y el cliente deberá usar el nuevo para conectarse.'
        );

        if (!confirmed) {
            return;
        }

        try {
            const response = await fetch(endpoints.regenerateAccessKey, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ customerId: customer.customerId })
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'No se pudo regenerar el Access Key');
            }

            if (accessKeyValue) {
                accessKeyValue.value = payload.accessKey;
            }
            if (accessKeyCustomerName) {
                accessKeyCustomerName.textContent = payload.customer.name || 'Sin nombre';
            }
            if (accessKeyCustomerId) {
                accessKeyCustomerId.textContent = 'ID: ' + payload.customer.customerId;
            }

            if (accessKeyModal) {
                accessKeyModal.show();
            }

            await fetchCustomers();
        } catch (error) {
            console.error(error);
            pushAlert('danger', error.message || 'Error al regenerar el Access Key');
        }
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

    function copyCustomerField(button) {
        const value = button ? button.getAttribute('data-copy-value') : '';
        if (!value) {
            return;
        }

        const field = button.closest('.customer-card-field');
        const hint = field ? field.querySelector('.customer-copy-hint') : null;
        const markCopied = () => {
            button.innerHTML = '<i class="fas fa-check"></i>';
            if (hint) {
                hint.classList.add('show');
            }
            setTimeout(() => {
                button.innerHTML = '<i class="fas fa-copy"></i>';
                if (hint) {
                    hint.classList.remove('show');
                }
            }, 1600);
        };

        if (!navigator.clipboard) {
            const textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
            markCopied();
            return;
        }

        navigator.clipboard.writeText(value).then(markCopied).catch(() => {
            pushAlert('danger', 'No se pudo copiar el dato');
        });
    }

    function showCustomerList() {
        if (customerFormPanel) customerFormPanel.classList.add('d-none');
        if (customersListPanel) customersListPanel.classList.remove('d-none');
    }

    function showCustomerForm() {
        if (customersListPanel) customersListPanel.classList.add('d-none');
        if (customerFormPanel) customerFormPanel.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function openCreateForm() {
        if (customerFormTitle) {
            customerFormTitle.textContent = 'Nuevo cliente';
        }
        customerIdInput.removeAttribute('disabled');
        customerIdInput.value = '';
        customerNameInput.value = '';
        if (customerEmailInput) {
            customerEmailInput.value = '';
        }
        if (customerPhoneInput) {
            customerPhoneInput.value = '';
        }
        if (customerAccessCodeInput) {
            customerAccessCodeInput.value = '';
        }
        if (customerDeviceInput) {
            customerDeviceInput.value = '';
        }
        if (customerBillingInput) {
            customerBillingInput.value = '';
        }
        if (customerPlanInput) {
            customerPlanInput.value = '';
        }
        customerTokenInput.value = '';
        customerActiveInput.checked = true;
        showCustomerForm();
    }

    function openEditForm(customer) {
        if (customerFormTitle) {
            customerFormTitle.textContent = 'Editar cliente';
        }
        customerIdInput.value = customer.customerId;
        customerIdInput.setAttribute('disabled', 'disabled');
        customerNameInput.value = customer.name || '';
        if (customerEmailInput) {
            customerEmailInput.value = customer.email || '';
        }
        if (customerPhoneInput) {
            customerPhoneInput.value = customer.phone || '';
        }
        if (customerAccessCodeInput) {
            customerAccessCodeInput.value = customer.codeAccess || '';
        }
        if (customerDeviceInput) {
            customerDeviceInput.value = customer.deviceName || '';
        }
        if (customerBillingInput) {
            customerBillingInput.value = customer.billingId || '';
        }
        if (customerPlanInput) {
            customerPlanInput.value = customer.planCode || '';
        }
        customerTokenInput.value = customer.token || '';
        customerActiveInput.checked = customer.isActive !== false;
        showCustomerForm();
    }

    if (customerForm) {
        customerForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const customerId = customerIdInput.value.trim();
            const name = customerNameInput.value.trim();
            const email = customerEmailInput ? customerEmailInput.value.trim() : '';
            const phone = customerPhoneInput ? customerPhoneInput.value.trim() : '';
            const codeAccess = customerAccessCodeInput ? customerAccessCodeInput.value.trim() : '';
            const deviceName = customerDeviceInput ? customerDeviceInput.value.trim() : '';
            const billingId = customerBillingInput ? customerBillingInput.value.trim() : '';
            const planCode = customerPlanInput ? customerPlanInput.value.trim() : '';
            const token = customerTokenInput.value.trim();
            const isActive = customerActiveInput.checked;

            const isEditing = customerIdInput.hasAttribute('disabled');

            if (isEditing && !customerId) {
                pushAlert('warning', 'El ID del cliente es obligatorio al editar');
                return;
            }

            if (!codeAccess) {
                pushAlert('warning', 'El AccessCode es obligatorio');
                return;
            }

            if (!name) {
                pushAlert('warning', 'El nombre es obligatorio');
                return;
            }

            if (!email) {
                pushAlert('warning', 'El correo es obligatorio');
                return;
            }

            if (!phone) {
                pushAlert('warning', 'El telefono es obligatorio');
                return;
            }

            const duplicateCodeAccess = customers.some((customer) => {
                return customer.codeAccess === codeAccess && customer.customerId !== customerId;
            });

            if (duplicateCodeAccess) {
                pushAlert('warning', 'El AccessCode ya está registrado para otro cliente');
                return;
            }

            const duplicateEmail = customers.some((customer) => {
                return String(customer.email || '').toLowerCase() === email.toLowerCase() && customer.customerId !== customerId;
            });

            if (duplicateEmail) {
                pushAlert('warning', 'El correo ya esta registrado para otro cliente');
                return;
            }

            const payload = {
                name: name || null,
                email,
                phone,
                codeAccess,
                isActive
            };

            if (customerId) {
                payload.customerId = customerId;
            }

            if (customerDeviceInput) {
                payload.deviceName = deviceName || null;
            }

            if (customerBillingInput) {
                payload.billingId = billingId || null;
            }

            if (customerPlanInput) {
                payload.planCode = planCode || null;
            }

            if (token) {
                payload.token = token;
            }

            await saveCustomer(payload);
        });
    }

    if (customerGrid) {
        customerGrid.addEventListener('click', function(event) {
            const copyButton = event.target.closest('[data-copy-value]');
            if (copyButton) {
                event.preventDefault();
                copyCustomerField(copyButton);
                return;
            }

            const toggleButton = event.target.closest('[data-toggle-customer]');
            if (toggleButton) {
                event.preventDefault();
                const targetId = toggleButton.getAttribute('data-toggle-customer');
                if (expandedCustomers.has(targetId)) {
                    expandedCustomers.delete(targetId);
                } else {
                    expandedCustomers.add(targetId);
                }
                renderCustomers();
                return;
            }

            const button = event.target.closest('button[data-action]');
            if (!button) {
                return;
            }

            const card = button.closest('[data-customer-id]');
            if (!card) {
                return;
            }

            const customerId = card.getAttribute('data-customer-id');
            const customer = customers.find((item) => item.customerId === customerId);
            const action = button.getAttribute('data-action');

            if (!customer) {
                pushAlert('danger', 'No se encontró la información del cliente');
                return;
            }

            switch (action) {
                case 'edit':
                    openEditForm(customer);
                    break;
                case 'regenerate-access-key':
                    regenerateAccessKey(customer);
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
                case 'delete':
                    deleteCustomer(customer);
                    break;
            }
        });
    }

    async function deleteCustomer(customer) {
        const confirmed = window.confirm(`¿Eliminar cliente ${customer.name || customer.customerId}? Esta acción no se puede deshacer.`);
        if (!confirmed) return;

        try {
            const url = endpoints.delete.replace(':customerId', encodeURIComponent(customer.customerId));
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                }
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error || 'No se pudo eliminar el cliente');
            }

            pushAlert('success', payload.message || 'Cliente eliminado');
            await fetchCustomers();
        } catch (error) {
            console.error(error);
            pushAlert('danger', error.message || 'Error al eliminar el cliente');
        }
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => fetchCustomers(true));
    }

    if (addButton) {
        addButton.addEventListener('click', openCreateForm);
    }

    if (closeCustomerFormBtn) {
        closeCustomerFormBtn.addEventListener('click', showCustomerList);
    }

    if (cancelCustomerFormBtn) {
        cancelCustomerFormBtn.addEventListener('click', showCustomerList);
    }

    if (copyAccessKeyBtn) {
        copyAccessKeyBtn.addEventListener('click', () => {
            const value = accessKeyValue ? accessKeyValue.value : '';
            if (!value) {
                return;
            }

            if (!navigator.clipboard) {
                accessKeyValue.select();
                document.execCommand('copy');
                pushAlert('success', 'Access Key copiado');
                return;
            }

            navigator.clipboard.writeText(value).then(() => {
                pushAlert('success', 'Access Key copiado al portapapeles');
                copyAccessKeyBtn.innerHTML = '<i class="fas fa-check"></i> Copiado';
                setTimeout(() => {
                    copyAccessKeyBtn.innerHTML = '<i class="fas fa-copy"></i> Copiar';
                }, 2000);
            }).catch(() => {
                pushAlert('danger', 'No se pudo copiar el Access Key');
            });
        });
    }

    window.AdminFilters.mount({
        container: '#customersFilter',
        title: 'Filtros de clientes',
        defaults: {
            search: '',
            status: 'active',
            waiting: 'all',
            sortBy: 'name',
            direction: 'asc'
        },
        values: customerFilters,
        fields: [
            {
                name: 'search',
                label: 'Buscar',
                type: 'search',
                placeholder: 'Buscar por nombre, email, telefono, ID o plan'
            },
            {
                name: 'sortBy',
                label: 'Ordenar por',
                chipLabel: 'Ordenar por',
                type: 'select',
                showChipWhenDefault: true,
                clearValue: '',
                options: [
                    { value: 'name', label: 'Nombre' },
                    { value: 'email', label: 'Correo' },
                    { value: 'phone', label: 'Telefono' },
                    { value: 'accessCode', label: 'AccessCode' },
                    { value: 'plan', label: 'Plan' },
                    { value: 'version', label: 'Version' },
                    { value: 'lastSeen', label: 'Ultima actividad' }
                ]
            },
            {
                name: 'direction',
                label: 'Direccion',
                chipLabel: 'Direccion',
                type: 'select',
                showChipWhenDefault: true,
                clearValue: '',
                options: [
                    { value: 'asc', label: 'Ascendente' },
                    { value: 'desc', label: 'Descendente' }
                ]
            },
            {
                name: 'status',
                label: 'Estado',
                type: 'select',
                showChipWhenDefault: true,
                clearValue: 'all',
                hideChipValues: ['all'],
                options: [
                    { value: 'all', label: 'Todos' },
                    { value: 'active', label: 'Activos' },
                    { value: 'inactive', label: 'Inactivos' }
                ]
            },
            {
                name: 'waiting',
                label: 'Token',
                type: 'select',
                options: [
                    { value: 'all', label: 'Todos' },
                    { value: 'waiting', label: 'Esperando token' },
                    { value: 'ready', label: 'Sin solicitud' }
                ]
            }
        ],
        onApply: (values) => {
            customerFilters = values;
            currentPage = 1;
            renderCustomers();
        }
    });

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
