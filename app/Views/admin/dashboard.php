<?php
$title = 'Dashboard';

ob_start();
?>

<div class="ops-dashboard">
    <header class="ops-actions-bar">
        <div class="topbar-actions">
            <a href="#" target="_blank" rel="noopener" class="action-btn dark" id="stripeLink">
                <i class="fas fa-arrow-up-right-from-square"></i>
                <span>Stripe</span>
            </a>
            <button type="button" class="action-btn primary" id="refreshBtn">
                <i class="fas fa-rotate"></i>
                <span>Actualizar</span>
            </button>
        </div>
    </header>

    <div id="alertsContainer"></div>

    <main class="dashboard-grid">
        <a href="#" target="_blank" rel="noopener" class="hero-revenue metric-link" id="receivedStripeLink">
            <div>
                <span>Recibido este mes</span>
                <strong id="receivedThisMonth">$0.00</strong>
                <small id="stripeStatus">Consultando Stripe...</small>
            </div>
            <div class="hero-mark">
                <i class="fas fa-wallet"></i>
            </div>
        </a>

        <a href="#" target="_blank" rel="noopener" class="metric-tile metric-link" id="expectedStripeLink">
            <div class="tile-top">
                <span>Total a recibir</span>
                <i class="fas fa-calendar-check"></i>
            </div>
            <strong id="expectedThisMonth">$0.00</strong>
            <small id="expectedSubscriptions">0 pagos esperados</small>
            <div class="progress-line"><span id="expectedBar"></span></div>
        </a>

        <a href="<?= app_url('/admin/customers') ?>" class="metric-tile metric-link">
            <div class="tile-top">
                <span>Clientes</span>
                <i class="fas fa-users"></i>
            </div>
            <strong id="totalCustomers">0</strong>
            <small id="activeCustomers">0 activos / 0 con billing</small>
        </a>

        <a href="<?= app_url('/admin/customer-stats') ?>" class="metric-tile metric-link whatsapp-tile">
            <div class="tile-top">
                <span>WhatsApp este mes</span>
                <i class="fab fa-whatsapp"></i>
            </div>
            <strong id="whatsappCost">$0.00</strong>
            <small id="whatsappMessages">0 mensajes enviados</small>
        </a>

        <section class="metric-tile settings-tile">
            <div class="tile-top">
                <span>Costo por mensaje</span>
                <i class="fas fa-coins"></i>
            </div>
            <div class="cost-control">
                <input type="number" min="0" step="0.000001" id="messageCostInput" aria-label="Costo por mensaje WhatsApp">
                <button type="button" id="saveCostBtn" title="Guardar costo">
                    <i class="fas fa-check"></i>
                </button>
            </div>
            <small>MXN por mensaje exitoso</small>
        </section>

        <section class="finance-band">
            <div>
                <span>Proyeccion del mes</span>
                <strong id="totalToReceive">$0.00</strong>
                <small>Estimado desde suscripciones Stripe</small>
            </div>
            <div>
                <span>Gasto WhatsApp</span>
                <strong id="whatsappTotalBand">$0.00</strong>
                <small>Mensajes enviados x costo configurado</small>
            </div>
            <a href="<?= app_url('/admin/stripe-plans') ?>" class="band-action">
                <i class="fas fa-tags"></i>
                <span>Planes</span>
            </a>
        </section>

        <section class="insight-panel summary-panel">
            <div class="panel-heading">
                <div>
                    <span>Resumen</span>
                    <h2>Indicadores del mes</h2>
                </div>
                <a href="<?= app_url('/admin/customer-stats') ?>">Ver estadisticas</a>
            </div>
            <div class="chart-wrap">
                <canvas id="summaryChart"></canvas>
            </div>
            <div class="month-list" id="whatsappMonthsList"></div>
        </section>

        <section class="insight-panel storage-panel">
            <div class="panel-heading">
                <div>
                    <span>Base de datos</span>
                    <h2>Espacio por tabla</h2>
                </div>
                <a href="<?= app_url('/admin/desktop-tables') ?>">Explorar tablas</a>
            </div>
            <div class="storage-content">
                <div class="chart-wrap">
                    <canvas id="storageChart"></canvas>
                </div>
                <div>
                    <div class="storage-total">
                        <span>Total estimado</span>
                        <strong id="storageTotal">0 MB</strong>
                    </div>
                    <div class="table-list" id="storageList"></div>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const endpoints = <?= json_encode([
    'dashboard' => app_url('/admin/api/dashboard'),
    'settings' => app_url('/admin/api/dashboard/settings'),
], JSON_UNESCAPED_SLASHES) ?>;

let summaryChart;
let storageChart;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('refreshBtn').addEventListener('click', loadDashboard);
    document.getElementById('saveCostBtn').addEventListener('click', saveMessageCost);
    loadDashboard();
});

async function loadDashboard() {
    setRefreshState(true);
    try {
        const res = await fetch(endpoints.dashboard);
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'No se pudo cargar el dashboard');

        const stripeDashboardUrl = data.stripe_dashboard_url || '#';
        document.getElementById('stripeLink').href = stripeDashboardUrl;
        document.getElementById('receivedStripeLink').href = `${stripeDashboardUrl}payments`;
        document.getElementById('expectedStripeLink').href = `${stripeDashboardUrl}subscriptions`;
        renderDashboard(data.dashboard || {});
    } catch (e) {
        showAlert(e.message, 'danger');
    } finally {
        setRefreshState(false);
    }
}

function renderDashboard(dashboard) {
    const stripe = dashboard.stripe || {};
    const customers = dashboard.customers || {};
    const whatsapp = dashboard.whatsapp || {};
    const storage = dashboard.storage || {};

    const received = Number(stripe.receivedThisMonth?.amount || 0);
    const expected = Number(stripe.expectedThisMonth?.amount || 0);
    const currency = stripe.expectedThisMonth?.currency || stripe.receivedThisMonth?.currency || 'mxn';

    document.getElementById('receivedThisMonth').textContent = moneyCents(received, currency);
    document.getElementById('expectedThisMonth').textContent = moneyCents(expected, currency);
    document.getElementById('expectedSubscriptions').textContent = `${stripe.expectedThisMonth?.subscriptions || 0} pagos esperados`;
    document.getElementById('stripeStatus').textContent = stripe.success ? 'Datos sincronizados desde Stripe' : (stripe.error || 'Stripe no disponible');
    document.getElementById('expectedBar').style.width = `${Math.min(100, expected > 0 ? (received / expected) * 100 : 0)}%`;
    document.getElementById('totalToReceive').textContent = moneyCents(expected, currency);

    document.getElementById('totalCustomers').textContent = customers.total || 0;
    document.getElementById('activeCustomers').textContent = `${customers.active || 0} activos / ${customers.withBillingId || 0} con billing`;

    document.getElementById('whatsappCost').textContent = moneyPesos(whatsapp.currentMonthCost || 0);
    document.getElementById('whatsappMessages').textContent = `${whatsapp.currentMonthMessages || 0} mensajes enviados`;
    document.getElementById('messageCostInput').value = whatsapp.unitCost ?? 0;
    document.getElementById('whatsappTotalBand').textContent = moneyPesos(whatsapp.currentMonthCost || 0);

    document.getElementById('storageTotal').textContent = `${Number(storage.totalMb || 0).toFixed(2)} MB`;
    renderSummaryChart(customers, whatsapp, stripe);
    renderStorageChart(storage.tables || []);
    renderStorageList(storage.tables || []);
    renderWhatsappMonths(whatsapp.months || []);
}

function renderSummaryChart(customers, whatsapp, stripe) {
    const data = [
        customers.total || 0,
        customers.active || 0,
        whatsapp.currentMonthMessages || 0,
        stripe.expectedThisMonth?.subscriptions || 0,
    ];

    if (summaryChart) summaryChart.destroy();
    summaryChart = new Chart(document.getElementById('summaryChart'), {
        type: 'doughnut',
        data: {
            labels: ['Clientes', 'Activos', 'Mensajes', 'Pagos esperados'],
            datasets: [{
                data,
                backgroundColor: ['#2f80ed', '#56ccf2', '#27ae60', '#f2c94c'],
                borderColor: '#ffffff',
                borderWidth: 4,
                hoverOffset: 8
            }]
        },
        options: chartOptions()
    });
}

function renderStorageChart(tables) {
    const top = tables.slice(0, 8);
    if (storageChart) storageChart.destroy();
    storageChart = new Chart(document.getElementById('storageChart'), {
        type: 'pie',
        data: {
            labels: top.map(t => t.name),
            datasets: [{
                data: top.map(t => t.mb),
                backgroundColor: ['#2f80ed', '#56ccf2', '#27ae60', '#f2c94c', '#9b51e0', '#2d9cdb', '#f2994a', '#8aa3bd'],
                borderColor: '#ffffff',
                borderWidth: 3
            }]
        },
        options: chartOptions()
    });
}

function renderStorageList(tables) {
    const list = document.getElementById('storageList');
    list.innerHTML = tables.slice(0, 8).map(table => `
        <a class="data-row" href="<?= app_url('/admin/desktop-tables') ?>">
            <span>${esc(table.name)}</span>
            <strong>${Number(table.mb || 0).toFixed(2)} MB</strong>
        </a>
    `).join('') || '<div class="empty-state">Sin datos de almacenamiento.</div>';
}

function renderWhatsappMonths(months) {
    const list = document.getElementById('whatsappMonthsList');
    list.innerHTML = months.slice(-6).map(month => `
        <a class="data-row" href="<?= app_url('/admin/customer-stats') ?>">
            <span>${esc(month.month)} / ${Number(month.messages || 0)} mensajes</span>
            <strong>${moneyPesos(month.cost || 0)}</strong>
        </a>
    `).join('') || '<div class="empty-state">Sin mensajes WhatsApp recientes.</div>';
}

async function saveMessageCost() {
    try {
        const cost = Number(document.getElementById('messageCostInput').value || 0);
        const res = await fetch(endpoints.settings, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ whatsapp_message_unit_cost_mxn: cost })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'No se pudo guardar');
        showAlert('Costo por mensaje actualizado.', 'success');
        await loadDashboard();
    } catch (e) {
        showAlert(e.message, 'danger');
    }
}

function chartOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    boxWidth: 8,
                    color: '#475569',
                    font: { size: 12, weight: '600' }
                }
            }
        }
    };
}

function setRefreshState(isLoading) {
    const btn = document.getElementById('refreshBtn');
    btn.disabled = isLoading;
    btn.querySelector('span').textContent = isLoading ? 'Cargando' : 'Actualizar';
}

function moneyCents(amount, currency) {
    return (Number(amount || 0) / 100).toLocaleString('es-MX', { style: 'currency', currency: String(currency || 'mxn').toUpperCase() });
}

function moneyPesos(amount) {
    return Number(amount || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

function showAlert(message, type) {
    document.getElementById('alertsContainer').innerHTML = `<div class="alert alert-${type} alert-dismissible fade show">
        ${esc(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
}
</script>

<?php
$customStyles = <<<CSS
body {
    background: linear-gradient(180deg, #f4f9ff 0%, #eef7ff 42%, #f8fbff 100%);
    color: #17324d;
}

.navbar {
    background: #1d6fb8 !important;
    border-bottom: 1px solid rgba(255,255,255,0.18);
}

.ops-dashboard {
    width: min(1180px, 100%);
    margin: 26px auto 48px;
}

.ops-topbar {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 18px;
}

.ops-actions-bar {
    display: flex;
    justify-content: flex-end;
    margin: -4px 0 18px;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    height: 28px;
    padding: 0 10px;
    border: 1px solid #cfe3f8;
    border-radius: 999px;
    background: #ffffffcc;
    color: #2f80ed;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0;
}

.ops-topbar h1 {
    margin: 12px 0 4px;
    font-size: clamp(28px, 4vw, 40px);
    font-weight: 700;
    letter-spacing: 0;
    color: #15395b;
}

.ops-topbar p {
    margin: 0;
    color: #5f7894;
    font-weight: 500;
}

.topbar-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 10px;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 42px;
    padding: 0 15px;
    border-radius: 7px;
    border: 1px solid transparent;
    text-decoration: none;
    font-weight: 700;
    transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.action-btn:hover {
    transform: translateY(-1px);
    text-decoration: none;
}

.action-btn.primary {
    background: #2f80ed;
    color: #ffffff;
    box-shadow: 0 6px 14px rgba(47, 128, 237, 0.14);
}

.action-btn.secondary {
    background: #ffffffd9;
    color: #285372;
    border-color: #cfe3f8;
}

.action-btn.dark {
    background: #e8f4ff;
    color: #1769aa;
    border-color: #c1ddf7;
    box-shadow: 0 6px 14px rgba(47, 128, 237, 0.08);
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 14px;
}

.hero-revenue,
.metric-tile,
.finance-band,
.insight-panel {
    background: #ffffff;
    border: 1px solid #d7eafd;
    border-radius: 10px;
    box-shadow: 0 8px 18px rgba(47, 128, 237, 0.06);
}

.metric-link {
    color: inherit;
    text-decoration: none;
    transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
}

.metric-link:hover {
    color: inherit;
    text-decoration: none;
    transform: translateY(-2px);
    border-color: #9fcdfa;
    box-shadow: 0 8px 18px rgba(47, 128, 237, 0.07);
}

.hero-revenue {
    grid-column: span 12;
    min-height: 176px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: clamp(20px, 4vw, 34px);
    background: #f7fbff;
}

.hero-revenue span,
.metric-tile span,
.finance-band span,
.panel-heading span,
.storage-total span {
    display: block;
    color: #6b8299;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0;
}

.hero-revenue strong {
    display: block;
    margin: 8px 0 10px;
    color: #15395b;
    font-size: clamp(36px, 6vw, 58px);
    line-height: 1;
    font-weight: 700;
}

.hero-revenue small,
.metric-tile small,
.finance-band small {
    color: #6b8299;
    font-weight: 500;
}

.hero-mark {
    width: clamp(78px, 16vw, 118px);
    height: clamp(78px, 16vw, 118px);
    display: grid;
    place-items: center;
    border-radius: 24px;
    background: #e1f4ff;
    color: #2f80ed;
    font-size: clamp(28px, 5vw, 42px);
    box-shadow: inset 0 0 0 1px #c7e2fb, 0 8px 18px rgba(47, 128, 237, 0.08);
}

.metric-tile {
    grid-column: span 3;
    min-height: 126px;
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr);
    grid-template-rows: auto auto auto;
    align-content: center;
    column-gap: 16px;
    row-gap: 4px;
    padding: 22px;
}

.tile-top {
    display: contents;
}

.tile-top i {
    grid-column: 1;
    grid-row: 1 / span 2;
    align-self: center;
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
    border-radius: 16px;
    background: #e1f4ff;
    color: #087cba;
    font-size: 19px;
}

.tile-top span {
    grid-column: 2;
    grid-row: 2;
    min-width: 0;
}

.whatsapp-tile .tile-top i {
    background: #e6f8ff;
    color: #087cba;
}

.settings-tile .tile-top i {
    background: #e6f8ff;
    color: #087cba;
}

.metric-tile strong {
    display: block;
    grid-column: 2;
    grid-row: 1;
    margin: 0;
    color: #15395b;
    font-size: clamp(26px, 3vw, 34px);
    line-height: 1;
    font-weight: 700;
}

.metric-tile small {
    grid-column: 2;
    grid-row: 3;
    min-width: 0;
}

.progress-line {
    grid-column: 1 / -1;
    height: 8px;
    margin-top: 14px;
    overflow: hidden;
    border-radius: 999px;
    background: #e2f1ff;
}

.progress-line span {
    display: block;
    width: 0;
    height: 100%;
    border-radius: inherit;
    background: #2f80ed;
}

.cost-control {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 44px;
    gap: 10px;
    margin: 14px 0 6px;
}

.settings-tile > small {
    grid-column: 1 / -1;
}

.cost-control input {
    min-width: 0;
    height: 44px;
    border: 1px solid #c7dcf1;
    border-radius: 7px;
    padding: 0 12px;
    color: #15395b;
    font-weight: 700;
}

.cost-control button {
    height: 44px;
    border: 0;
    border-radius: 7px;
    background: #21b26f;
    color: #ffffff;
    box-shadow: 0 6px 14px rgba(33, 178, 111, 0.12);
}

.finance-band {
    grid-column: span 12;
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    align-items: center;
    gap: 18px;
    padding: 20px;
    background: #f7fbff;
    color: #17324d;
}

.finance-band span,
.finance-band small {
    color: #66819b;
}

.finance-band strong {
    display: block;
    margin: 6px 0;
    color: #1769aa;
    font-size: clamp(26px, 4vw, 38px);
    font-weight: 700;
    line-height: 1;
}

.band-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 44px;
    padding: 0 16px;
    border-radius: 7px;
    background: #2f80ed;
    color: #ffffff;
    text-decoration: none;
    font-weight: 700;
    box-shadow: 0 6px 14px rgba(47, 128, 237, 0.12);
}

.band-action:hover {
    color: #ffffff;
}

.insight-panel {
    grid-column: span 6;
    min-height: 420px;
    padding: 20px;
}

.panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    padding-bottom: 14px;
    margin-bottom: 16px;
    border-bottom: 1px solid #dbeafa;
}

.panel-heading h2 {
    margin: 4px 0 0;
    color: #15395b;
    font-size: 20px;
    font-weight: 700;
}

.panel-heading a {
    color: #2f80ed;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.chart-wrap {
    height: 230px;
}

.storage-content {
    display: grid;
    grid-template-columns: minmax(0, 0.95fr) minmax(220px, 1fr);
    gap: 18px;
    align-items: center;
}

.storage-total {
    padding: 13px 14px;
    border-radius: 8px;
    background: #f5fbff;
    border: 1px solid #dbeafa;
    margin-bottom: 10px;
}

.storage-total strong {
    display: block;
    margin-top: 4px;
    color: #15395b;
    font-size: 24px;
    font-weight: 700;
}

.table-list,
.month-list {
    display: grid;
    gap: 8px;
    margin-top: 14px;
}

.data-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    min-height: 42px;
    padding: 9px 12px;
    border: 1px solid #dbeafa;
    border-radius: 7px;
    color: #315574;
    background: #ffffff;
    text-decoration: none;
}

.data-row:hover {
    color: #15395b;
    border-color: #bfdbfe;
    background: #f5fbff;
    text-decoration: none;
}

.data-row span {
    min-width: 0;
    overflow-wrap: anywhere;
    font-weight: 600;
}

.data-row strong {
    white-space: nowrap;
    color: #15395b;
    font-size: 13px;
}

.empty-state {
    padding: 18px;
    border: 1px dashed #c7dcf1;
    border-radius: 8px;
    color: #6b8299;
    background: #f5fbff;
}

@media (max-width: 991.98px) {
    .ops-topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .topbar-actions {
        width: 100%;
        justify-content: flex-start;
    }

    .metric-tile {
        grid-column: span 6;
    }

    .insight-panel {
        grid-column: span 12;
    }
}

@media (max-width: 640px) {
    .ops-dashboard {
        width: min(100vw - 14px, 430px);
        margin-top: 12px;
    }

    .topbar-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .topbar-actions .primary {
        grid-column: span 2;
    }

    .hero-revenue {
        min-height: 158px;
        padding: 18px;
    }

    .hero-mark {
        border-radius: 22px;
    }

    .metric-tile {
        grid-column: span 12;
        min-height: 118px;
        padding: 18px;
    }

    .finance-band {
        grid-template-columns: 1fr;
    }

    .band-action {
        width: 100%;
    }

    .storage-content {
        grid-template-columns: 1fr;
    }

    .chart-wrap {
        height: 210px;
    }
}
CSS;

$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
