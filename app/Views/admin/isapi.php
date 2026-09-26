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
#responseTable { max-height: 520px; overflow: auto; }
#responseTable thead th { position: sticky; top: 0; z-index: 1; }
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
                        <div id="pingFields" class="border rounded p-3 mb-3 d-none">
                            <div class="fw-semibold mb-2">Opciones del ping</div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="pingTimeoutMs" class="form-label">Timeout (ms)</label>
                                    <input class="form-control" id="pingTimeoutMs" type="number" value="2000" min="250" max="10000">
                                </div>
                                <div class="col-6">
                                    <label for="pingAttempts" class="form-label">Intentos</label>
                                    <input class="form-control" id="pingAttempts" type="number" value="2" min="1" max="5">
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
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <strong id="responseViewTitle">Respuesta</strong>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Formato de respuesta">
                            <button class="btn btn-outline-primary response-view-button" data-response-view="json" type="button">JSON</button>
                            <button class="btn btn-outline-primary response-view-button" data-response-view="xml" type="button">XML</button>
                            <button class="btn btn-outline-primary response-view-button" data-response-view="table" type="button">Tabla</button>
                            <button class="btn btn-outline-primary response-view-button" data-response-view="raw" type="button">Original</button>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" id="copyResponse" type="button">
                            <i class="fas fa-copy me-1"></i>Copiar
                        </button>
                    </div>
                </div>
                <pre class="isapi-code mb-0" id="responseBody">Sin respuesta.</pre>
                <div class="table-responsive border rounded d-none" id="responseTable"></div>
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
    let currentRenderedBody = '';
    let currentResponseView = 'raw';
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

    function parseXml(value) {
        const documentXml = new DOMParser().parseFromString(value, 'application/xml');
        if (documentXml.querySelector('parsererror')) {
            throw new Error('La respuesta no contiene XML valido.');
        }
        return documentXml;
    }

    function xmlNodeToValue(node) {
        const result = {};
        Array.from(node.attributes || []).forEach(attribute => {
            result[`@${attribute.name}`] = attribute.value;
        });

        const childElements = Array.from(node.children || []);
        if (!childElements.length) {
            const textValue = (node.textContent || '').trim();
            if (!Object.keys(result).length) return textValue;
            if (textValue !== '') result['#text'] = textValue;
            return result;
        }

        childElements.forEach(child => {
            const name = child.localName || child.nodeName;
            const value = xmlNodeToValue(child);
            if (Object.prototype.hasOwnProperty.call(result, name)) {
                if (!Array.isArray(result[name])) result[name] = [result[name]];
                result[name].push(value);
            } else {
                result[name] = value;
            }
        });
        return result;
    }

    function xmlToPrettyJson(value) {
        const documentXml = parseXml(value);
        const root = documentXml.documentElement;
        return JSON.stringify({[root.localName || root.nodeName]: xmlNodeToValue(root)}, null, 2);
    }

    function xmlEscape(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&apos;');
    }

    function xmlTag(value) {
        const normalized = String(value).replace(/[^A-Za-z0-9_.-]/g, '_');
        return /^[A-Za-z_]/.test(normalized) ? normalized : `field_${normalized}`;
    }

    function jsonValueToXml(value, name, depth = 0) {
        const indent = '  '.repeat(depth);
        const tag = xmlTag(name);
        if (value === null || value === undefined) return `${indent}<${tag} xsi:nil="true" />`;
        if (Array.isArray(value)) {
            if (!value.length) return `${indent}<${tag} />`;
            return `${indent}<${tag}>\n${value.map(item => jsonValueToXml(item, 'item', depth + 1)).join('\n')}\n${indent}</${tag}>`;
        }
        if (typeof value === 'object') {
            const entries = Object.entries(value);
            if (!entries.length) return `${indent}<${tag} />`;
            return `${indent}<${tag}>\n${entries.map(([key, item]) => jsonValueToXml(item, key, depth + 1)).join('\n')}\n${indent}</${tag}>`;
        }
        return `${indent}<${tag}>${xmlEscape(value)}</${tag}>`;
    }

    function jsonToPrettyXml(value) {
        const parsed = JSON.parse(value);
        return `<?xml version="1.0" encoding="UTF-8"?>\n` +
            `<response xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">\n` +
            `${jsonValueToXml(parsed, 'data', 1)}\n</response>`;
    }

    function prettyXml(value) {
        const documentXml = parseXml(value);
        const serialize = (node, depth = 0) => {
            const indent = '  '.repeat(depth);
            if (node.nodeType === Node.TEXT_NODE) {
                const textValue = node.nodeValue.trim();
                return textValue ? indent + xmlEscape(textValue) : '';
            }
            if (node.nodeType !== Node.ELEMENT_NODE) return '';
            const name = node.nodeName;
            const attributes = Array.from(node.attributes)
                .map(attribute => ` ${attribute.name}="${xmlEscape(attribute.value)}"`)
                .join('');
            const children = Array.from(node.childNodes).filter(child =>
                child.nodeType === Node.ELEMENT_NODE ||
                (child.nodeType === Node.TEXT_NODE && child.nodeValue.trim() !== '')
            );
            if (!children.length) return `${indent}<${name}${attributes} />`;
            const onlyText = children.every(child => child.nodeType === Node.TEXT_NODE);
            if (onlyText) {
                return `${indent}<${name}${attributes}>${xmlEscape(children.map(child => child.nodeValue).join('').trim())}</${name}>`;
            }
            const content = children.map(child => serialize(child, depth + 1)).filter(Boolean).join('\n');
            return `${indent}<${name}${attributes}>\n${content}\n${indent}</${name}>`;
        };
        return `<?xml version="1.0" encoding="UTF-8"?>\n${serialize(documentXml.documentElement)}`;
    }

    function responseObject() {
        try {
            return JSON.parse(currentBody);
        } catch (_) {
            return JSON.parse(xmlToPrettyJson(currentBody));
        }
    }

    function displayValue(value) {
        if (value === null) return 'null';
        if (value === undefined) return '';
        if (typeof value === 'object') return JSON.stringify(value);
        return String(value);
    }

    function flattenRows(value, path = '', rows = []) {
        if (value === null || typeof value !== 'object') {
            rows.push([path || 'valor', displayValue(value)]);
            return rows;
        }
        if (Array.isArray(value)) {
            if (!value.length) rows.push([path || 'items', '[]']);
            value.forEach((item, index) => flattenRows(item, `${path || 'items'}[${index}]`, rows));
            return rows;
        }
        const entries = Object.entries(value);
        if (!entries.length) rows.push([path || 'objeto', '{}']);
        entries.forEach(([key, item]) => flattenRows(item, path ? `${path}.${key}` : key, rows));
        return rows;
    }

    function appendCell(row, value, header = false) {
        const cell = document.createElement(header ? 'th' : 'td');
        cell.textContent = displayValue(value);
        if (!header) cell.className = 'text-break';
        row.appendChild(cell);
    }

    function createTable(headers, rows) {
        const table = document.createElement('table');
        table.className = 'table table-striped table-hover table-sm align-middle mb-0';
        const head = document.createElement('thead');
        head.className = 'table-light';
        const headRow = document.createElement('tr');
        headers.forEach(header => appendCell(headRow, header, true));
        head.appendChild(headRow);
        table.appendChild(head);
        const body = document.createElement('tbody');
        rows.forEach(values => {
            const row = document.createElement('tr');
            values.forEach(value => appendCell(row, value));
            body.appendChild(row);
        });
        table.appendChild(body);
        return table;
    }

    function renderTableResponse() {
        const container = el('responseTable');
        if (!container) return;
        container.replaceChildren();

        let data;
        try {
            data = responseObject();
        } catch (_) {
            data = {respuesta: currentBody};
        }

        const itemList = Array.isArray(data)
            ? data
            : (data && Array.isArray(data.items) ? data.items : null);
        const objectItems = itemList && itemList.every(item => item && typeof item === 'object' && !Array.isArray(item));

        if (objectItems) {
            if (!itemList.length) {
                container.appendChild(createTable(['Resultado'], [['Sin registros']]));
                currentRenderedBody = 'Resultado\nSin registros';
                return;
            }
            const columns = [...new Set(itemList.flatMap(item => Object.keys(item)))];
            const rows = itemList.map(item => columns.map(column => displayValue(item[column])));
            container.appendChild(createTable(columns, rows));
            currentRenderedBody = [columns, ...rows]
                .map(row => row.map(value => String(value).replaceAll('\t', ' ')).join('\t'))
                .join('\n');

            if (!Array.isArray(data)) {
                const summary = Object.fromEntries(Object.entries(data).filter(([key]) => key !== 'items'));
                const summaryRows = Object.keys(summary).length ? flattenRows(summary) : [];
                if (summaryRows.length) {
                    const title = document.createElement('div');
                    title.className = 'fw-semibold p-2 border-top bg-light';
                    title.textContent = 'Paginacion y metadatos';
                    container.appendChild(title);
                    container.appendChild(createTable(['Campo', 'Valor'], summaryRows));
                    currentRenderedBody += '\n\nCampo\tValor\n' + summaryRows.map(row => row.join('\t')).join('\n');
                }
            }
            return;
        }

        const rows = flattenRows(data);
        container.appendChild(createTable(['Campo', 'Valor'], rows));
        currentRenderedBody = 'Campo\tValor\n' + rows.map(row => row.join('\t')).join('\n');
    }

    function renderResponse(view) {
        const bodyElement = el('responseBody');
        const tableElement = el('responseTable');
        if (!bodyElement) return;
        currentResponseView = view;
        bodyElement.classList.toggle('d-none', view === 'table');
        if (tableElement) tableElement.classList.toggle('d-none', view !== 'table');
        try {
            if (!currentBody) {
                currentRenderedBody = 'La orden aun no tiene cuerpo de respuesta.';
                if (view === 'table' && tableElement) {
                    tableElement.replaceChildren(createTable(['Resultado'], [[currentRenderedBody]]));
                }
            } else if (view === 'table') {
                renderTableResponse();
            } else if (view === 'json') {
                try {
                    currentRenderedBody = JSON.stringify(JSON.parse(currentBody), null, 2);
                } catch (_) {
                    currentRenderedBody = xmlToPrettyJson(currentBody);
                }
            } else if (view === 'xml') {
                try {
                    currentRenderedBody = prettyXml(currentBody);
                } catch (_) {
                    currentRenderedBody = jsonToPrettyXml(currentBody);
                }
            } else {
                currentRenderedBody = currentBody;
            }
        } catch (error) {
            currentRenderedBody = `No fue posible convertir la respuesta a ${view.toUpperCase()}.\n\n${currentBody}`;
            if (view === 'table' && tableElement) {
                tableElement.replaceChildren(createTable(['Error'], [[currentRenderedBody]]));
            }
        }
        if (view !== 'table') bodyElement.textContent = currentRenderedBody;
        const title = el('responseViewTitle');
        if (title) title.textContent = `Respuesta · ${view === 'raw' ? 'Original' : view.toUpperCase()}`;
        document.querySelectorAll('.response-view-button').forEach(button => {
            const active = button.dataset.responseView === view;
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-outline-primary', !active);
        });
    }

    function alertMessage(type, message) {
        el('alertContainer').innerHTML = `<div class="alert alert-${type} alert-dismissible fade show">
            ${escapeHtml(message)}<button class="btn-close" data-bs-dismiss="alert"></button></div>`;
    }

    function updateActionDescription() {
        const action = el('action').value;
        el('actionDescription').textContent = actionMap[action]?.description || '';
        const paginated = ['get_registered_members', 'get_recent_activity'].includes(action);
        el('paginationFields').classList.toggle('d-none', !paginated);
        el('pingFields').classList.toggle('d-none', action !== 'network_ping');
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
        const summaryElement = el('detailSummary');
        const errorElement = el('detailError');
        const bodyElement = el('responseBody');

        if (!summaryElement || !bodyElement) {
            alertMessage('danger', 'No fue posible abrir el detalle. Recarga la pagina para actualizar la vista.');
            return;
        }

        try {
            summaryElement.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cargando...';
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.classList.add('d-none');
            }
            bodyElement.textContent = '';
            detailModal.show();

            const data = await request(endpoints.show.replace(':id', encodeURIComponent(id)));
            const command = data.command;
            summaryElement.innerHTML = `<div class="row g-2">
                <div class="col-md-4"><strong>Cliente:</strong> ${escapeHtml(command.CustomerName)}</div>
                <div class="col-md-4"><strong>Accion:</strong> ${escapeHtml(command.Action)}</div>
                <div class="col-md-4"><strong>Estado:</strong> <span class="badge bg-${badge(command.Status)}">${escapeHtml(command.Status)}</span></div>
                <div class="col-md-8"><strong>Comando:</strong> <code>${escapeHtml(command.Action)}</code> · Terminal [${Number(command.TerminalIndex || 0)}]</div>
                <div class="col-md-4"><strong>HTTP:</strong> ${command.HttpStatus || '—'} · ${command.DurationMs !== null ? `${Number(command.DurationMs)} ms` : '—'}</div>
            </div>`;
            if (command.ErrorMessage && errorElement) {
                errorElement.textContent = `${command.ErrorCode || 'error'}: ${command.ErrorMessage}`;
                errorElement.classList.remove('d-none');
            }
            currentBody = command.ResponseBody || '';
            let defaultView = 'raw';
            if (currentBody) {
                try {
                    JSON.parse(currentBody);
                    defaultView = 'json';
                } catch (_) {
                    try {
                        parseXml(currentBody);
                        defaultView = 'xml';
                    } catch (_) {
                        defaultView = 'raw';
                    }
                }
            }
            renderResponse(defaultView);
        } catch (error) {
            summaryElement.textContent = '';
            if (errorElement) {
                errorElement.textContent = error.message;
                errorElement.classList.remove('d-none');
            } else {
                bodyElement.textContent = `Error: ${error.message}`;
            }
        }
    }

    el('commandForm').addEventListener('submit', async event => {
        event.preventDefault();
        const button = el('submitButton');
        button.disabled = true;
        try {
            const action = el('action').value;
            const parameters = {};
            if (action === 'network_ping') {
                parameters.timeoutMs = Number(el('pingTimeoutMs').value);
                parameters.attempts = Number(el('pingAttempts').value);
            }
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
    document.querySelectorAll('.response-view-button').forEach(button => {
        button.addEventListener('click', () => renderResponse(button.dataset.responseView));
    });
    el('copyResponse').addEventListener('click', () => navigator.clipboard?.writeText(currentRenderedBody));
    updateActionDescription();
    refresh();
    setInterval(refresh, 10000);
})();
</script>
<?php
$customScripts = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
