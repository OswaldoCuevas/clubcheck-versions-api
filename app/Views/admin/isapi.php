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
                                    <option value="<?= htmlspecialchars($action['key'], ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $action['key'] === 'network_ping' ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="actionDescription"></div>
                        </div>
                        <div id="proxyFields" class="border border-warning rounded p-3 mb-3 d-none">
                            <div class="fw-semibold text-warning-emphasis mb-2">
                                <i class="fas fa-triangle-exclamation me-1"></i>Proxy temporal de pruebas
                            </div>
                            <div class="alert alert-warning py-2 small">
                                Solo acepta rutas relativas <code>/ISAPI/...</code>. La direccion y las credenciales permanecen en el desktop.
                            </div>
                            <div class="row g-2">
                                <div class="col-4">
                                    <label for="proxyMethod" class="form-label">Metodo</label>
                                    <select class="form-select" id="proxyMethod">
                                        <option value="GET">GET</option>
                                        <option value="POST">POST</option>
                                        <option value="PUT">PUT</option>
                                        <?php if (!empty($proxyAllowDelete)): ?>
                                            <option value="DELETE">DELETE</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-8">
                                    <label for="proxyContentType" class="form-label">Content-Type</label>
                                    <select class="form-select" id="proxyContentType">
                                        <option value="application/xml">application/xml</option>
                                        <option value="text/xml">text/xml</option>
                                        <option value="application/json">application/json</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="proxyPath" class="form-label">Ruta relativa</label>
                                    <input class="form-control font-monospace" id="proxyPath" maxlength="1000"
                                           value="/ISAPI/System/status" placeholder="/ISAPI/System/status">
                                </div>
                                <div class="col-12" id="proxyBodyContainer">
                                    <label for="proxyBody" class="form-label">Body XML o JSON</label>
                                    <textarea class="form-control font-monospace" id="proxyBody" rows="9" maxlength="262144"
                                              placeholder="Pega aqui el cuerpo exacto que recibira la terminal"></textarea>
                                    <div class="form-text">En GET el body se omite automaticamente.</div>
                                </div>
                            </div>
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
                        <div id="cardReaderFields" class="border rounded p-3 mb-3 d-none">
                            <div class="fw-semibold mb-2">Lector de tarjetas</div>
                            <label for="readerNo" class="form-label">Numero de lector</label>
                            <input class="form-control" id="readerNo" type="number" value="1" min="1" max="255">
                        </div>
                        <div id="pictureFields" class="border rounded p-3 mb-3 d-none">
                            <label for="picturePath" class="form-label">Captura del evento</label>
                            <input class="form-control" id="picturePath" maxlength="1024" placeholder="/LOCALS/pic/acsLinkCap/...jpeg@WEB...">
                            <div class="form-text">Pega pictureURL del evento o su ruta de captura. La imagen se solicita a la terminal seleccionada mediante ClubCheck.</div>
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
                                <div class="col-6">
                                    <label for="activityMajor" class="form-label">Evento major</label>
                                    <input class="form-control" id="activityMajor" type="number" value="0" min="0" max="2147483647">
                                </div>
                                <div class="col-6">
                                    <label for="activityMinor" class="form-label">Evento minor</label>
                                    <input class="form-control" id="activityMinor" type="number" value="0" min="0" max="2147483647">
                                </div>
                            </div>
                            <div class="form-text">Maximo 31 dias. Vacio consulta las ultimas 24 horas. Major y minor en 0 consultan todos.</div>
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
                <div id="eventPictures" class="mb-3 d-none"></div>
                <div id="responseImage" class="text-center d-none mb-3">
                    <img id="eventPicture" class="img-fluid rounded border" style="max-height: 65vh" alt="Captura del evento de la terminal">
                    <p class="text-muted small mt-2 mb-0" id="pictureCaption"></p>
                </div>
                <div id="responseControls" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
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
    let detailCommandId = null;
    let detailPending = false;
    let detailGeneration = 0;
    let pictureObjectUrl = null;

    function clearPicture() {
        if (pictureObjectUrl) URL.revokeObjectURL(pictureObjectUrl);
        pictureObjectUrl = null;
        el('eventPicture').removeAttribute('src');
        el('responseImage').classList.add('d-none');
    }

    function normalizePicturePath(value) {
        let path = String(value || '').trim();
        if (/^https?:\/\//i.test(path)) {
            const url = new URL(path);
            if (url.username || url.password || url.search || url.hash) throw new Error('La URL de captura no debe incluir credenciales, consultas ni fragmentos.');
            path = url.pathname;
        }
        if (!/^\/LOCALS\/pic\/acsLinkCap\/[A-Za-z0-9_/-]+\.(?:jpe?g|png)(?:@WEB[A-Za-z0-9_-]+)?$/.test(path) || path.includes('//') || path.length > 1024) {
            throw new Error('Usa una ruta de captura valida de /LOCALS/pic/acsLinkCap/ conservando el sufijo @WEB.');
        }
        return path;
    }

    function renderPicture(command) {
        if (command.Status !== 'Completed') {
            el('responseBody').textContent = ['Pending', 'Processing'].includes(command.Status)
                ? 'Esperando la captura. ClubCheck debe estar conectado; este detalle se actualiza automaticamente.'
                : 'No hay una imagen disponible. Consulta el estado y el mensaje de la solicitud.';
            return;
        }
        let metadata = command.ResponseMetadata || {};
        if (typeof metadata === 'string') metadata = JSON.parse(metadata);
        const mime = command.ResponseContentType;
        const base64 = command.ResponseBody || '';
        if (metadata.bodyEncoding !== 'base64' || !['image/jpeg', 'image/png'].includes(mime) ||
            !base64.length || base64.length > 2097152 || base64.length % 4 !== 0 || !/^[A-Za-z0-9+/]+={0,2}$/.test(base64)) {
            throw new Error('La respuesta no contiene una imagen JPEG o PNG valida. Actualiza ClubCheck si es necesario.');
        }
        const binary = atob(base64);
        const bytes = Uint8Array.from(binary, character => character.charCodeAt(0));
        const pngSignature = [137, 80, 78, 71, 13, 10, 26, 10];
        const validSignature = mime === 'image/jpeg'
            ? bytes[0] === 255 && bytes[1] === 216 && bytes[2] === 255
            : pngSignature.every((byte, index) => bytes[index] === byte);
        if (!validSignature || bytes.length > 1572864) throw new Error('El contenido recibido no coincide con el formato de imagen indicado.');
        pictureObjectUrl = URL.createObjectURL(new Blob([bytes], {type: mime}));
        el('eventPicture').src = pictureObjectUrl;
        el('pictureCaption').textContent = `Captura de la terminal [${Number(command.TerminalIndex || 0)}] · ${Math.ceil(bytes.length / 1024)} KB`;
        el('responseImage').classList.remove('d-none');
        el('responseBody').classList.add('d-none');
    }

    function renderEventPictures(command) {
        const container = el('eventPictures');
        container.replaceChildren();
        container.classList.add('d-none');
        if (command.Action !== 'get_recent_activity' || command.Status !== 'Completed') return;
        let data;
        try { data = JSON.parse(command.ResponseBody || ''); } catch (_) { return; }
        const events = data?.AcsEvent?.InfoList || data?.items || [];
        if (!Array.isArray(events)) return;
        const available = events.filter(event => event && typeof event.pictureURL === 'string');
        if (!available.length) return;
        const title = document.createElement('div');
        title.className = 'fw-semibold mb-2';
        title.textContent = 'Capturas de los eventos';
        container.appendChild(title);
        available.forEach(event => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-primary me-2 mb-2';
            button.textContent = `Solicitar captura · ${event.serialNo ?? 'Evento'} · ${event.name || event.time || ''}`;
            button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                    const result = await request(endpoints.create, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                        body: JSON.stringify({customerId: command.CustomerId, agentId: command.AgentId,
                            terminalIndex: Number(command.TerminalIndex || 0), deviceId: command.DeviceId,
                            action: 'get_event_picture', parameters: {picturePath: normalizePicturePath(event.pictureURL)}})
                    });
                    await refresh();
                    const id = result.command?.Id || result.command?.id;
                    if (id) await showDetail(id);
                    else {
                        button.textContent = 'Captura solicitada. Consulta el historial.';
                        alertMessage('success', 'Captura solicitada. Consulta su estado en el historial.');
                    }
                } catch (error) {
                    el('detailError').textContent = error.message;
                    el('detailError').classList.remove('d-none');
                    button.disabled = false;
                }
            });
            container.appendChild(button);
        });
        container.classList.remove('d-none');
    }

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
        el('cardReaderFields').classList.toggle('d-none', action !== 'get_card_reader_config');
        el('activityFields').classList.toggle('d-none', action !== 'get_recent_activity');
        el('pictureFields').classList.toggle('d-none', action !== 'get_event_picture');
        el('proxyFields').classList.toggle('d-none', action !== 'test_proxy_request');
        el('picturePath').required = action === 'get_event_picture';
        const pageSize = el('pageSize');
        pageSize.max = action === 'get_recent_activity' ? '30' : '100';
        if (Number(pageSize.value) > Number(pageSize.max)) pageSize.value = pageSize.max;
        updateProxyBodyVisibility();
    }

    function updateProxyBodyVisibility() {
        const isGet = el('proxyMethod').value === 'GET';
        el('proxyBody').disabled = isGet;
        el('proxyBodyContainer').classList.toggle('opacity-50', isGet);
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

    async function showDetail(id, background = false) {
        const generation = ++detailGeneration;
        detailCommandId = id;
        detailPending = false;
        const summaryElement = el('detailSummary');
        const errorElement = el('detailError');
        const bodyElement = el('responseBody');

        if (!summaryElement || !bodyElement) {
            alertMessage('danger', 'No fue posible abrir el detalle. Recarga la pagina para actualizar la vista.');
            return;
        }

        try {
            if (!background) summaryElement.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cargando...';
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.classList.add('d-none');
            }
            currentBody = '';
            currentRenderedBody = '';
            bodyElement.textContent = '';
            bodyElement.classList.remove('d-none');
            el('responseTable').classList.add('d-none');
            el('responseTable').replaceChildren();
            el('eventPictures').classList.add('d-none');
            el('eventPictures').replaceChildren();
            el('responseControls').classList.remove('d-none');
            clearPicture();
            if (!background) detailModal.show();

            const data = await request(endpoints.show.replace(':id', encodeURIComponent(id)));
            if (generation !== detailGeneration) return;
            const command = data.command;
            detailPending = ['Pending', 'Processing'].includes(command.Status);
            let requestParameters = {};
            try {
                requestParameters = typeof command.Parameters === 'string'
                    ? JSON.parse(command.Parameters)
                    : (command.Parameters || {});
            } catch (_) {
                requestParameters = {raw: command.Parameters};
            }
            summaryElement.innerHTML = `<div class="row g-2">
                <div class="col-md-4"><strong>Cliente:</strong> ${escapeHtml(command.CustomerName)}</div>
                <div class="col-md-4"><strong>Accion:</strong> ${escapeHtml(command.Action)}</div>
                <div class="col-md-4"><strong>Estado:</strong> <span class="badge bg-${badge(command.Status)}">${escapeHtml(command.Status)}</span></div>
                <div class="col-md-8"><strong>Comando:</strong> <code>${escapeHtml(command.Action)}</code> · Terminal [${Number(command.TerminalIndex || 0)}]</div>
                <div class="col-md-4"><strong>HTTP:</strong> ${command.HttpStatus || '—'} · ${command.DurationMs !== null ? `${Number(command.DurationMs)} ms` : '—'}</div>
                <div class="col-12"><details><summary class="fw-semibold">Parametros enviados</summary><pre class="bg-light border rounded p-2 mt-2 mb-0 text-break">${escapeHtml(JSON.stringify(requestParameters, null, 2))}</pre></details></div>
            </div>`;
            if (command.ErrorMessage && errorElement) {
                errorElement.textContent = `${command.ErrorCode || 'error'}: ${command.ErrorMessage}`;
                errorElement.classList.remove('d-none');
            }
            if (command.Action === 'get_event_picture') {
                el('responseControls').classList.add('d-none');
                renderPicture(command);
                return;
            }
            renderEventPictures(command);
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
            if (generation !== detailGeneration) return;
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
            if (action === 'get_event_picture') {
                parameters.picturePath = normalizePicturePath(el('picturePath').value);
            }
            if (action === 'network_ping') {
                parameters.timeoutMs = Number(el('pingTimeoutMs').value);
                parameters.attempts = Number(el('pingAttempts').value);
            }
            if (action === 'get_card_reader_config') {
                parameters.readerNo = Number(el('readerNo').value);
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
                parameters.major = Number(el('activityMajor').value);
                parameters.minor = Number(el('activityMinor').value);
            }
            if (action === 'test_proxy_request') {
                const method = el('proxyMethod').value;
                const path = el('proxyPath').value.trim();
                if (!path.startsWith('/ISAPI/')) {
                    throw new Error('La ruta debe comenzar con /ISAPI/.');
                }
                if (method !== 'GET' && !window.confirm(`Se enviara una solicitud ${method} a ${path}. ¿Deseas continuar?`)) {
                    return;
                }
                parameters.method = method;
                parameters.path = path;
                parameters.contentType = el('proxyContentType').value;
                parameters.body = method === 'GET' ? null : (el('proxyBody').value || null);
            }
            const created = await request(endpoints.create, {
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
            const createdId = created.command?.Id || created.command?.id;
            if (action === 'get_event_picture' && createdId) await showDetail(createdId);
        } catch (error) {
            alertMessage('danger', error.message);
        } finally {
            button.disabled = false;
        }
    });

    el('action').addEventListener('change', updateActionDescription);
    el('proxyMethod').addEventListener('change', updateProxyBodyVisibility);
    el('customerId').addEventListener('change', updateTerminalOptions);
    el('agentId').addEventListener('input', updateTerminalOptions);
    el('terminalIndex').addEventListener('change', selectTerminalLabel);
    el('refreshButton').addEventListener('click', refresh);
    el('historyCustomer').addEventListener('change', refresh);
    document.querySelectorAll('.response-view-button').forEach(button => {
        button.addEventListener('click', () => renderResponse(button.dataset.responseView));
    });
    el('copyResponse').addEventListener('click', () => navigator.clipboard?.writeText(currentRenderedBody));
    el('eventPicture').addEventListener('error', () => {
        clearPicture();
        el('detailError').textContent = 'No fue posible mostrar la imagen recibida.';
        el('detailError').classList.remove('d-none');
    });
    el('detailModal').addEventListener('hidden.bs.modal', () => {
        detailGeneration++;
        detailCommandId = null;
        detailPending = false;
        clearPicture();
        currentBody = '';
        currentRenderedBody = '';
    });
    setInterval(() => {
        if (detailCommandId && detailPending) showDetail(detailCommandId, true);
    }, 5000);
    updateActionDescription();
    refresh();
    setInterval(refresh, 10000);
})();
</script>
<?php
$customScripts = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>

