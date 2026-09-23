<?php
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$selectedCustomerId = '';
foreach ($customers as $customer) {
    if ((int) $customer['DeviceCount'] > 0) {
        $selectedCustomerId = (string) $customer['Id'];
        break;
    }
}
ob_start();
?>

<div class="container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-bell text-primary me-2"></i>Notificaciones push</h1>
            <p class="text-muted mb-0">Envía una prueba a los navegadores registrados de un cliente.</p>
        </div>
        <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Volver al panel</a>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header"><h2 class="h5 mb-0">Preparar envío</h2></div>
                <div class="card-body">
                    <form id="pushForm" class="row g-3">
                        <div class="col-12">
                            <label for="customerId" class="form-label fw-semibold">Cliente receptor</label>
                            <select id="customerId" class="form-select" required>
                                <option value="">Selecciona un cliente</option>
                                <?php foreach ($customers as $customer): ?>
                                    <?php $id = (string) $customer['Id']; $count = (int) $customer['DeviceCount']; ?>
                                    <option value="<?= $escape($id) ?>" data-devices="<?= $count ?>" <?= $id === $selectedCustomerId ? 'selected' : '' ?>>
                                        <?= $escape($customer['Name']) ?> · <?= $count ?> <?= $count === 1 ? 'dispositivo' : 'dispositivos' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="deviceHint" class="form-text"></div>
                        </div>

                        <div class="col-12">
                            <label for="pushTitle" class="form-label fw-semibold">Título</label>
                            <input id="pushTitle" class="form-control" maxlength="150" value="Prueba de ClubCheck" required>
                        </div>
                        <div class="col-12">
                            <label for="pushBody" class="form-label fw-semibold">Mensaje</label>
                            <textarea id="pushBody" class="form-control" rows="3" maxlength="1000" required>Las notificaciones push ya están conectadas.</textarea>
                            <div class="form-text">Escribe un aviso breve. El navegador puede recortar los textos largos.</div>
                        </div>

                        <div class="col-12"><hr class="my-1"><h3 class="h6 text-muted mb-0">Presentación opcional</h3></div>
                        <div class="col-12">
                            <label for="iconUrl" class="form-label">Icono HTTPS</label>
                            <input id="iconUrl" type="url" class="form-control" maxlength="2048" placeholder="https://tu-dominio.com/icon-192.png">
                        </div>
                        <div class="col-12">
                            <label for="imageUrl" class="form-label">Imagen HTTPS</label>
                            <input id="imageUrl" type="url" class="form-control" maxlength="2048" placeholder="https://tu-dominio.com/imagen.jpg">
                        </div>
                        <div class="col-12">
                            <label for="link" class="form-label">Página al hacer clic (HTTPS)</label>
                            <input id="link" type="url" class="form-control" maxlength="2048" placeholder="https://tu-dominio.com/avisos">
                        </div>

                        <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3 pt-2">
                            <small class="text-muted">El envío llega a todos los dispositivos registrados del cliente seleccionado.</small>
                            <button id="sendButton" class="btn btn-primary" type="submit"><i class="fas fa-paper-plane me-2"></i>Enviar prueba</button>
                        </div>
                    </form>
                    <div id="sendResult" class="mt-3" role="status" aria-live="polite"></div>
                    <details id="firebaseDetails" class="mt-3 d-none">
                        <summary>Respuesta de Firebase</summary>
                        <pre id="firebaseResponse" class="bg-light border rounded p-3 mt-2 small text-break" style="white-space: pre-wrap;"></pre>
                    </details>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header"><h2 class="h5 mb-0">Vista previa</h2></div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Es una referencia. El navegador y el sistema operativo deciden el aspecto final.</p>
                    <div class="push-preview" aria-label="Vista previa de la notificación">
                        <div class="d-flex align-items-start gap-3">
                            <div id="previewIconFallback" class="push-preview-icon"><i class="fas fa-bell"></i></div>
                            <img id="previewIcon" class="push-preview-icon d-none" alt="Icono de la notificación">
                            <div class="flex-grow-1 min-width-0">
                                <div class="small text-muted mb-1">ClubCheck · ahora</div>
                                <div id="previewTitle" class="fw-semibold text-break"></div>
                                <div id="previewBody" class="text-secondary small text-break mt-1"></div>
                            </div>
                        </div>
                        <img id="previewImage" class="push-preview-image d-none" alt="Imagen de la notificación">
                    </div>
                    <div id="previewLink" class="small text-muted mt-3"></div>
                    <div class="alert alert-info mt-4 mb-0 small">
                        Para ver la notificación del sistema durante la prueba, deja el cliente web en segundo plano y confirma que tenga permiso para mostrar avisos. Si está en primer plano, debe mostrarla mediante <code>onMessage</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$customStyles = <<<CSS
.push-preview { max-width: 430px; padding: 1rem; border: 1px solid #d8e0ea; border-radius: 14px; background: #f8fafc; box-shadow: 0 14px 30px rgba(30, 50, 80, .10); }
.push-preview-icon { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 10px; background: #e6efff; color: #2865b5; display: grid; place-items: center; object-fit: cover; }
.push-preview-image { width: 100%; max-height: 190px; object-fit: cover; border-radius: 10px; margin-top: 1rem; }
.min-width-0 { min-width: 0; }
CSS;
$sendUrl = json_encode(app_url('/admin/api/push/send'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$customScripts = <<<'JS'
<script>
(() => {
    const form = document.getElementById('pushForm');
    const customer = document.getElementById('customerId');
    const sendButton = document.getElementById('sendButton');
    const result = document.getElementById('sendResult');
    const firebaseDetails = document.getElementById('firebaseDetails');
    const firebaseResponse = document.getElementById('firebaseResponse');
    const field = id => document.getElementById(id).value.trim();
    const httpsUrl = value => { try { return new URL(value).protocol === 'https:'; } catch { return false; } };

    function updatePreview() {
        const count = Number(customer.selectedOptions[0]?.dataset.devices || 0);
        document.getElementById('deviceHint').textContent = count > 0
            ? `Se enviará a ${count} ${count === 1 ? 'dispositivo registrado' : 'dispositivos registrados'}.`
            : 'Este cliente todavía no tiene dispositivos registrados.';
        sendButton.disabled = count === 0;
        document.getElementById('previewTitle').textContent = field('pushTitle') || 'Título de la notificación';
        document.getElementById('previewBody').textContent = field('pushBody') || 'Aquí aparecerá el mensaje.';
        for (const [inputId, imageId] of [['iconUrl', 'previewIcon'], ['imageUrl', 'previewImage']]) {
            const image = document.getElementById(imageId);
            const value = field(inputId);
            image.classList.toggle('d-none', !httpsUrl(value));
            if (httpsUrl(value)) image.src = value;
            else image.removeAttribute('src');
        }
        document.getElementById('previewIconFallback').classList.toggle('d-none', httpsUrl(field('iconUrl')));
        document.getElementById('previewLink').textContent = httpsUrl(field('link')) ? `Al hacer clic: ${field('link')}` : '';
    }

    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const count = Number(customer.selectedOptions[0]?.dataset.devices || 0);
        if (!form.reportValidity() || count === 0) return;
        const payload = {
            customerId: customer.value,
            title: field('pushTitle'),
            body: field('pushBody'),
        };
        for (const key of ['iconUrl', 'imageUrl', 'link']) {
            if (field(key)) payload[key] = field(key);
        }
        sendButton.disabled = true;
        result.className = 'alert alert-info mt-3';
        result.textContent = 'Enviando notificación…';
        firebaseDetails.classList.add('d-none');
        try {
            const response = await fetch(__SEND_URL__, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (Array.isArray(data.firebaseResponses) && data.firebaseResponses.length > 0) {
                firebaseResponse.textContent = JSON.stringify(data.firebaseResponses, null, 2);
                firebaseDetails.classList.remove('d-none');
            }
            if (!response.ok || data.failed) {
                result.className = 'alert alert-danger mt-3';
                result.textContent = data.error || `Enviados: ${data.sent || 0}. Fallidos: ${data.failed || 0}. ${Object.keys(data.errors || {}).join(', ')}`;
            } else {
                result.className = 'alert alert-success mt-3';
                result.textContent = data.sent > 0
                    ? `Firebase aceptó ${data.sent} ${data.sent === 1 ? 'mensaje' : 'mensajes'}. Comprueba el navegador receptor.`
                    : (data.message || 'No se enviaron mensajes.');
            }
        } catch {
            result.className = 'alert alert-danger mt-3';
            result.textContent = 'No se pudo completar la solicitud. Revisa tu sesión y vuelve a intentar.';
        } finally {
            updatePreview();
        }
    });
    updatePreview();
})();
</script>
JS;
$customScripts = str_replace('__SEND_URL__', $sendUrl, $customScripts);
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
