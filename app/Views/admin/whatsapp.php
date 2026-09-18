<?php
$title = 'WhatsApp Business';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-end align-items-center mb-3 gap-2">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="refreshConfigs">
                        <i class="fas fa-rotate"></i> Actualizar
                    </button>
                    <button type="button" class="btn btn-primary" id="addConfigBtn">
                        <i class="fab fa-whatsapp me-1"></i> Agregar número
                    </button>
                </div>
            </div>

            <div id="alertsContainer"></div>

            <section class="admin-form-panel mb-3 d-none" id="configFormPanel">
                <form id="configForm">
                    <div class="admin-form-panel-title">
                        <div>
                            <span>WhatsApp</span>
                            <h2>Nueva configuracion</h2>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="closeConfigFormBtn">Cerrar</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="configCustomerId" class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select" id="configCustomerId" data-search-select data-label="Cliente" data-page-size="10" required>
                                <option value="">Selecciona un cliente</option>
                                <?php foreach ($customers ?? [] as $customer): ?>
                                <option value="<?= htmlspecialchars($customer['customerId'] ?? '') ?>">
                                    <?= htmlspecialchars($customer['name'] ?? $customer['customerId'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">El cliente al que se asociara este numero de WhatsApp.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="configPhoneNumber" class="form-label">Numero de telefono <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="configPhoneNumber" placeholder="+52 1234567890" required>
                        </div>
                        <div class="col-md-6">
                            <label for="configPhoneNumberId" class="form-label">Phone Number ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="configPhoneNumberId" placeholder="123456789012345" required>
                        </div>
                        <div class="col-md-6">
                            <label for="configAccessToken" class="form-label">Access Token</label>
                            <input type="text" class="form-control" id="configAccessToken" placeholder="EAABs...">
                        </div>
                        <div class="col-md-6">
                            <label for="configBusinessName" class="form-label">Nombre del negocio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="configBusinessName" placeholder="Mi Gimnasio" required>
                        </div>
                        <div class="col-md-6">
                            <label for="configEmail" class="form-label">Email del negocio</label>
                            <input type="email" class="form-control" id="configEmail" placeholder="contacto@gimnasio.com">
                        </div>
                        <div class="col-md-6">
                            <label for="configAddress" class="form-label">Direccion</label>
                            <input type="text" class="form-control" id="configAddress" placeholder="Calle 123, Ciudad">
                        </div>
                        <div class="col-md-6">
                            <label for="configDescription" class="form-label">Descripcion</label>
                            <input type="text" class="form-control" id="configDescription" placeholder="Gimnasio y centro fitness">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="configRegisterInWhatsApp">
                                <label class="form-check-label" for="configRegisterInWhatsApp">Registrar numero en WhatsApp</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-outline-primary" id="cancelConfigFormBtn">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Guardar configuracion
                        </button>
                    </div>
                </form>
            </section>

            <section class="admin-form-panel mb-3 d-none" id="templateFormPanel">
                <form id="templateForm">
                    <div class="admin-form-panel-title">
                        <div>
                            <span>WhatsApp</span>
                            <h2>Nuevo template personalizado</h2>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="closeTemplateFormBtn">Cerrar</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="templateCustomerId" class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select" id="templateCustomerId" data-search-select data-label="Cliente" data-page-size="10" required>
                                <option value="">Selecciona un cliente</option>
                                <?php foreach ($customers ?? [] as $customer): ?>
                                <option value="<?= htmlspecialchars($customer['customerId'] ?? '') ?>">
                                    <?= htmlspecialchars($customer['name'] ?? $customer['customerId'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="templateEventKey" class="form-label">Evento <span class="text-danger">*</span></label>
                            <select class="form-select" id="templateEventKey" data-search-select data-label="Evento" data-page-size="10" required></select>
                        </div>
                        <div class="col-md-8">
                            <label for="templateName" class="form-label">Nombre del template en WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="templateName" placeholder="membership_custom" required>
                        </div>
                        <div class="col-md-4">
                            <label for="templateLanguageCode" class="form-label">Idioma</label>
                            <input type="text" class="form-control" id="templateLanguageCode" value="es_MX">
                        </div>
                        <div class="col-12">
                            <label for="templateDescription" class="form-label">Descripcion</label>
                            <input type="text" class="form-control" id="templateDescription" placeholder="Uso interno">
                        </div>
                        <div class="col-md-6">
                            <label for="templateHeaderVariables" class="form-label">Variables header</label>
                            <select class="form-select" id="templateHeaderVariables" multiple></select>
                        </div>
                        <div class="col-md-6">
                            <label for="templateBodyVariables" class="form-label">Variables body</label>
                            <select class="form-select" id="templateBodyVariables" multiple></select>
                        </div>
                    </div>
                    <div class="form-text mt-3">El orden seleccionado debe coincidir con el orden de variables aprobado en Meta.</div>
                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-outline-primary" id="cancelTemplateFormBtn">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Guardar template
                        </button>
                    </div>
                </form>
            </section>

            <section id="whatsappListPanel">

            <div id="whatsappConfigsFilter"></div>
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
            <div id="whatsappConfigsPagination"></div>

            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Nota:</strong> El Phone Number ID se obtiene desde el Meta Business Manager cuando agregas un número de WhatsApp Business.
            </div>
        </div>
    </div>
</div>

<!-- Templates personalizados -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-message me-2"></i>Templates personalizados por evento</h5>
        <button type="button" class="btn btn-outline-primary" id="addTemplateBtn">
            <i class="fas fa-plus me-1"></i> Agregar template
        </button>
    </div>
    <div id="whatsappTemplatesFilter"></div>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="templatesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Evento</th>
                            <th>Template WhatsApp</th>
                            <th>Idioma</th>
                            <th>Variables</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Cargando templates...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div id="whatsappTemplatesPagination"></div>
</div>

</section>

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
    'templates' => app_url('/admin/api/whatsapp/templates'),
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
    const templatesUrl = endpoints.templates;
    console.log('Base URL:', baseUrl);
    
    let configs = [];
    let templates = [];
    let templateVariables = [];
    let templateEvents = [];
    let deleteId = null;
    let configsPage = 1;
    let templatesPage = 1;
    const listPageSize = 10;
    let configFilters = { search: '', status: 'all' };
    let templateFilters = { search: '', event: 'all' };

    const tableBody = document.querySelector('#configsTable tbody');
    const templatesTableBody = document.querySelector('#templatesTable tbody');
    const refreshButton = document.getElementById('refreshConfigs');
    const alertsContainer = document.getElementById('alertsContainer');
    const whatsappListPanel = document.getElementById('whatsappListPanel');
    const configFormPanel = document.getElementById('configFormPanel');
    const templateFormPanel = document.getElementById('templateFormPanel');
    const configForm = document.getElementById('configForm');
    const templateForm = document.getElementById('templateForm');
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
        if (window.AdminToast && typeof window.AdminToast.show === 'function') {
            window.AdminToast.show(message, type, { duration: 5000 });
            return;
        }

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

    function showListPanel() {
        configFormPanel.classList.add('d-none');
        templateFormPanel.classList.add('d-none');
        whatsappListPanel.classList.remove('d-none');
    }

    function showConfigForm() {
        configForm.reset();
        templateFormPanel.classList.add('d-none');
        whatsappListPanel.classList.add('d-none');
        configFormPanel.classList.remove('d-none');
        window.AdminUI?.initSearchSelects(configFormPanel);
        document.getElementById('configCustomerId').dispatchEvent(new Event('change', { bubbles: true }));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showTemplateForm() {
        templateForm.reset();
        configFormPanel.classList.add('d-none');
        whatsappListPanel.classList.add('d-none');
        templateFormPanel.classList.remove('d-none');
        window.AdminUI?.initSearchSelects(templateFormPanel);
        document.getElementById('templateCustomerId').dispatchEvent(new Event('change', { bubbles: true }));
        document.getElementById('templateEventKey').dispatchEvent(new Event('change', { bubbles: true }));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function renderTable() {
        const search = String(configFilters.search || '').toLowerCase().trim();
        const filtered = (configs || []).filter(config => {
            const matchesSearch = !search || [config.CustomerName, config.CustomerId, config.PhoneNumber, config.PhoneNumberId, config.BusinessName]
                .some(value => String(value || '').toLowerCase().includes(search));
            const isActive = ![false, 0, '0', null].includes(config.IsActive);
            const matchesStatus = configFilters.status === 'all' || !configFilters.status
                || (configFilters.status === 'active' && isActive)
                || (configFilters.status === 'inactive' && !isActive);
            return matchesSearch && matchesStatus;
        });
        const page = window.AdminPagination
            ? window.AdminPagination.range(filtered, configsPage, listPageSize)
            : { items: filtered, page: 1 };
        configsPage = page.page;

        if (filtered.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fab fa-whatsapp fa-2x mb-3 d-block" style="color: #25D366;"></i>
                        No hay configuraciones de WhatsApp registradas.<br>
                        <small>Haz clic en "Agregar número" para comenzar.</small>
                    </td>
                </tr>
            `;
            window.AdminPagination?.render({ container: '#whatsappConfigsPagination', page: 1, pageSize: listPageSize, totalItems: 0 });
            return;
        }

        tableBody.innerHTML = page.items.map(config => `
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
        window.AdminPagination?.render({
            container: '#whatsappConfigsPagination', page: configsPage, pageSize: listPageSize,
            totalItems: filtered.length, label: 'Paginacion de configuraciones WhatsApp',
            onChange: pageNumber => { configsPage = pageNumber; renderTable(); }
        });
    }

    function selectedValues(select) {
        return Array.from(select.selectedOptions).map(option => option.value);
    }

    function populateTemplateSelectors() {
        const eventSelect = document.getElementById('templateEventKey');
        const headerSelect = document.getElementById('templateHeaderVariables');
        const bodySelect = document.getElementById('templateBodyVariables');

        if (eventSelect) {
            eventSelect.innerHTML = '<option value="">Selecciona un evento...</option>' + templateEvents.map(event =>
                `<option value="${escapeHtml(event.value)}">${escapeHtml(event.label)}</option>`
            ).join('');
            eventSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const variableOptions = templateVariables.map(variable =>
            `<option value="${escapeHtml(variable.SourceKey)}">${escapeHtml(variable.Name)} (${escapeHtml(variable.SourceKey)})</option>`
        ).join('');

        if (headerSelect) headerSelect.innerHTML = variableOptions;
        if (bodySelect) bodySelect.innerHTML = variableOptions;
    }

    function findCustomerName(customerId) {
        const match = configs.find(config => config.CustomerId === customerId);
        return match ? (match.CustomerName || customerId) : customerId;
    }

    function eventLabel(eventKey) {
        const match = templateEvents.find(event => event.value === eventKey);
        return match ? match.label : (eventKey || '-');
    }

    function templateVariableSummary(template) {
        let components = [];
        try {
            components = template.ComponentsJson ? JSON.parse(template.ComponentsJson) : [];
        } catch (error) {
            components = [];
        }

        return components.map(component => {
            const variables = Array.isArray(component.variables) ? component.variables.join(', ') : '';
            return `${component.type || 'body'}: ${variables}`;
        }).filter(Boolean).join('<br>') || '-';
    }

    function renderTemplatesTable() {
        if (!templatesTableBody) return;

        const search = String(templateFilters.search || '').toLowerCase().trim();
        const filtered = (templates || []).filter(template => {
            const matchesSearch = !search || [findCustomerName(template.CustomerId), template.CustomerId, template.TemplateName, template.LanguageCode]
                .some(value => String(value || '').toLowerCase().includes(search));
            const matchesEvent = templateFilters.event === 'all' || !templateFilters.event || template.EventKey === templateFilters.event;
            return matchesSearch && matchesEvent;
        });
        const page = window.AdminPagination
            ? window.AdminPagination.range(filtered, templatesPage, listPageSize)
            : { items: filtered, page: 1 };
        templatesPage = page.page;

        if (filtered.length === 0) {
            templatesTableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No hay templates personalizados.</td></tr>';
            window.AdminPagination?.render({ container: '#whatsappTemplatesPagination', page: 1, pageSize: listPageSize, totalItems: 0 });
            return;
        }

        templatesTableBody.innerHTML = page.items.map(template => `
            <tr>
                <td>
                    <strong>${escapeHtml(findCustomerName(template.CustomerId))}</strong>
                    <br><small class="text-muted">${escapeHtml(template.CustomerId)}</small>
                </td>
                <td>${escapeHtml(eventLabel(template.EventKey))}</td>
                <td><code>${escapeHtml(template.TemplateName)}</code></td>
                <td>${escapeHtml(template.LanguageCode || 'es_MX')}</td>
                <td><small>${templateVariableSummary(template)}</small></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-danger" title="Eliminar template" onclick="deleteTemplate('${escapeHtml(template.Id)}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `).join('');
        window.AdminPagination?.render({
            container: '#whatsappTemplatesPagination', page: templatesPage, pageSize: listPageSize,
            totalItems: filtered.length, label: 'Paginacion de templates WhatsApp',
            onChange: pageNumber => { templatesPage = pageNumber; renderTemplatesTable(); }
        });
    }

    async function loadTemplates() {
        if (!templatesTableBody) return;

        try {
            const response = await fetch(templatesUrl, { method: 'GET', headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (data.success) {
                templates = data.templates || [];
                templateVariables = data.variables || [];
                templateEvents = data.events || [];
                populateTemplateSelectors();
                renderTemplatesTable();
            } else {
                showAlert(data.error || 'Error al cargar templates', 'danger');
            }
        } catch (error) {
            showAlert('Error de conexión al cargar templates: ' + error.message, 'danger');
        }
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
                renderTemplatesTable();
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
                    showListPanel();
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

    if (templateForm) {
        templateForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const headerVariables = selectedValues(document.getElementById('templateHeaderVariables'));
            const bodyVariables = selectedValues(document.getElementById('templateBodyVariables'));
            const components = [];

            if (headerVariables.length > 0) components.push({ type: 'header', variables: headerVariables });
            if (bodyVariables.length > 0) components.push({ type: 'body', variables: bodyVariables });

            const payload = {
                customerId: document.getElementById('templateCustomerId').value,
                eventKey: document.getElementById('templateEventKey').value,
                templateName: document.getElementById('templateName').value,
                languageCode: document.getElementById('templateLanguageCode').value || 'es_MX',
                description: document.getElementById('templateDescription').value,
                components: components
            };

            try {
                const response = await fetch(templatesUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();

                if (data.success) {
                    showListPanel();
                    templateForm.reset();
                    document.getElementById('templateLanguageCode').value = 'es_MX';
                    showAlert('Template personalizado guardado correctamente', 'success');
                    loadTemplates();
                } else {
                    showAlert(data.error || 'Error al guardar template', 'danger');
                }
            } catch (error) {
                showAlert('Error de conexión', 'danger');
            }
        });
    }

    window.deleteTemplate = async function(id) {
        try {
            const response = await fetch(`${templatesUrl}/${id}/delete`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
            });
            const data = await response.json();
            if (data.success) {
                showAlert('Template eliminado correctamente', 'success');
                loadTemplates();
            } else {
                showAlert(data.error || 'Error al eliminar template', 'danger');
            }
        } catch (error) {
            showAlert('Error de conexión', 'danger');
        }
    };

    // Event listeners
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            loadConfigs();
            loadTemplates();
        });
    }

    document.getElementById('addConfigBtn')?.addEventListener('click', showConfigForm);
    document.getElementById('addTemplateBtn')?.addEventListener('click', showTemplateForm);
    document.getElementById('closeConfigFormBtn')?.addEventListener('click', showListPanel);
    document.getElementById('cancelConfigFormBtn')?.addEventListener('click', showListPanel);
    document.getElementById('closeTemplateFormBtn')?.addEventListener('click', showListPanel);
    document.getElementById('cancelTemplateFormBtn')?.addEventListener('click', showListPanel);

    if (window.AdminFilters) {
        window.AdminFilters.mount({
            container: '#whatsappConfigsFilter',
            id: 'whatsapp-configs-filter-drawer',
            title: 'Filtrar configuraciones',
            defaults: { search: '', status: 'all' },
            fields: [
                { name: 'search', label: 'Buscar', type: 'search', placeholder: 'Cliente, numero o negocio' },
                { name: 'status', label: 'Estado', type: 'select', hideChipValues: ['all'], options: [
                    { value: 'all', label: 'Todos' }, { value: 'active', label: 'Activos' }, { value: 'inactive', label: 'Inactivos' }
                ] }
            ],
            onApply: function(values) {
                configFilters = values;
                configsPage = 1;
                renderTable();
            }
        });
        window.AdminFilters.mount({
            container: '#whatsappTemplatesFilter',
            id: 'whatsapp-templates-filter-drawer',
            title: 'Filtrar templates',
            defaults: { search: '' },
            fields: [
                { name: 'search', label: 'Buscar', type: 'search', placeholder: 'Cliente, template o idioma' }
            ],
            onApply: function(values) {
                templateFilters.search = values.search || '';
                templatesPage = 1;
                renderTemplatesTable();
            }
        });
    }

    // Cargar al iniciar
    loadConfigs();
    loadTemplates();
});
</script>
<?php
$customScripts = ob_get_clean();
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
