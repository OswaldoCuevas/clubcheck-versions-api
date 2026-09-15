<?php
$title = 'Anuncios';
ob_start();
?>

<div class="container mt-4">
    <div id="alertBox"></div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Anuncios guardados</h5>
                </div>
                <div class="list-group list-group-flush" id="announcementList"></div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Constructor visual</h5>
                    <button type="button" class="btn btn-light btn-sm" id="newBtn">
                        <i class="fas fa-plus me-1"></i>Nuevo
                    </button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">ID</label>
                            <input class="form-control" id="annId" placeholder="clubcheck-update-2026-09">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Version anuncio</label>
                            <input class="form-control" id="annVersion" placeholder="2026.09">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Version minima desktop</label>
                            <input class="form-control" id="minVersion" placeholder="6.93.0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Titulo</label>
                            <input class="form-control" id="annTitle" placeholder="Actualizacion importante">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subtitulo</label>
                            <input class="form-control" id="annSubtitle" placeholder="Novedades disponibles para mejorar la operacion diaria.">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="isActive">
                                <label class="form-check-label" for="isActive">Activar este anuncio al guardar</label>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-uppercase text-muted fw-semibold mb-0">Secciones</h6>
                        <button type="button" class="btn btn-primary btn-sm" id="addSlideBtn">
                            <i class="fas fa-layer-group me-1"></i>Agregar seccion
                        </button>
                    </div>
                    <div id="slides"></div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-outline-secondary" id="previewBtn">
                            <i class="fas fa-eye me-1"></i>Previsualizar
                        </button>
                        <button type="button" class="btn btn-primary" id="saveBtn">
                            <i class="fas fa-save me-1"></i>Guardar anuncio
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content announcement-preview">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <span class="preview-icon me-3"><i class="fas fa-info"></i></span>
                    <div>
                        <h3 class="modal-title" id="previewTitle"></h3>
                        <div class="text-muted" id="previewSubtitle"></div>
                    </div>
                </div>
                <span class="text-muted fw-semibold" id="previewCounter"></span>
            </div>
            <div class="modal-body">
                <div class="row g-4 align-items-center">
                    <div class="col-md-5">
                        <div class="preview-image" id="previewImage"></div>
                    </div>
                    <div class="col-md-7">
                        <h2 id="previewSlideTitle"></h2>
                        <p class="lead" id="previewSlideText"></p>
                        <div id="previewDots"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" id="prevPreview"><i class="fas fa-chevron-left"></i></button>
                <button class="btn btn-outline-secondary" id="nextPreview"><i class="fas fa-chevron-right"></i></button>
                <button class="btn btn-primary" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="viewsTitle">Socios que vieron el anuncio</h5>
                    <div class="text-muted small" id="viewsSubtitle"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Email</th>
                                <th>AccessCode</th>
                                <th>Version cliente</th>
                                <th>Visto</th>
                                <th>IP</th>
                                <th>User agent</th>
                            </tr>
                        </thead>
                        <tbody id="viewsTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
$customStyles = <<<CSS
.slide-card { border: 1px solid #dee2e6; border-radius: 6px; padding: 1rem; margin-bottom: 1rem; background: #fff; }
.drop-zone { border: 2px dashed #b8c2d3; border-radius: 6px; min-height: 180px; display: flex; align-items: center; justify-content: center; text-align: center; color: #667085; background: #f8fafc; cursor: pointer; overflow: hidden; }
.drop-zone.dragover { border-color: #5b2be0; background: #f3f0ff; }
.drop-zone img { width: 100%; height: 180px; object-fit: contain; }
.announcement-preview { border-radius: 8px; overflow: hidden; }
.preview-icon { width: 64px; height: 64px; display: inline-flex; align-items: center; justify-content: center; border-radius: 16px; background: #5b2be0; color: #fff; font-size: 1.7rem; }
.preview-image { min-height: 420px; border: 1px solid #e3e8f0; border-radius: 18px; background: #f8fafc; display: flex; align-items: center; justify-content: center; color: #667085; overflow: hidden; }
.preview-image img { width: 100%; height: 420px; object-fit: contain; }
.dot { display: inline-block; width: 10px; height: 10px; border-radius: 999px; background: #cbd5e1; margin-right: 10px; }
.dot.active { width: 28px; background: #5b2be0; }
CSS;

$customScripts = <<<'JS'
<script>
const state = { announcements: [], slides: [], previewIndex: 0 };
const api = path => `${window.location.origin}${window.location.pathname.replace(/\/admin\/announcements$/, '')}${path}`;

function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

function alertMsg(message, type = 'success') {
    document.getElementById('alertBox').innerHTML = `<div class="alert alert-${type}">${esc(message)}</div>`;
}

async function loadAnnouncements() {
    const res = await fetch(api('/admin/api/announcements'));
    const data = await res.json();
    state.announcements = data.announcements || [];
    renderList();
}

function renderList() {
    const list = document.getElementById('announcementList');
    if (!state.announcements.length) {
        list.innerHTML = '<div class="p-3 text-muted">Sin anuncios guardados.</div>';
        return;
    }
    list.innerHTML = state.announcements.map(a => `
        <div class="list-group-item">
            <div class="d-flex justify-content-between">
                <strong>${esc(a.title)}</strong>
                ${a.isActive ? '<span class="badge bg-success">Activo</span>' : ''}
            </div>
            <div class="small text-muted">${esc(a.id)} · v${esc(a.version)} · ${a.viewsCount} vistas</div>
            <div class="btn-group btn-group-sm mt-2">
                <button class="btn btn-outline-primary" onclick="editAnnouncement('${esc(a.id)}')"><i class="fas fa-pen"></i></button>
                <button class="btn btn-outline-info" onclick="showViews('${esc(a.id)}')"><i class="fas fa-users"></i></button>
                <button class="btn btn-outline-success" onclick="activateAnnouncement('${esc(a.id)}')"><i class="fas fa-toggle-on"></i></button>
                <button class="btn btn-outline-danger" onclick="deleteAnnouncement('${esc(a.id)}')"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `).join('');
}

function blankForm() {
    document.getElementById('annId').value = '';
    document.getElementById('annVersion').value = '';
    document.getElementById('minVersion').value = '';
    document.getElementById('annTitle').value = '';
    document.getElementById('annSubtitle').value = '';
    document.getElementById('isActive').checked = false;
    state.slides = [{ title: '', text: '', imageUrl: '', imageAlt: '' }];
    renderSlides();
}

function editAnnouncement(id) {
    const a = state.announcements.find(item => item.id === id);
    if (!a) return;
    document.getElementById('annId').value = a.id;
    document.getElementById('annVersion').value = a.version;
    document.getElementById('minVersion').value = a.minClientVersion || '';
    document.getElementById('annTitle').value = a.title;
    document.getElementById('annSubtitle').value = a.subtitle || '';
    document.getElementById('isActive').checked = !!a.isActive;
    state.slides = JSON.parse(JSON.stringify(a.slides || []));
    renderSlides();
}

function renderSlides() {
    document.getElementById('slides').innerHTML = state.slides.map((s, i) => `
        <div class="slide-card">
            <div class="d-flex justify-content-between mb-3">
                <strong>Seccion ${i + 1}</strong>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="moveSlide(${i}, -1)"><i class="fas fa-arrow-up"></i></button>
                    <button class="btn btn-outline-secondary" onclick="moveSlide(${i}, 1)"><i class="fas fa-arrow-down"></i></button>
                    <button class="btn btn-outline-danger" onclick="removeSlide(${i})"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="drop-zone" data-index="${i}" onclick="pickImage(${i})">
                        ${s.imageUrl ? `<img src="${esc(s.imageUrl)}" alt="">` : '<div><i class="fas fa-image fa-2x mb-2"></i><br>Arrastra una imagen</div>'}
                    </div>
                    <input type="file" accept="image/*" class="d-none image-input" data-index="${i}">
                </div>
                <div class="col-md-7">
                    <input class="form-control mb-2" placeholder="Titulo de la seccion" value="${esc(s.title)}" oninput="state.slides[${i}].title=this.value">
                    <textarea class="form-control mb-2" rows="5" placeholder="Texto del anuncio" oninput="state.slides[${i}].text=this.value">${esc(s.text)}</textarea>
                    <input class="form-control" placeholder="Descripcion de la imagen" value="${esc(s.imageAlt)}" oninput="state.slides[${i}].imageAlt=this.value">
                </div>
            </div>
        </div>
    `).join('');
    bindDropZones();
}

function bindDropZones() {
    document.querySelectorAll('.image-input').forEach(input => input.onchange = () => uploadImage(input.dataset.index, input.files[0]));
    document.querySelectorAll('.drop-zone').forEach(zone => {
        zone.ondragover = e => { e.preventDefault(); zone.classList.add('dragover'); };
        zone.ondragleave = () => zone.classList.remove('dragover');
        zone.ondrop = e => {
            e.preventDefault();
            zone.classList.remove('dragover');
            uploadImage(zone.dataset.index, e.dataTransfer.files[0]);
        };
    });
}

function pickImage(index) {
    document.querySelector(`.image-input[data-index="${index}"]`).click();
}

async function uploadImage(index, file) {
    if (!file) return;
    const form = new FormData();
    form.append('image', file);
    const res = await fetch(api('/admin/api/announcements/upload-image'), { method: 'POST', body: form });
    const data = await res.json();
    if (!res.ok) return alertMsg(data.error || 'No se pudo subir la imagen', 'danger');
    state.slides[index].imageUrl = data.imageUrl;
    if (!state.slides[index].imageAlt) state.slides[index].imageAlt = file.name;
    renderSlides();
}

function moveSlide(index, dir) {
    const next = index + dir;
    if (next < 0 || next >= state.slides.length) return;
    [state.slides[index], state.slides[next]] = [state.slides[next], state.slides[index]];
    renderSlides();
}

function removeSlide(index) {
    state.slides.splice(index, 1);
    if (!state.slides.length) state.slides.push({ title: '', text: '', imageUrl: '', imageAlt: '' });
    renderSlides();
}

async function saveAnnouncement() {
    const payload = {
        id: document.getElementById('annId').value,
        version: document.getElementById('annVersion').value,
        title: document.getElementById('annTitle').value,
        subtitle: document.getElementById('annSubtitle').value,
        minClientVersion: document.getElementById('minVersion').value,
        isActive: document.getElementById('isActive').checked,
        slides: state.slides
    };
    const res = await fetch(api('/admin/api/announcements'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok) return alertMsg(data.error || 'No se pudo guardar', 'danger');
    alertMsg('Anuncio guardado correctamente.');
    await loadAnnouncements();
    editAnnouncement(data.announcement.id);
}

async function activateAnnouncement(id) {
    const res = await fetch(api(`/admin/api/announcements/${id}/activate`), { method: 'POST' });
    const data = await res.json();
    if (!res.ok) return alertMsg(data.error || 'No se pudo activar', 'danger');
    alertMsg('Anuncio activo actualizado.');
    await loadAnnouncements();
}

async function deleteAnnouncement(id) {
    if (!confirm('Eliminar este anuncio?')) return;
    const res = await fetch(api(`/admin/api/announcements/${id}/delete`), { method: 'POST' });
    const data = await res.json();
    if (!res.ok) return alertMsg(data.error || 'No se pudo eliminar', 'danger');
    alertMsg('Anuncio eliminado.');
    blankForm();
    await loadAnnouncements();
}

async function showViews(id) {
    const res = await fetch(api(`/admin/api/announcements/${id}/views`));
    const data = await res.json();
    if (!res.ok) return alertMsg(data.error || 'No se pudieron cargar las vistas', 'danger');

    document.getElementById('viewsTitle').textContent = data.announcement?.title || 'Socios que vieron el anuncio';
    document.getElementById('viewsSubtitle').textContent = `${data.count || 0} socios han visto este anuncio`;
    const rows = data.views || [];
    document.getElementById('viewsTableBody').innerHTML = rows.length ? rows.map(v => `
        <tr>
            <td>
                <strong>${esc(v.CustomerName || 'Sin nombre')}</strong>
                <div class="small text-muted">${esc(v.CustomerId)}</div>
            </td>
            <td>${esc(v.CustomerEmail || '-')}</td>
            <td><code>${esc(v.CodeAccess || '-')}</code></td>
            <td><code>${esc(v.ClientVersion || '-')}</code></td>
            <td>${esc(v.ViewedAt || '-')}</td>
            <td><code>${esc(v.IpAddress || '-')}</code></td>
            <td class="small text-muted">${esc(v.UserAgent || '-')}</td>
        </tr>
    `).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">Nadie ha visto este anuncio todavia.</td></tr>';

    new bootstrap.Modal(document.getElementById('viewsModal')).show();
}

function preview() {
    state.previewIndex = 0;
    renderPreview();
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}

function renderPreview() {
    const slides = state.slides;
    const s = slides[state.previewIndex] || {};
    document.getElementById('previewTitle').textContent = document.getElementById('annTitle').value;
    document.getElementById('previewSubtitle').textContent = document.getElementById('annSubtitle').value;
    document.getElementById('previewCounter').textContent = `${state.previewIndex + 1} de ${slides.length}`;
    document.getElementById('previewSlideTitle').textContent = s.title || '';
    document.getElementById('previewSlideText').textContent = s.text || '';
    document.getElementById('previewImage').innerHTML = s.imageUrl ? `<img src="${esc(s.imageUrl)}" alt="${esc(s.imageAlt)}">` : '<div><i class="fas fa-image fa-3x mb-3"></i><br>Sin imagen</div>';
    document.getElementById('previewDots').innerHTML = slides.map((_, i) => `<span class="dot ${i === state.previewIndex ? 'active' : ''}"></span>`).join('');
}

document.getElementById('newBtn').onclick = blankForm;
document.getElementById('addSlideBtn').onclick = () => { state.slides.push({ title: '', text: '', imageUrl: '', imageAlt: '' }); renderSlides(); };
document.getElementById('saveBtn').onclick = saveAnnouncement;
document.getElementById('previewBtn').onclick = preview;
document.getElementById('prevPreview').onclick = () => { state.previewIndex = Math.max(0, state.previewIndex - 1); renderPreview(); };
document.getElementById('nextPreview').onclick = () => { state.previewIndex = Math.min(state.slides.length - 1, state.previewIndex + 1); renderPreview(); };

blankForm();
loadAnnouncements();
</script>
JS;
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
