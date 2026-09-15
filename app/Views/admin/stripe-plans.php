<?php
$title = 'Planes Stripe';

ob_start();
?>

<div class="container mt-4">
    <div class="d-flex flex-wrap justify-content-end align-items-center mb-3 gap-2">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" id="refreshBtn">
                <i class="fas fa-rotate me-1"></i>Actualizar
            </button>
            <button type="button" class="btn btn-primary" id="newPlanBtn">
                <i class="fas fa-plus me-1"></i>Nuevo precio
            </button>
        </div>
    </div>

    <div id="alertsContainer"></div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Catalogo de reglas</h5>
                    <p class="text-muted mb-0 small">Reglas disponibles solo para la app seleccionada.</p>
                </div>
                <button type="button" class="btn btn-outline-primary" id="newRuleBtn">
                    <i class="fas fa-sliders me-1"></i>Nueva regla
                </button>
            </div>
            <div id="rulesCatalogContainer" class="rule-catalog-list">
                <span class="text-muted">Cargando reglas...</span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Plan</th>
                            <th>Periodo</th>
                            <th>Monto</th>
                            <th>Reglas</th>
                            <th>Billing IDs</th>
                            <th>Stripe</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="plansBody">
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-circle-notch fa-spin me-2"></i>Cargando planes...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ruleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="ruleForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="ruleModalTitle">Nueva regla</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ruleId">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Clave</label>
                            <input type="text" class="form-control font-monospace" id="catalogRuleKey" placeholder="max_messages" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" class="form-control" id="catalogRuleName" placeholder="Mensajes WhatsApp" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Tipo</label>
                            <select class="form-select" id="catalogRuleType">
                                <option value="integer">Numero</option>
                                <option value="boolean">Si/No</option>
                                <option value="decimal">Decimal</option>
                                <option value="string">Texto</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Descripcion</label>
                            <textarea class="form-control" id="catalogRuleDescription" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar regla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="planModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="planForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="planModalTitle">Nuevo precio</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Lookup key</label>
                            <input type="text" class="form-control font-monospace" id="lookupKey" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" class="form-control" id="planName" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Periodo</label>
                            <select class="form-select" id="planType">
                                <option value="monthly">Mensual</option>
                                <option value="yearly">Anual</option>
                                <option value="permanent">Permanente</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Activo</label>
                            <select class="form-select" id="isActive">
                                <option value="1">Si</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Monto en centavos</label>
                            <input type="number" min="0" step="1" class="form-control" id="unitAmount" placeholder="99000">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Moneda</label>
                            <input type="text" maxlength="3" class="form-control text-lowercase" id="currency" value="mxn">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stripe product ID</label>
                            <input type="text" class="form-control font-monospace" id="stripeProductId" placeholder="Vacio = config product_id">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Orden</label>
                            <input type="number" step="1" class="form-control" id="sortOrder" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Billing IDs exclusivos</label>
                            <textarea class="form-control font-monospace" id="billingIds" rows="2" placeholder="cus_xxx, cus_yyy"></textarea>
                            <div class="form-text">Si agregas IDs, el precio queda oculto para los demas clientes.</div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Reglas</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addRuleBtn">
                            <i class="fas fa-plus me-1"></i>Agregar regla
                        </button>
                    </div>
                    <div id="rulesContainer" class="vstack gap-2 rules-scroll"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const endpoints = <?= json_encode([
    'list' => app_url('/admin/api/stripe-plans'),
    'save' => app_url('/admin/api/stripe-plans'),
    'saveRule' => app_url('/admin/api/stripe-plan-rules'),
    'unlinkRule' => app_url('/admin/api/stripe-plan-rules/{ruleId}'),
    'verify' => app_url('/admin/api/stripe-plans/{lookupKey}/verify'),
    'createPrice' => app_url('/admin/api/stripe-plans/{lookupKey}/create-stripe-price'),
], JSON_UNESCAPED_SLASHES) ?>;

let plans = [];
let rulesCatalog = [];
let modal;
let ruleModal;
let stripeDashboardBase = 'https://dashboard.stripe.com/test/prices/';

document.addEventListener('DOMContentLoaded', () => {
    modal = new bootstrap.Modal(document.getElementById('planModal'));
    ruleModal = new bootstrap.Modal(document.getElementById('ruleModal'));
    document.getElementById('refreshBtn').addEventListener('click', loadPlans);
    document.getElementById('newPlanBtn').addEventListener('click', () => openPlanModal());
    document.getElementById('newRuleBtn').addEventListener('click', () => openRuleModal());
    document.getElementById('addRuleBtn').addEventListener('click', () => addRuleRow('', ''));
    document.getElementById('planForm').addEventListener('submit', savePlan);
    document.getElementById('ruleForm').addEventListener('submit', saveRule);
    loadPlans();
});

async function loadPlans() {
    const tbody = document.getElementById('plansBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-circle-notch fa-spin me-2"></i>Cargando planes...</td></tr>';

    try {
        const res = await fetch(endpoints.list);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'No se pudieron cargar los planes');

        plans = data.plans || [];
        rulesCatalog = data.rules_catalog || [];
        stripeDashboardBase = data.stripe_dashboard_base || stripeDashboardBase;
        ensureRulesDatalist();

        if (!data.tables_ready) {
            showAlert('Ejecuta las migraciones 009_create_stripe_plan_catalog.sql y 010_add_stripe_plan_price_fields.sql antes de administrar planes.', 'warning');
        } else if (data.source === 'config') {
            showAlert('Mostrando planes desde config/stripe.php porque la tabla aun no tiene registros. Al guardar un plan quedara en base de datos.', 'info');
        } else if (!data.stripe_checked) {
            showAlert('Los planes cargaron, pero no se pudo consultar Stripe para hacer el match automatico.', 'warning');
        }

        renderRuleCatalog();
        renderPlans();
    } catch (e) {
        showAlert(e.message, 'danger');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">No se pudieron cargar los planes.</td></tr>';
    }
}

function renderRuleCatalog() {
    const container = document.getElementById('rulesCatalogContainer');
    if (!rulesCatalog.length) {
        container.innerHTML = '<span class="text-muted">No hay reglas en el catalogo de esta app.</span>';
        return;
    }

    container.innerHTML = rulesCatalog.map(rule => `
        <div class="rule-catalog-pill">
            <span>
                <strong>${esc(rule.Name || rule.RuleKey)}</strong>
                <small>${esc(rule.RuleKey)}</small>
            </span>
            <span class="rule-catalog-actions">
                <em>${ruleTypeLabel(rule.ValueType)}</em>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openRuleModal(${Number(rule.Id) || 0})" title="Editar regla">
                    <i class="fas fa-pen"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="unlinkRule(${Number(rule.Id) || 0}, '${js(rule.RuleKey)}')" title="Desvincular de esta app">
                    <i class="fas fa-link-slash"></i>
                </button>
            </span>
        </div>
    `).join('');
}

function renderPlans() {
    const tbody = document.getElementById('plansBody');
    if (!plans.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">No hay planes registrados.</td></tr>';
        return;
    }

    tbody.innerHTML = plans.map(plan => `
        <tr>
            <td>
                <div class="fw-semibold">${esc(plan.name || '')}</div>
                <div class="font-monospace small text-muted">${esc(plan.lookup_key || '')}</div>
            </td>
            <td>${periodBadge(plan.type)}</td>
            <td>${displayMoney(plan)}</td>
            <td><span class="badge bg-light text-dark">${Object.keys(plan.rules || {}).length} reglas</span></td>
            <td>${(plan.showBillingIds || []).length ? `<span class="badge bg-warning text-dark">${plan.showBillingIds.length} exclusivos</span>` : '<span class="text-muted">Publico</span>'}</td>
            <td>
                <div class="font-monospace small">${esc(plan.stripe_price_id || plan.stripe_price?.id || 'Sin price_id')}</div>
                <div class="small" id="stripeStatus-${cssId(plan.lookup_key)}">${stripeStatus(plan)}</div>
            </td>
            <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="openPlanModal('${js(plan.lookup_key)}')" title="Editar">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn btn-outline-secondary" onclick="verifyPlan('${js(plan.lookup_key)}')" title="Verificar en Stripe">
                        <i class="fas fa-magnifying-glass"></i>
                    </button>
                    <button class="btn btn-outline-success" onclick="createStripePrice('${js(plan.lookup_key)}')" title="Crear precio en Stripe" ${plan.stripe_exists ? 'disabled' : ''}>
                        <i class="fas fa-cloud-arrow-up"></i>
                    </button>
                    ${stripeEditButton(plan)}
                </div>
            </td>
        </tr>
    `).join('');
}

function openPlanModal(lookupKey = null) {
    const plan = lookupKey ? plans.find(p => p.lookup_key === lookupKey) : null;
    document.getElementById('planModalTitle').textContent = plan ? 'Editar precio' : 'Nuevo precio';
    document.getElementById('lookupKey').value = plan?.lookup_key || '';
    document.getElementById('lookupKey').readOnly = Boolean(plan);
    document.getElementById('planName').value = plan?.name || '';
    document.getElementById('planType').value = plan?.type || 'monthly';
    document.getElementById('isActive').value = plan?.is_active === false ? '0' : '1';
    document.getElementById('unitAmount').value = plan?.unit_amount ?? '';
    document.getElementById('currency').value = plan?.currency || 'mxn';
    document.getElementById('stripeProductId').value = plan?.stripe_product_id || '';
    document.getElementById('sortOrder').value = plan?.sort_order ?? 0;
    document.getElementById('billingIds').value = (plan?.showBillingIds || []).join('\n');

    const container = document.getElementById('rulesContainer');
    container.innerHTML = '';
    const rules = plan?.rules || defaultRules();
    Object.entries(rules).forEach(([key, value]) => addRuleRow(key, value));
    if (!Object.keys(rules).length) addRuleRow('', '');

    modal.show();
}

function openRuleModal(ruleId = null) {
    const rule = ruleId ? rulesCatalog.find(item => Number(item.Id) === Number(ruleId)) : null;
    document.getElementById('ruleModalTitle').textContent = rule ? 'Editar regla' : 'Nueva regla';
    document.getElementById('ruleId').value = rule?.Id || '';
    document.getElementById('catalogRuleKey').value = rule?.RuleKey || '';
    document.getElementById('catalogRuleName').value = rule?.Name || '';
    document.getElementById('catalogRuleType').value = rule?.ValueType || 'integer';
    document.getElementById('catalogRuleDescription').value = rule?.Description || '';
    ruleModal.show();
}

function defaultRules() {
    const defaults = {};
    rulesCatalog.forEach(rule => {
        defaults[rule.RuleKey] = rule.ValueType === 'boolean' ? true : '';
    });
    return defaults;
}

function addRuleRow(key, value) {
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center';
    row.innerHTML = `
        <div class="col-md-5">
            <input list="rulesList" class="form-control rule-key" placeholder="max_messages" value="${esc(key)}">
        </div>
        <div class="col-md-5">
            <input class="form-control rule-value" placeholder="0, 100, true, __null__" value="${value === null ? '__null__' : esc(String(value))}">
        </div>
        <div class="col-md-2 text-end">
            <button type="button" class="btn btn-outline-danger" title="Eliminar regla">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    row.querySelector('button').addEventListener('click', () => row.remove());
    document.getElementById('rulesContainer').appendChild(row);
    ensureRulesDatalist();
}

function ensureRulesDatalist() {
    let datalist = document.getElementById('rulesList');
    if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = 'rulesList';
        document.body.appendChild(datalist);
    }

    datalist.innerHTML = rulesCatalog.map(rule => `<option value="${esc(rule.RuleKey)}">${esc(rule.Name || rule.RuleKey)}</option>`).join('');
}

async function saveRule(event) {
    event.preventDefault();
    const payload = {
        id: document.getElementById('ruleId').value,
        rule_key: document.getElementById('catalogRuleKey').value.trim(),
        name: document.getElementById('catalogRuleName').value.trim(),
        value_type: document.getElementById('catalogRuleType').value,
        description: document.getElementById('catalogRuleDescription').value.trim()
    };

    try {
        const res = await fetch(endpoints.saveRule, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'No se pudo guardar la regla');
        ruleModal.hide();
        showAlert('Regla guardada para esta app.', 'success');
        await loadPlans();
    } catch (e) {
        showAlert(e.message, 'danger');
    }
}

async function unlinkRule(ruleId, ruleKey) {
    if (!ruleId) return;
    if (!confirm(`Se quitara la regla "${ruleKey}" del catalogo de esta app y de sus planes. Continuar?`)) return;

    try {
        const res = await fetch(endpoints.unlinkRule.replace('{ruleId}', encodeURIComponent(ruleId)), { method: 'DELETE' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'No se pudo desvincular la regla');
        showAlert('Regla desvinculada de esta app.', 'success');
        await loadPlans();
    } catch (e) {
        showAlert(e.message, 'danger');
    }
}

async function savePlan(event) {
    event.preventDefault();
    const rules = {};
    document.querySelectorAll('#rulesContainer .row').forEach(row => {
        const key = row.querySelector('.rule-key').value.trim();
        const value = row.querySelector('.rule-value').value.trim();
        if (key) rules[key] = value;
    });

    const payload = {
        lookup_key: document.getElementById('lookupKey').value.trim(),
        name: document.getElementById('planName').value.trim(),
        type: document.getElementById('planType').value,
        is_active: document.getElementById('isActive').value === '1',
        unit_amount: document.getElementById('unitAmount').value,
        currency: document.getElementById('currency').value.trim().toLowerCase(),
        stripe_product_id: document.getElementById('stripeProductId').value.trim(),
        sort_order: document.getElementById('sortOrder').value,
        showBillingIds: document.getElementById('billingIds').value,
        rules
    };

    try {
        const res = await fetch(endpoints.save, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'No se pudo guardar');
        modal.hide();
        showAlert('Plan guardado correctamente.', 'success');
        await loadPlans();
    } catch (e) {
        showAlert(e.message, 'danger');
    }
}

async function verifyPlan(lookupKey) {
    const status = statusEl(lookupKey);
    status.innerHTML = '<span class="text-muted">Verificando...</span>';

    try {
        const res = await fetch(endpoints.verify.replace('{lookupKey}', encodeURIComponent(lookupKey)));
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Error al verificar');
        status.innerHTML = data.exists
            ? `<span class="text-success">Existe: ${esc(data.price.id)}</span>`
            : '<span class="text-danger">No existe en Stripe</span>';
    } catch (e) {
        status.innerHTML = `<span class="text-danger">${esc(e.message)}</span>`;
    }
}

async function createStripePrice(lookupKey) {
    if (!confirm('Se creara un Price real en Stripe para este lookup_key.')) return;
    const status = statusEl(lookupKey);
    status.innerHTML = '<span class="text-muted">Creando...</span>';

    try {
        const res = await fetch(endpoints.createPrice.replace('{lookupKey}', encodeURIComponent(lookupKey)), { method: 'POST' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'No se pudo crear el precio');
        status.innerHTML = data.created
            ? `<span class="text-success">Creado: ${esc(data.price.id)}</span>`
            : `<span class="text-info">Ya existia: ${esc(data.price.id)}</span>`;
        await loadPlans();
    } catch (e) {
        status.innerHTML = `<span class="text-danger">${esc(e.message)}</span>`;
    }
}

function statusEl(lookupKey) {
    return document.getElementById(`stripeStatus-${cssId(lookupKey)}`);
}

function ruleTypeLabel(type) {
    const labels = { boolean: 'Si/No', integer: 'Numero', decimal: 'Decimal', string: 'Texto', json: 'JSON' };
    return labels[type] || type || 'Regla';
}

function periodBadge(type) {
    const labels = { monthly: 'Mensual', yearly: 'Anual', permanent: 'Permanente' };
    const colors = { monthly: 'primary', yearly: 'success', permanent: 'dark' };
    return `<span class="badge bg-${colors[type] || 'secondary'}">${labels[type] || type}</span>`;
}

function stripeStatus(plan) {
    if (plan.stripe_exists === true) {
        return '<span class="text-success">Existe en Stripe</span>';
    }

    if (plan.stripe_exists === false) {
        return '<span class="text-danger">No existe en Stripe</span>';
    }

    return '<span class="text-muted">No verificado</span>';
}

function money(amount, currency) {
    if (amount === null || amount === undefined || amount === '') return '<span class="text-muted">Sin monto</span>';
    return `${(Number(amount) / 100).toLocaleString('es-MX', { style: 'currency', currency: (currency || 'mxn').toUpperCase() })}`;
}

function displayMoney(plan) {
    if (plan.stripe_price && plan.stripe_price.unit_amount !== null && plan.stripe_price.unit_amount !== undefined) {
        return `${money(plan.stripe_price.unit_amount, plan.stripe_price.currency)} <div class="small text-success">Stripe</div>`;
    }

    if (plan.unit_amount !== null && plan.unit_amount !== undefined && plan.unit_amount !== '') {
        return `${money(plan.unit_amount, plan.currency)} <div class="small text-muted">Local</div>`;
    }

    return '<span class="text-muted">Sin monto</span>';
}

function stripeEditButton(plan) {
    const priceId = plan.stripe_price?.id || plan.stripe_price_id;
    if (!priceId || !plan.stripe_exists) {
        return '';
    }

    return `<a class="btn btn-outline-dark" href="${stripeDashboardBase}${encodeURIComponent(priceId)}" target="_blank" rel="noopener" title="Editar en Stripe">
        <i class="fas fa-arrow-up-right-from-square"></i>
    </a>`;
}

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertsContainer');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${esc(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
}

function js(value) {
    return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function cssId(value) {
    return String(value ?? '').replace(/[^a-zA-Z0-9_-]/g, '_');
}
</script>

<?php
$customStyles = <<<CSS
#planModal .modal-dialog {
    max-height: calc(100vh - 2rem);
}

#planModal .modal-body {
    max-height: calc(100vh - 13rem);
    overflow-y: auto;
}

.rules-scroll {
    max-height: 34vh;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.rule-catalog-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.rule-catalog-pill {
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    min-width: 220px;
    padding: 10px 12px;
    border: 1px solid #d7eafd;
    border-radius: 8px;
    background: #f8fbff;
    color: #15395b;
    text-align: left;
}

.rule-catalog-pill:hover {
    border-color: #9fcdfa;
    background: #edf7ff;
}

.rule-catalog-pill strong,
.rule-catalog-pill small {
    display: block;
}

.rule-catalog-pill small {
    color: #6b8299;
    font-family: monospace;
}

.rule-catalog-pill em {
    color: #1769aa;
    font-size: 12px;
    font-style: normal;
    font-weight: 700;
}

.rule-catalog-actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
CSS;

$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
