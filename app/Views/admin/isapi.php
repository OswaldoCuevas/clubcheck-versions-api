<?php
$customersJson = json_encode($customers ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$actionsJson = json_encode($actions ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$endpointsJson = json_encode([
    'index' => app_url('/admin/api/isapi'),
    'create' => app_url('/admin/api/isapi/commands'),
    'show' => app_url('/admin/api/isapi/commands/:id'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$customStyles = <<<'CSS'
.isapi-code {
    max-height: 420px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
    background: #101827;
    color: #dbeafe;
    border-radius: 6px;
    padding: 1rem;
    font-size: .82rem;
}
.status-dot {
    width: .65rem;
    height: .65rem;
    display: inline-block;
    border-radius: 50%;
    margin-right: .4rem;
}
.command-row { cursor: pointer; }
.command-row:hover { background: #f3f7fb; }
CSS;

ob_start();
?>
<div class="container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="fas fa-fingerprint me-2"></i>Diagnostico remoto ISAPI</h3>
            <p class="text-muted mb-0">Solicitudes seguras de solo lectura ejecutadas por el cliente de escritorio.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Volver
            </a>
            <button class="btn btn-outline-primary" id="refreshButton" type="button">
                <i class="fas fa-rotate me-1"></i>Actualizar
            </button>
        </div>
    </div>

    <div id="alertContainer"></div>

    <div class="alert alert-info">
        <i class="fas fa-shield-halved me-2"></i>
        El servidor solo envia nombres de comandos. El cliente decide como consultar ISAPI y conserva localmente sus credenciales.
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header py-3">
                    <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Nueva consulta</h5>
                </div>
                <div class="card-body">
                    <form id="commandForm">
                        <div class="mb-3">
                            <label for="customerId" class="form-label">Cliente</label>
                            <select class="form-select" id="customerId" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach (($customers ?? []) as $customer): ?>
                                    <option value="<?= htmlspecialchars($customer['customerId'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($customer['name'] . ' — ' . $customer['customerId'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="agentId" class="form-label">Instalacion del cliente desktop</label>
                            <input class="form-control" id="agentId" value="desktop-main" maxlength="100" required>
                            <div class="form-text">Debe coincidir con el identificador usado por el cliente.</div>
                        </div>
                        <div class="mb-3">
                            <label for="terminalIndex" class="form-label">Indice de terminal</label>
                            <input class="form-control" id="terminalIndex" type="number" value="0" min="0" max="999" list="terminalOptions" required>
                            <datalist id="terminalOptions"></datalist>
                            <div class="form-text">Base cero: 0 es la primera terminal del arreglo del cliente.</div>
                        </div>
                        <div class="mb-3">
                            <label for="deviceId" class="form-label">Dispositivo local (opcional)</label>
                            <input class="form-control" id="deviceId" maxlength="100" placeholder="terminal-01">
                        </div>
                        <div class="mb-3">
                            <label for="action" class="form-label">Consulta permitida</label>
                            <select class="form-select" id="action" required>
                                <?php foreach (($actions ?? []) as $action): ?>
                                    <option value="<?= htmlspecialchars($action['key'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="actionDescription"></div>
                        </div>
                        <div id="paginationFields" class="border rounded p-3 mb-3 d-none">
                            <div class="fw-semibold mb-2">Paginacion</div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="page" class="form-label">Pagina</label>
                                    <input class="form-control" id="page" type="number" value="1" min="1" max="1000000">
                                </div>
                                <div class="col-6">
                                    <label for="pageSize" class="form-label">Registros</label>
                                    <input class="form-control" id="pageSize" type="number" value="50" min="1" max="100">
                                </div>
                                <div class="col-12">
                                    <label for="cursor" class="form-label">Cursor (opcional)</label>
                                    <input class="form-control" id="cursor" maxlength="200" placeholder="Para continuar desde una respuesta anterior">
                                </div>
                            </div>
                        </div>
                        <div id="activityFields" class="border rounded p-3 mb-3 d-none">
                            <div class="fw-semibold mb-2">Rango de actividad</div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="activityFrom" class="form-label">Desde</label>
                                    <input class="form-control" id="activityFrom" type="datetime-local">
                                </div>
                                <div class="col-6">
                                    <label for="activityTo" class="form-label">Hasta</label>
                                    <input class="form-control" id="activityTo" type="datetime-local">
                                </div>
                            </div>
                            <div class="form-text">Maximo 31 dias. Vacio consulta las ultimas 24 horas.</div>
                        </div>
                        <button class="btn btn-primary w-100" type="submit" id="submitButton">
                            <i class="fas fa-paper-plane me-2"></i>Enviar consulta
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-desktop me-2"></i>Clientes desktop conectados</h5>
                    <small id="lastRefresh">Sin actualizar</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Cliente / agente</th><th>Terminal</th><th>Ultimo contacto</th></tr>
                            </thead>
                            <tbody id="agentsBody">
                                <tr><td colspan="3" class="text-center text-muted py-4">Esperando el primer heartbeat...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Historial de consultas</h5>
            <select class="form-select form-select-sm" id="historyCustomer" style="max-width: 320px">
                <option value="">Todos los clientes</option>
                <?php foreach (($customers ?? []) as $customer): ?>
                    <option value="<?= htmlspecialchars($customer['customerId'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($customer['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Creada</th><th>Cliente</th><th>Accion</th><th>Estado</th>
                            <th>HTTP</th><th>Duracion</th><th></th>
                        </tr>
                    </thead>
                    <tbody id="commandsBody">
                        <tr><td colspan="7" class="text-center text-muted py-4">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-code me-2"></i>Respuesta ISAPI</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="detailSummary" class="mb-3"></div>
                <div id="detailError" class="alert alert-danger d-none"></div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Cuerpo original</strong>
                    <button class="btn btn-sm btn-outline-secondary" id="copyResponse" type="button">
                        <i class="fas fa-copy me-1"></i>Copiar
                    </button>
                </div>
                <pre class="isapi-code mb-0" id="responseBody">Sin respuesta.</pre>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

ob_start();
?>
<script>
(function () {
    const endpoints = <?= $endpointsJson ?>;
    const actions = <?= $actionsJson ?>;
    const csrfToken = <?= json_encode($csrfToken ?? '') ?>;
    const actionMap = Object.fromEntries(actions.map(item => [item.key, item]));
    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
    let currentBody = '';
    let agentsCache = [];

    const el = id => document.getElementById(id);
    const escapeHtml = value => {
        const node = document.createElement('div');
        node.textContent = value === null || value === undefined ? '' : String(value);
        return node.innerHTML;
    };
    const formatDate = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleString('es-MX') : '—';
    const badge = status => ({
        Pending: 'warning', Processing: 'info', Completed: 'success',
        Failed: 'danger', Expired: 'secondary', Cancelled: 'secondary'
    })[status] || 'secondary';

    function alertMessage(type, message) {
        el('alertContainer').innerHTML = `<div class="alert alert-${type} alert-dismissible fade show">
            ${escapeHtml(message)}<button class="btn-close" data-bs-dismiss="alert"></button></div>`;
    }

    function updateActionDescription() {
        const action = el('action').value;
        el('actionDescription').textContent = actionMap[action]?.description || '';
        const paginated = ['get_registered_members', 'get_recent_activity'].includes(action);
        el('paginationFields').classList.toggle('d-none', !paginated);
        el('activityFields').classList.toggle('d-none', action !== 'get_recent_activity');
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {credentials: 'same-origin', ...options});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.error || `Error HTTP ${response.status}`);
        return data;
    }

    function renderAgents(agents) {
        agentsCache = agents;
        updateTerminalOptions();
        if (!agents.length) {
            el('agentsBody').innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">El cliente de escritorio aun no ha enviado heartbeat.</td></tr>';
            return;
        }
        el('agentsBody').innerHTML = agents.map(agent => {
            const agentOnline = Number(agent.AgentOnline) === 1;
            const terminalKnown = agent.TerminalOnline !== null;
            const terminalOnline = Number(agent.TerminalOnline) === 1;
            return `<tr>
                <td><strong>${escapeHtml(agent.CustomerName)}</strong><br><small class="text-muted">${escapeHtml(agent.AgentId)}</small></td>
                <td><span class="status-dot bg-${terminalKnown ? (terminalOnline ? 'success' : 'danger') : 'secondary'}"></span>
                    ${terminalKnown ? (terminalOnline ? 'Disponible' : 'Sin respuesta') : 'Sin comprobar'}
                    <br><small class="text-muted">${escapeHtml(agent.DeviceId || 'Sin ID')}</small></td>
                <td><span class="badge bg-${agentOnline ? 'success' : 'secondary'}">${agentOnline ? 'Conectado' : 'Desconectado'}</span>
                    <br><small class="text-muted">${formatDate(agent.LastSeenAt)}</small></td>
            </tr>`;
        }).join('');
    }

    function terminalsForSelection() {
        const customerId = el('customerId').value;
        const agentId = el('agentId').value.trim();
        const agent = agentsCache.find(item => item.CustomerId === customerId && item.AgentId === agentId);
        let terminalInfo = {};
        try {
            terminalInfo = typeof agent?.TerminalInfo === 'string'
                ? JSON.parse(agent.TerminalInfo)
                : (agent?.TerminalInfo || {});
        } catch (_) {
            terminalInfo = {};
        }
        return Array.isArray(terminalInfo.terminals) ? terminalInfo.terminals : [];
    }

    function updateTerminalOptions() {
        const terminals = terminalsForSelection();
        el('terminalOptions').innerHTML = terminals
            .filter(item => Number.isInteger(Number(item.index)) && Number(item.index) >= 0)
            .map(item => `<option value="${Number(item.index)}">${escapeHtml(item.name || item.id || `Terminal ${item.index}`)}</option>`)
            .join('');
    }

    function selectTerminalLabel() {
        const index = Number(el('terminalIndex').value);
        const terminal = terminalsForSelection().find(item => Number(item.index) === index);
        if (terminal) {
            el('deviceId').value = terminal.id || terminal.name || '';
        }
    }

    function renderCommands(commands) {
        if (!commands.length) {
            el('commandsBody').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No hay consultas.</td></tr>';
            return;
        }
        el('commandsBody').innerHTML = commands.map(command => `<tr class="command-row" data-id="${escapeHtml(command.Id)}">
            <td><small>${formatDate(command.CreatedAt)}</small></td>
            <td><strong>${escapeHtml(command.CustomerName)}</strong><br><small class="text-muted">${escapeHtml(command.AgentId)}</small></td>
            <td><code>${escapeHtml(command.Action)}</code><br><small class="text-muted">Terminal [${Number(command.TerminalIndex || 0)}]${command.DeviceId ? ` · ${escapeHtml(command.DeviceId)}` : ''}</small></td>
            <td><span class="badge bg-${badge(command.Status)}">${escapeHtml(command.Status)}</span>${command.ErrorMessage ? `<br><small class="text-danger">${escapeHtml(command.ErrorMessage)}</small>` : ''}</td>
            <td>${command.HttpStatus || '—'}</td>
            <td>${command.DurationMs !== null ? `${Number(command.DurationMs)} ms` : '—'}</td>
            <td><button class="btn btn-sm btn-outline-primary" type="button">Ver</button></td>
        </tr>`).join('');
        document.querySelectorAll('.command-row').forEach(row => row.addEventListener('click', () => showDetail(row.dataset.id)));
    }

    async function refresh() {
        const filter = el('historyCustomer').value;
        const url = endpoints.index + (filter ? `?customerId=${encodeURIComponent(filter)}` : '');
        try {
            const data = await request(url);
            renderAgents(data.agents || []);
            renderCommands(data.commands || []);
            el('lastRefresh').textContent = `Actualizado ${new Date().toLocaleTimeString('es-MX')}`;
        } catch (error) {
            alertMessage('danger', error.message);
        }
    }

    async function showDetail(id) {
        el('detailSummary').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cargando...';
        el('detailError').classList.add('d-none');
        el('responseBody').textContent = '';
        detailModal.show();
        try {
            const data = await request(endpoints.show.replace(':id', encodeURIComponent(id)));
            const command = data.command;
            el('detailSummary').innerHTML = `<div class="row g-2">
                <div class="col-md-4"><strong>Cliente:</strong> ${escapeHtml(command.CustomerName)}</div>
                <div class="col-md-4"><strong>Accion:</strong> ${escapeHtml(command.Action)}</div>
                <div class="col-md-4"><strong>Estado:</strong> <span class="badge bg-${badge(command.Status)}">${escapeHtml(command.Status)}</span></div>
                <div class="col-md-8"><strong>Comando:</strong> <code>${escapeHtml(command.Action)}</code> · Terminal [${Number(command.TerminalIndex || 0)}]</div>
                <div class="col-md-4"><strong>HTTP:</strong> ${command.HttpStatus || '—'} · ${command.DurationMs !== null ? `${Number(command.DurationMs)} ms` : '—'}</div>
            </div>`;
            if (command.ErrorMessage) {
                el('detailError').textContent = `${command.ErrorCode || 'error'}: ${command.ErrorMessage}`;
                el('detailError').classList.remove('d-none');
            }
            currentBody = command.ResponseBody || '';
            el('responseBody').textContent = currentBody || 'La orden aun no tiene cuerpo de respuesta.';
        } catch (error) {
            el('detailSummary').textContent = '';
            el('detailError').textContent = error.message;
            el('detailError').classList.remove('d-none');
        }
    }

    el('commandForm').addEventListener('submit', async event => {
        event.preventDefault();
        const button = el('submitButton');
        button.disabled = true;
        try {
            const action = el('action').value;
            const parameters = {};
            if (['get_registered_members', 'get_recent_activity'].includes(action)) {
                parameters.page = Number(el('page').value);
                parameters.pageSize = Number(el('pageSize').value);
                parameters.cursor = el('cursor').value.trim() || null;
                parameters.includeTotal = true;
            }
            if (action === 'get_recent_activity') {
                parameters.from = el('activityFrom').value || null;
                parameters.to = el('activityTo').value || null;
            }
            await request(endpoints.create, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                body: JSON.stringify({
                    customerId: el('customerId').value,
                    agentId: el('agentId').value,
                    terminalIndex: Number(el('terminalIndex').value),
                    deviceId: el('deviceId').value,
                    action,
                    parameters
                })
            });
            alertMessage('success', 'Consulta creada. El cliente de escritorio la recibira en su siguiente revision.');
            el('historyCustomer').value = el('customerId').value;
            await refresh();
        } catch (error) {
            alertMessage('danger', error.message);
        } finally {
            button.disabled = false;
        }
    });

    el('action').addEventListener('change', updateActionDescription);
    el('customerId').addEventListener('change', updateTerminalOptions);
    el('agentId').addEventListener('input', updateTerminalOptions);
    el('terminalIndex').addEventListener('change', selectTerminalLabel);
    el('refreshButton').addEventListener('click', refresh);
    el('historyCustomer').addEventListener('change', refresh);
    el('copyResponse').addEventListener('click', () => navigator.clipboard?.writeText(currentBody));
    updateActionDescription();
    refresh();
    setInterval(refresh, 10000);
})();
</script>
<?php
$customScripts = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
