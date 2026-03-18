<?php
$title = 'Tokens JWT - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h3 class="mb-1">Tokens JWT de Clientes</h3>
                    <p class="text-muted mb-0">Monitorea tokens de acceso y las IPs desde donde acceden los clientes.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al panel
                    </a>
                    <button type="button" class="btn btn-outline-secondary" id="refreshData">
                        <i class="fas fa-rotate"></i>
                        Actualizar
                    </button>
                </div>
            </div>

            <div id="alertsContainer"></div>

            <!-- Estadísticas -->
            <div class="row mb-4" id="statsCards">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0" id="statTotalCustomers">-</h4>
                            <small class="text-muted">Clientes totales</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-key fa-2x text-success mb-2"></i>
                            <h4 class="mb-0" id="statActiveJwt">-</h4>
                            <small class="text-muted">Tokens activos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0" id="statExpiredJwt">-</h4>
                            <small class="text-muted">Tokens expirados</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                            <h4 class="mb-0" id="statMultipleIps">-</h4>
                            <small class="text-muted">Múltiples IPs</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" placeholder="Buscar por nombre, email o ID...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="filterStatus">
                                <option value="all">Todos los estados</option>
                                <option value="active">Con token activo</option>
                                <option value="expired">Token expirado</option>
                                <option value="none">Sin token JWT</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm" id="filterIps">
                                <option value="all">Todas las IPs</option>
                                <option value="multiple">Múltiples IPs (24h)</option>
                                <option value="flagged">IPs marcadas</option>
                            </select>
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-sm btn-outline-secondary" id="clearFilters">
                                <i class="fas fa-times"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de clientes -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="customersTable">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Cliente</th>
                                    <th scope="col">Token JWT</th>
                                    <th scope="col">IPs de acceso</th>
                                    <th scope="col">Última actividad</th>
                                    <th scope="col" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
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

<!-- Modal: Crear nuevo token JWT -->
<div class="modal fade" id="createTokenModal" tabindex="-1" aria-labelledby="createTokenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="createTokenModalLabel">
                    <i class="fas fa-key me-2"></i>Crear nuevo Token JWT
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>¡Atención!</strong> Esto revocará cualquier token JWT existente para este cliente. 
                    La aplicación de escritorio necesitará volver a autenticarse.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Cliente:</label>
                    <div id="createTokenCustomerName" class="text-muted"></div>
                    <small id="createTokenCustomerId" class="text-muted"></small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Duración del token:</label>
                    <select class="form-select" id="tokenDuration">
                        <option value="604800">7 días</option>
                        <option value="2592000" selected>30 días (recomendado)</option>
                        <option value="7776000">90 días</option>
                        <option value="31536000">1 año</option>
                        <option value="0">Sin caducidad</option>
                    </select>
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i> Los tokens sin caducidad nunca expiran, pero pueden ser revocados manualmente.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmCreateToken">
                    <i class="fas fa-key me-2"></i>Crear Token
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Revocar token JWT -->
<div class="modal fade" id="revokeTokenModal" tabindex="-1" aria-labelledby="revokeTokenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="revokeTokenModalLabel">
                    <i class="fas fa-ban me-2"></i>Revocar Token JWT
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-3">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>¡Advertencia!</strong> El cliente perderá acceso inmediatamente y necesitará 
                    autenticarse de nuevo con un nuevo token.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Cliente:</label>
                    <div id="revokeTokenCustomerName" class="text-muted"></div>
                    <small id="revokeTokenCustomerId" class="text-muted"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmRevokeToken">
                    <i class="fas fa-ban me-2"></i>Revocar Token
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Ver IPs del cliente -->
<div class="modal fade" id="viewIpsModal" tabindex="-1" aria-labelledby="viewIpsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewIpsModalLabel">
                    <i class="fas fa-network-wired me-2"></i>IPs de acceso
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Cliente:</strong> <span id="viewIpsCustomerName"></span>
                    <br><small class="text-muted" id="viewIpsCustomerId"></small>
                </div>
                <div id="ipsAlertContainer"></div>
                <div class="table-responsive">
                    <table class="table table-sm" id="ipsTable">
                        <thead>
                            <tr>
                                <th>IP</th>
                                <th>Ubicación</th>
                                <th>Dispositivo</th>
                                <th>Primer acceso</th>
                                <th>Último acceso</th>
                                <th>Accesos</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    <i class="fas fa-circle-notch fa-spin me-2"></i>
                                    Cargando...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
$endpointsJson = json_encode([
    'list' => app_url('/admin/api/jwt-tokens'),
    'create' => app_url('/admin/api/jwt-tokens/create'),
    'revoke' => app_url('/admin/api/jwt-tokens/revoke'),
    'customerIps' => app_url('/admin/api/jwt-tokens/customer/{customerId}/ips'),
    'flagIp' => app_url('/admin/api/jwt-tokens/ips/{id}/flag'),
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

.ip-badge {
    font-size: 0.8rem;
    font-family: monospace;
}

.warning-row {
    background-color: rgba(255, 193, 7, 0.1);
}

.ip-list {
    max-height: 100px;
    overflow-y: auto;
}

#alertsContainer .alert {
    margin-bottom: 1rem;
}

.stats-card-value {
    font-size: 1.75rem;
    font-weight: bold;
}
CSS;

ob_start();
?>
<script>
(function() {
    const endpoints = <?= $endpointsJson ?>;
    let customers = [];
    let stats = {};

    // DOM Elements
    const tableBody = document.querySelector('#customersTable tbody');
    const refreshButton = document.getElementById('refreshData');
    const alertsContainer = document.getElementById('alertsContainer');
    const searchInput = document.getElementById('searchInput');
    const filterStatus = document.getElementById('filterStatus');
    const filterIps = document.getElementById('filterIps');
    const clearFiltersBtn = document.getElementById('clearFilters');

    // Stats elements
    const statTotalCustomers = document.getElementById('statTotalCustomers');
    const statActiveJwt = document.getElementById('statActiveJwt');
    const statExpiredJwt = document.getElementById('statExpiredJwt');
    const statMultipleIps = document.getElementById('statMultipleIps');

    // Modal elements
    const createTokenModalEl = document.getElementById('createTokenModal');
    const createTokenModal = createTokenModalEl ? new bootstrap.Modal(createTokenModalEl) : null;
    const createTokenCustomerName = document.getElementById('createTokenCustomerName');
    const createTokenCustomerId = document.getElementById('createTokenCustomerId');
    const tokenDuration = document.getElementById('tokenDuration');
    const confirmCreateToken = document.getElementById('confirmCreateToken');

    const revokeTokenModalEl = document.getElementById('revokeTokenModal');
    const revokeTokenModal = revokeTokenModalEl ? new bootstrap.Modal(revokeTokenModalEl) : null;
    const revokeTokenCustomerName = document.getElementById('revokeTokenCustomerName');
    const revokeTokenCustomerId = document.getElementById('revokeTokenCustomerId');
    const confirmRevokeToken = document.getElementById('confirmRevokeToken');

    const viewIpsModalEl = document.getElementById('viewIpsModal');
    const viewIpsModal = viewIpsModalEl ? new bootstrap.Modal(viewIpsModalEl) : null;
    const viewIpsCustomerName = document.getElementById('viewIpsCustomerName');
    const viewIpsCustomerIdEl = document.getElementById('viewIpsCustomerId');
    const ipsTable = document.querySelector('#ipsTable tbody');
    const ipsAlertContainer = document.getElementById('ipsAlertContainer');

    let currentCustomerId = null;

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
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function relativeTime(dateStr) {
        if (!dateStr) return 'sin datos';
        const date = new Date(dateStr);
        const now = new Date();
        const diff = (now - date) / 1000;

        if (diff < 60) return 'hace ' + Math.round(diff) + 's';
        const minutes = Math.floor(diff / 60);
        if (minutes < 60) return 'hace ' + minutes + 'm';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return 'hace ' + hours + 'h';
        const days = Math.floor(hours / 24);
        return 'hace ' + days + 'd';
    }

    function formatExpiresIn(seconds) {
        if (!seconds || seconds <= 0) return 'Expirado';
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        if (days > 0) return days + 'd ' + hours + 'h';
        if (hours > 0) return hours + 'h';
        const minutes = Math.floor(seconds / 60);
        return minutes + 'm';
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
            if (alert) alert.close();
        }, 6000);
    }

    async function copyToClipboard(text, successMessage) {
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
                pushAlert('success', successMessage || 'Copiado al portapapeles');
            } else {
                // Fallback para navegadores antiguos
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                pushAlert('success', successMessage || 'Copiado al portapapeles');
            }
        } catch (error) {
            pushAlert('danger', 'Error al copiar: ' + error.message);
        }
    }

    function updateStats() {
        if (statTotalCustomers) statTotalCustomers.textContent = stats.totalCustomers || 0;
        if (statActiveJwt) statActiveJwt.textContent = stats.activeJwt || 0;
        if (statExpiredJwt) statExpiredJwt.textContent = stats.expiredJwt || 0;
        
        // Count customers with multiple IPs
        const multipleIpsCount = customers.filter(c => c.hasMultipleIps).length;
        if (statMultipleIps) statMultipleIps.textContent = multipleIpsCount;
    }

    function getFilteredCustomers() {
        let filtered = [...customers];
        
        // Search filter
        const search = (searchInput?.value || '').toLowerCase().trim();
        if (search) {
            filtered = filtered.filter(c => 
                (c.name || '').toLowerCase().includes(search) ||
                (c.email || '').toLowerCase().includes(search) ||
                (c.customerId || '').toLowerCase().includes(search)
            );
        }
        
        // Status filter
        const status = filterStatus?.value || 'all';
        if (status === 'active') {
            filtered = filtered.filter(c => c.hasActiveJwt && !c.isJwtExpired);
        } else if (status === 'expired') {
            filtered = filtered.filter(c => c.hasActiveJwt && c.isJwtExpired);
        } else if (status === 'none') {
            filtered = filtered.filter(c => !c.hasActiveJwt);
        }
        
        // IPs filter
        const ipsFilter = filterIps?.value || 'all';
        if (ipsFilter === 'multiple') {
            filtered = filtered.filter(c => c.hasMultipleIps);
        } else if (ipsFilter === 'flagged') {
            filtered = filtered.filter(c => c.flaggedCount > 0);
        }
        
        return filtered;
    }

    function renderCustomers() {
        if (!tableBody) return;

        const filtered = getFilteredCustomers();

        if (!filtered.length) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="fas fa-search me-2"></i>
                        No se encontraron clientes con los filtros actuales
                    </td>
                </tr>
            `;
            return;
        }

        const rows = filtered.map((customer) => {
            // Customer info
            const name = escapeHtml(customer.name || 'Sin nombre');
            const email = customer.email ? escapeHtml(customer.email) : '<span class="text-muted">Sin email</span>';
            const device = customer.primaryDevice ? `<small class="text-muted d-block">${escapeHtml(customer.primaryDevice)}</small>` : '';

            // JWT status
            let jwtHtml = '';
            if (customer.hasActiveJwt) {
                const now = new Date();
                const expiresAt = customer.tokenJwtExpiresAt ? new Date(customer.tokenJwtExpiresAt) : null;
                
                // Si no tiene fecha de expiración, es un token sin caducidad
                if (!expiresAt) {
                    jwtHtml = `
                        <span class="badge bg-success"><i class="fas fa-infinity me-1"></i>Sin caducidad</span>
                        <small class="text-muted d-block">No expira</small>
                    `;
                } else {
                    const isExpired = expiresAt < now;
                    
                    if (isExpired) {
                        jwtHtml = `
                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Expirado</span>
                            <small class="text-muted d-block">Expiró ${formatDate(customer.tokenJwtExpiresAt)}</small>
                        `;
                    } else {
                        const expiresIn = Math.floor((expiresAt - now) / 1000);
                        jwtHtml = `
                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Activo</span>
                            <small class="text-muted d-block">Expira en ${formatExpiresIn(expiresIn)}</small>
                        `;
                    }
                }
            } else {
                jwtHtml = '<span class="badge bg-secondary">Sin token</span>';
            }

            // IPs info
            let ipsHtml = '';
            const ipCount = customer.uniqueIpCount || 0;
            const hasWarning = customer.hasMultipleIps || customer.flaggedCount > 0;
            
            if (ipCount === 0) {
                ipsHtml = '<span class="text-muted">Sin registros</span>';
            } else {
                const warningIcon = hasWarning ? '<i class="fas fa-exclamation-triangle text-warning me-1"></i>' : '';
                const flaggedBadge = customer.flaggedCount > 0 
                    ? `<span class="badge bg-danger ms-1">${customer.flaggedCount} marcadas</span>` 
                    : '';
                
                // Show first IP
                const firstIp = customer.ipDetails && customer.ipDetails[0];
                const ipDisplay = firstIp 
                    ? `<code class="ip-badge">${escapeHtml(firstIp.ip)}</code>` 
                    : '';
                const locationDisplay = firstIp && firstIp.city 
                    ? `<small class="text-muted">${escapeHtml(firstIp.city)}, ${escapeHtml(firstIp.country)}</small>` 
                    : '';
                
                ipsHtml = `
                    ${warningIcon}
                    <span class="badge bg-info">${ipCount} IP${ipCount !== 1 ? 's' : ''}</span>
                    ${flaggedBadge}
                    ${ipDisplay ? `<div class="mt-1">${ipDisplay}</div>` : ''}
                    ${locationDisplay ? `<div>${locationDisplay}</div>` : ''}
                `;
            }

            // Last activity
            const lastActivity = customer.lastIpAccess || customer.lastSeen;
            const lastActivityHtml = lastActivity 
                ? `<span title="${formatDate(lastActivity)}">${relativeTime(lastActivity)}</span>`
                : '<span class="text-muted">Sin actividad</span>';

            // Actions
            const actionsHtml = `
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" title="Ver IPs" data-action="view-ips" data-customer-id="${escapeHtml(customer.customerId)}">
                        <i class="fas fa-network-wired"></i>
                    </button>
                    ${customer.hasActiveJwt ? `
                        <button class="btn btn-outline-info" title="Copiar token" data-action="copy-token" data-customer-id="${escapeHtml(customer.customerId)}">
                            <i class="fas fa-copy"></i>
                        </button>
                    ` : ''}
                    <button class="btn btn-outline-success" title="Crear nuevo token" data-action="create-token" data-customer-id="${escapeHtml(customer.customerId)}">
                        <i class="fas fa-key"></i>
                    </button>
                    ${customer.hasActiveJwt ? `
                        <button class="btn btn-outline-danger" title="Revocar token" data-action="revoke-token" data-customer-id="${escapeHtml(customer.customerId)}">
                            <i class="fas fa-ban"></i>
                        </button>
                    ` : ''}
                </div>
            `;

            const rowClass = hasWarning ? 'warning-row' : '';

            return `
                <tr class="${rowClass}">
                    <td>
                        <strong>${name}</strong>
                        <div class="small">${email}</div>
                        ${device}
                        <small class="text-muted d-block">${escapeHtml(customer.customerId)}</small>
                    </td>
                    <td>${jwtHtml}</td>
                    <td>${ipsHtml}</td>
                    <td>${lastActivityHtml}</td>
                    <td class="text-end">${actionsHtml}</td>
                </tr>
            `;
        });

        tableBody.innerHTML = rows.join('');
    }

    async function fetchData(showAlert = false) {
        try {
            const response = await fetch(endpoints.list, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Error al cargar datos');
            }

            const data = await response.json();
            
            if (data.success) {
                customers = data.customers || [];
                stats = data.stats || {};
                updateStats();
                renderCustomers();
                
                if (showAlert) {
                    pushAlert('success', 'Datos actualizados correctamente');
                }
            } else {
                throw new Error(data.error || 'Error desconocido');
            }
        } catch (error) {
            pushAlert('danger', 'Error al cargar datos: ' + error.message);
        }
    }

    async function createToken() {
        if (!currentCustomerId) return;
        
        const duration = tokenDuration?.value || 2592000;
        
        try {
            confirmCreateToken.disabled = true;
            confirmCreateToken.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creando...';
            
            const response = await fetch(endpoints.create, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    customerId: currentCustomerId,
                    expiresIn: parseInt(duration)
                })
            });

            const data = await response.json();
            
            if (data.success) {
                createTokenModal?.hide();
                const expiresMsg = data.data.expiresAt 
                    ? 'Expira: ' + data.data.expiresAt 
                    : 'Sin caducidad';
                pushAlert('success', 'Token JWT creado exitosamente. ' + expiresMsg);
                fetchData();
            } else {
                pushAlert('danger', data.error || 'Error al crear token');
            }
        } catch (error) {
            pushAlert('danger', 'Error al crear token: ' + error.message);
        } finally {
            confirmCreateToken.disabled = false;
            confirmCreateToken.innerHTML = '<i class="fas fa-key me-2"></i>Crear Token';
        }
    }

    async function revokeToken() {
        if (!currentCustomerId) return;
        
        try {
            confirmRevokeToken.disabled = true;
            confirmRevokeToken.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Revocando...';
            
            const response = await fetch(endpoints.revoke, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ customerId: currentCustomerId })
            });

            const data = await response.json();
            
            if (data.success) {
                revokeTokenModal?.hide();
                pushAlert('success', 'Token JWT revocado exitosamente');
                fetchData();
            } else {
                pushAlert('danger', data.error || 'Error al revocar token');
            }
        } catch (error) {
            pushAlert('danger', 'Error al revocar token: ' + error.message);
        } finally {
            confirmRevokeToken.disabled = false;
            confirmRevokeToken.innerHTML = '<i class="fas fa-ban me-2"></i>Revocar Token';
        }
    }

    async function loadCustomerIps(customerId) {
        if (!ipsTable) return;
        
        ipsTable.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    <i class="fas fa-circle-notch fa-spin me-2"></i>
                    Cargando IPs...
                </td>
            </tr>
        `;
        
        if (ipsAlertContainer) ipsAlertContainer.innerHTML = '';
        
        try {
            const url = endpoints.customerIps.replace('{customerId}', encodeURIComponent(customerId));
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const data = await response.json();
            
            if (data.success) {
                if (data.hasMultipleRecentIps) {
                    ipsAlertContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Advertencia:</strong> Este cliente ha accedido desde múltiples IPs en las últimas 24 horas.
                        </div>
                    `;
                }
                
                const ips = data.ips || [];
                
                if (ips.length === 0) {
                    ipsTable.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No hay registros de IPs para este cliente
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                const rows = ips.map(ip => {
                    const location = [ip.city, ip.region, ip.country].filter(Boolean).join(', ') || '-';
                    const flaggedBadge = ip.isFlagged 
                        ? `<span class="badge bg-danger"><i class="fas fa-flag me-1"></i>Marcada</span>` 
                        : `<span class="badge bg-success">OK</span>`;
                    
                    return `
                        <tr class="${ip.isFlagged ? 'table-warning' : ''}">
                            <td><code>${escapeHtml(ip.ipAddress)}</code></td>
                            <td>
                                <small>${escapeHtml(location)}</small>
                                ${ip.isp ? `<br><small class="text-muted">${escapeHtml(ip.isp)}</small>` : ''}
                            </td>
                            <td><small>${escapeHtml(ip.deviceName || '-')}</small></td>
                            <td><small>${formatDate(ip.firstSeenAt)}</small></td>
                            <td><small>${formatDate(ip.lastSeenAt)}</small></td>
                            <td>${ip.accessCount}</td>
                            <td>${flaggedBadge}</td>
                        </tr>
                    `;
                });
                
                ipsTable.innerHTML = rows.join('');
            } else {
                throw new Error(data.error || 'Error al cargar IPs');
            }
        } catch (error) {
            ipsTable.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error: ${escapeHtml(error.message)}
                    </td>
                </tr>
            `;
        }
    }

    // Event listeners
    if (tableBody) {
        tableBody.addEventListener('click', (e) => {
            const button = e.target.closest('button[data-action]');
            if (!button) return;

            const action = button.getAttribute('data-action');
            const customerId = button.getAttribute('data-customer-id');
            const customer = customers.find(c => c.customerId === customerId);

            if (!customer) {
                pushAlert('danger', 'Cliente no encontrado');
                return;
            }

            currentCustomerId = customerId;

            switch (action) {
                case 'view-ips':
                    if (viewIpsCustomerName) viewIpsCustomerName.textContent = customer.name || 'Sin nombre';
                    if (viewIpsCustomerIdEl) viewIpsCustomerIdEl.textContent = customerId;
                    viewIpsModal?.show();
                    loadCustomerIps(customerId);
                    break;

                case 'copy-token':
                    if (customer.tokenJwt) {
                        copyToClipboard(customer.tokenJwt, 'Token JWT copiado al portapapeles');
                    } else {
                        pushAlert('warning', 'Este cliente no tiene un token JWT activo');
                    }
                    break;

                case 'create-token':
                    if (createTokenCustomerName) createTokenCustomerName.textContent = customer.name || 'Sin nombre';
                    if (createTokenCustomerId) createTokenCustomerId.textContent = customerId;
                    createTokenModal?.show();
                    break;

                case 'revoke-token':
                    if (revokeTokenCustomerName) revokeTokenCustomerName.textContent = customer.name || 'Sin nombre';
                    if (revokeTokenCustomerId) revokeTokenCustomerId.textContent = customerId;
                    revokeTokenModal?.show();
                    break;
            }
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', () => fetchData(true));
    }

    if (confirmCreateToken) {
        confirmCreateToken.addEventListener('click', createToken);
    }

    if (confirmRevokeToken) {
        confirmRevokeToken.addEventListener('click', revokeToken);
    }

    // Filter listeners
    if (searchInput) {
        searchInput.addEventListener('input', renderCustomers);
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', renderCustomers);
    }

    if (filterIps) {
        filterIps.addEventListener('change', renderCustomers);
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (filterStatus) filterStatus.value = 'all';
            if (filterIps) filterIps.value = 'all';
            renderCustomers();
        });
    }

    // Initial load
    fetchData();
})();
</script>
<?php
$customScripts = ob_get_clean();
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
