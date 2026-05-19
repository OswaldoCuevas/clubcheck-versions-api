<?php
$title = 'Licencias - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h3 class="mb-1">Licencias generadas</h3>
                    <p class="text-muted mb-0">Historial completo de licencias emitidas por clientes y administradores.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al panel
                    </a>
                    <button type="button" class="btn btn-outline-secondary" id="refreshLicenses">
                        <i class="fas fa-rotate"></i> Actualizar
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal">
                        <i class="fas fa-file-signature me-2"></i>Generar licencia
                    </button>
                </div>
            </div>

            <div id="alertsContainer"></div>

            <!-- Resumen rápido -->
            <div class="row g-3 mb-4" id="statsRow">
                <div class="col-6 col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="fs-2 fw-bold text-primary" id="statTotal">—</div>
                            <div class="text-muted small">Total licencias</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="fs-2 fw-bold text-success" id="statPermanent">—</div>
                            <div class="text-muted small">Permanentes</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="fs-2 fw-bold text-info" id="statByAdmin">—</div>
                            <div class="text-muted small">Por administrador</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="fs-2 fw-bold text-warning" id="statByCustomer">—</div>
                            <div class="text-muted small">Por cliente</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros rápidos -->
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <input type="text" class="form-control form-control-sm" style="max-width:260px"
                       id="filterSearch" placeholder="Buscar cliente, plan, email…">
                <select class="form-select form-select-sm" style="max-width:160px" id="filterCreatedBy">
                    <option value="">Todos los emisores</option>
                    <option value="customer">Por cliente</option>
                    <option value="admin">Por administrador</option>
                </select>
                <select class="form-select form-select-sm" style="max-width:160px" id="filterType">
                    <option value="">Todos los tipos</option>
                    <option value="permanent">Permanente</option>
                    <option value="recurring">Recurrente</option>
                </select>
            </div>

            <!-- Tabla -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="licensesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Cliente</th>
                                    <th>Plan</th>
                                    <th>Tipo</th>
                                    <th>Expira</th>
                                    <th>Emitido por</th>
                                    <th>Fecha emisión</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="licensesBody">
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fas fa-circle-notch fa-spin me-2"></i>Cargando licencias…
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

<!-- ==================== MODAL: VER DETALLE ==================== -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">
                    <i class="fas fa-id-card me-2"></i>Detalle de licencia
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody">
                <!-- Relleno por JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="downloadLicBtn">
                    <i class="fas fa-download me-1"></i>Descargar .lic
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MODAL: GENERAR ==================== -->
<div class="modal fade" id="generateModal" tabindex="-1" aria-labelledby="generateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="generateForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="generateModalLabel">
                        <i class="fas fa-file-signature me-2"></i>Generar licencia (administrador)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        La vigencia se toma de la suscripción activa del cliente en Stripe. Si el cliente no tiene suscripción activa ni plan permanente, no se podrá generar la licencia.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Cliente <span class="text-danger">*</span></label>
                        <select class="form-select" id="genCustomerId" required>
                            <option value="">— Selecciona un cliente —</option>
                        </select>
                        <div class="form-text" id="genCustomerInfo"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Plan (opcional)</label>
                        <select class="form-select" id="genPlanLookupKey">
                            <option value="">— Auto (desde Stripe) —</option>
                        </select>
                    </div>


                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fecha de caducidad (opcional)</label>
                        <input type="datetime-local" class="form-control" id="genExpiresAt">
                        <div class="form-text">Déjalo vacío para usar la fecha de Stripe o la del plan.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Token de máquina (opcional)</label>
                        <input type="text" class="form-control font-monospace" id="genMachineToken"
                               placeholder="Vacío = usa el token del cliente o sin restricción">
                        <div class="form-text">Déjalo vacío para usar el token registrado del cliente.</div>
                    </div>

                    <div id="generateAlert" class="alert d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="generateSubmitBtn">
                        <i class="fas fa-file-signature me-1"></i>Generar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL: RESULTADO ==================== -->
<div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="resultModalLabel">
                    <i class="fas fa-check-circle me-2"></i>Licencia generada correctamente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="resultModalBody">
                <!-- Relleno por JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="downloadResultBtn">
                    <i class="fas fa-download me-1"></i>Descargar .lic
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ==================== ESTADO GLOBAL ====================
let allLicenses = [];
let currentLicenseFile = null;

const BASE = '<?= app_url('') ?>';

// ==================== CARGAR LISTA ====================
async function loadLicenses() {
    try {
        const res = await fetch(BASE + '/admin/api/licenses');
        const data = await res.json();
        allLicenses = data.licenses ?? [];
        renderStats(allLicenses);
        renderTable(allLicenses);
    } catch (e) {
        showAlert('Error al cargar licencias: ' + e.message, 'danger');
    }
}

function renderStats(licenses) {
    document.getElementById('statTotal').textContent    = licenses.length;
    document.getElementById('statPermanent').textContent = licenses.filter(l => l.isPermanent).length;
    document.getElementById('statByAdmin').textContent   = licenses.filter(l => l.createdBy === 'admin').length;
    document.getElementById('statByCustomer').textContent= licenses.filter(l => l.createdBy === 'customer').length;
}

function renderTable(licenses) {
    const tbody = document.getElementById('licensesBody');
    if (!licenses.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted">No hay licencias registradas.</td></tr>';
        return;
    }
    tbody.innerHTML = licenses.map((l, i) => `
        <tr>
            <td class="text-muted small">${l.id}</td>
            <td>
                <div class="fw-semibold">${esc(l.customerName)}</div>
                <small class="text-muted">${esc(l.customerEmail ?? '')}</small>
            </td>
            <td>
                <code class="small">${esc(l.planLookupKey)}</code>
                <div class="small text-muted">${esc(l.planName)}</div>
            </td>
            <td>
                ${l.isPermanent
                    ? '<span class="badge bg-success">Permanente</span>'
                    : '<span class="badge bg-info text-dark">Recurrente</span>'}
            </td>
            <td class="small">
                ${l.isPermanent
                    ? '<span class="text-muted">—</span>'
                    : (l.expiresAt ? formatDate(l.expiresAt) : '<span class="text-muted">—</span>')}
            </td>
            <td>
                ${l.createdBy === 'admin'
                    ? `<span class="badge bg-warning text-dark"><i class="fas fa-user-shield me-1"></i>Admin</span>
                       <div class="small text-muted">${esc(l.adminUsername ?? '')}</div>`
                    : '<span class="badge bg-secondary"><i class="fas fa-user me-1"></i>Cliente</span>'}
            </td>
            <td class="small text-muted">${formatDate(l.issuedAt)}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-primary" onclick="openDetail(${i})">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ==================== DETALLE ====================
function openDetail(idx) {
    const l = allLicenses[idx];
    currentLicenseFile = l.licenseToken ? buildLicFile(l) : null;

    document.getElementById('detailModalBody').innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="text-muted small">Cliente</label>
                <div class="fw-semibold">${esc(l.customerName)}</div>
                <div class="text-muted small">${esc(l.customerEmail ?? '')}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">ID interno / Billing ID</label>
                <div class="font-monospace small">${esc(l.customerId ?? '—')} / ${esc(l.billingId ?? '—')}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Plan</label>
                <div><code>${esc(l.planLookupKey)}</code> — ${esc(l.planName)}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Tipo</label>
                <div>${l.isPermanent ? '<span class="badge bg-success">Permanente</span>' : '<span class="badge bg-info text-dark">Recurrente</span>'}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Expira</label>
                <div>${l.isPermanent ? '—' : (l.expiresAt ? formatDate(l.expiresAt) : '—')}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Emitido por</label>
                <div>${l.createdBy === 'admin'
                        ? `<span class="badge bg-warning text-dark">Admin</span> ${esc(l.adminUsername ?? '')}`
                        : '<span class="badge bg-secondary">Cliente</span>'}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Fecha emisión</label>
                <div>${formatDate(l.issuedAt)}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Token de máquina</label>
                <div class="font-monospace small text-truncate" style="max-width:220px" title="${esc(l.machineToken ?? '')}">
                    ${l.machineToken ? esc(l.machineToken.substring(0, 12)) + '…' : '—'}
                </div>
            </div>
            <div class="col-12">
                <label class="text-muted small">Token de licencia</label>
                <div class="input-group">
                    <input type="text" class="form-control form-control-sm font-monospace"
                           value="${esc(l.licenseToken)}" readonly id="copyTokenInput">
                    <button class="btn btn-sm btn-outline-secondary" onclick="copyToken()">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    `;

    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

function copyToken() {
    const input = document.getElementById('copyTokenInput');
    input.select();
    navigator.clipboard.writeText(input.value).then(() => {
        showAlert('Token copiado al portapapeles', 'success', 2000);
    });
}

function buildLicFile(l) {
    return [
        '-----BEGIN CLUBCHECK LICENSE-----',
        l.licenseToken,
        '-----END CLUBCHECK LICENSE-----',
    ].join('\n');
}

document.getElementById('downloadLicBtn').addEventListener('click', () => {
    if (!currentLicenseFile) return;
    downloadFile(currentLicenseFile, 'clubcheck.lic');
});

// ==================== GENERAR (admin) ====================
async function loadCustomersForSelect() {
    try {
        const res = await fetch(BASE + '/admin/api/customers');
        const data = await res.json();
        const sel = document.getElementById('genCustomerId');
        (data.customers ?? []).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.customerId;
            opt.textContent = `${c.name ?? c.customerId} — ${c.email ?? ''}`;
            opt.dataset.token = c.token ?? '';
            opt.dataset.billingId = c.billingId ?? '';
            sel.appendChild(opt);
        });
    } catch (e) { /* silencioso */ }
}

async function loadPlansForSelect() {
    try {
        const res = await fetch(BASE + '/api/customers/stripe/plans');
        const data = await res.json();
        const sel = document.getElementById('genPlanLookupKey');
        (data.plans ?? []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.lookup_key;
            opt.textContent = `${p.name} (${p.lookup_key})`;
            sel.appendChild(opt);
        });
    } catch (e) { /* silencioso */ }
}

document.getElementById('genCustomerId').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const info = document.getElementById('genCustomerInfo');
    if (this.value) {
        const billing = opt.dataset.billingId || '—';
        info.textContent = `Billing ID: ${billing}`;
        if (opt.dataset.token) {
            document.getElementById('genMachineToken').placeholder = opt.dataset.token.substring(0, 12) + '… (registrado)';
        }
    } else {
        info.textContent = '';
    }
});

document.getElementById('generateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('generateSubmitBtn');
    const alert = document.getElementById('generateAlert');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generando…';
    if (alert) alert.className = 'alert d-none';

    const customerId    = document.getElementById('genCustomerId').value;
    const planLookupKey = document.getElementById('genPlanLookupKey').value;
    const machineToken  = document.getElementById('genMachineToken').value.trim();
    const expiresAtRaw  = document.getElementById('genExpiresAt').value;

    if (!customerId) {
        if (alert) {
            alert.className = 'alert alert-danger';
            alert.textContent = 'Selecciona un cliente.';
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-signature me-1"></i>Generar';
        return;
    }

    const body = { customerId };
    if (planLookupKey) body.planLookupKey = planLookupKey;
    if (machineToken)  body.machineToken  = machineToken;
    if (expiresAtRaw) {
        // Convertir a timestamp Unix (segundos)
        const dt = new Date(expiresAtRaw);
        if (!isNaN(dt.getTime())) {
            body.expiresAt = Math.floor(dt.getTime() / 1000);
        }
    }

    try {
        const res  = await fetch(BASE + '/admin/api/licenses/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();

        if (!data.success) throw new Error(data.error ?? 'Error desconocido');

        // Cerrar modal de generación
        bootstrap.Modal.getInstance(document.getElementById('generateModal')).hide();

        // Mostrar resultado
        showResult(data);

        // Recargar tabla
        loadLicenses();
    } catch (err) {
        if (alert) {
            alert.className = 'alert alert-danger';
            alert.textContent = err.message;
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-signature me-1"></i>Generar';
    }
});

function showResult(data) {
    const licFile = data.license_file;
    currentLicenseFile = licFile;

    const expires = data.is_permanent
        ? '<span class="badge bg-success">Permanente</span>'
        : (data.expires_at ? `<span class="badge bg-info text-dark">${new Date(data.expires_at * 1000).toLocaleDateString('es-MX')}</span>` : '—');

    document.getElementById('resultModalBody').innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="text-muted small">Cliente</label>
                <div class="fw-semibold">${esc(data.customer_name ?? '')}</div>
                <div class="text-muted small">${esc(data.customer_email ?? '')}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Plan</label>
                <div><code>${esc(data.plan_lookup_key ?? '')}</code> — ${esc(data.plan_name ?? '')}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Tipo</label>
                <div>${data.is_permanent ? '<span class="badge bg-success">Permanente</span>' : '<span class="badge bg-info text-dark">Recurrente</span>'}</div>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Expira</label>
                <div>${expires}</div>
            </div>
            <div class="col-12 mt-2">
                <label class="text-muted small">Contenido del archivo .lic</label>
                <pre class="bg-dark text-success p-3 rounded small" style="max-height:200px;overflow:auto;white-space:pre-wrap">${esc(licFile ?? '')}</pre>
            </div>
        </div>
    `;

    document.getElementById('downloadResultBtn').onclick = () => downloadFile(licFile, 'clubcheck.lic');
    new bootstrap.Modal(document.getElementById('resultModal')).show();
}

// ==================== FILTROS ====================
function applyFilters() {
    const search    = document.getElementById('filterSearch').value.toLowerCase();
    const createdBy = document.getElementById('filterCreatedBy').value;
    const type      = document.getElementById('filterType').value;

    const filtered = allLicenses.filter(l => {
        const matchSearch = !search || [
            l.customerName, l.customerEmail, l.planLookupKey, l.planName,
            l.customerId, l.billingId, l.adminUsername,
        ].some(v => (v ?? '').toLowerCase().includes(search));

        const matchCreatedBy = !createdBy || l.createdBy === createdBy;

        const matchType = !type
            || (type === 'permanent' && l.isPermanent)
            || (type === 'recurring' && !l.isPermanent);

        return matchSearch && matchCreatedBy && matchType;
    });

    renderTable(filtered);
}

['filterSearch', 'filterCreatedBy', 'filterType'].forEach(id => {
    document.getElementById(id).addEventListener('input', applyFilters);
    document.getElementById(id).addEventListener('change', applyFilters);
});

// ==================== UTILIDADES ====================
function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
}

function downloadFile(content, filename) {
    const blob = new Blob([content], { type: 'text/plain' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function showAlert(msg, type = 'success', ms = 5000) {
    const container = document.getElementById('alertsContainer');
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show`;
    div.innerHTML = `${esc(msg)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    container.appendChild(div);
    if (ms) setTimeout(() => div.remove(), ms);
}

// ==================== INIT ====================
document.getElementById('refreshLicenses').addEventListener('click', loadLicenses);

// Cargar datos al abrir modal de generación
document.getElementById('generateModal').addEventListener('show.bs.modal', function() {
    const sel = document.getElementById('genCustomerId');
    if (sel.options.length <= 1) {
        loadCustomersForSelect();
        loadPlansForSelect();
    }
});

loadLicenses();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
