<?php
$title = 'Versiones';
$customStyles = '
    .file-upload-wrapper {
        position: relative;
        overflow: hidden;
        display: inline-block;
        width: 100%;
    }
    
    .file-upload-input {
        position: absolute;
        left: -9999px;
    }
    
    .file-upload-label {
        cursor: pointer;
        background: #f8f9fa;
        border: 2px dashed #adb5bd;
        border-radius: 4px;
        padding: 2rem;
        text-align: center;
        transition: all 0.15s ease-in-out;
        display: block;
    }
    
    .file-upload-label:hover {
        border-color: #3498db;
        background: #e3f2fd;
    }
    
    .version-info {
        background: #ecf0f1;
        border: 1px solid #bdc3c7;
        border-radius: 4px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .version-badge {
        background-color: #27ae60;
        color: white;
        padding: 0.4rem 0.8rem;
        border-radius: 4px;
        font-weight: 500;
        font-size: 0.9rem;
    }
    
    .replacement-notice {
        background-color: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
';

$customScripts = '
    <script>
        function updateFileName(input, displayElementId) {
            const fileName = document.getElementById(displayElementId);
            const label = input.nextElementSibling;
            
            if (input.files && input.files.length > 0) {
                fileName.textContent = input.files[0].name;
                fileName.style.display = "block";
                label.style.borderColor = "#27ae60";
                label.style.background = "#d5f4e6";
            } else {
                fileName.style.display = "none";
                label.style.borderColor = "#adb5bd";
                label.style.background = "#f8f9fa";
            }
        }
        
        // Drag and drop functionality para EXE
        const exeLabel = document.querySelector("label[for=\'exeFile\']");
        const exeInput = document.getElementById("exeFile");
        
        if (exeLabel && exeInput) {
            exeLabel.addEventListener("dragover", function(e) {
                e.preventDefault();
                this.style.borderColor = "#3498db";
                this.style.background = "#e3f2fd";
            });
            
            exeLabel.addEventListener("dragleave", function(e) {
                e.preventDefault();
                this.style.borderColor = "#adb5bd";
                this.style.background = "#f8f9fa";
            });
            
            exeLabel.addEventListener("drop", function(e) {
                e.preventDefault();
                this.style.borderColor = "#adb5bd";
                this.style.background = "#f8f9fa";
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    exeInput.files = files;
                    updateFileName(exeInput, "exeFileName");
                }
            });
        }
        
        // Drag and drop functionality para Setup ZIP
        const setupLabel = document.querySelector("label[for=\'setupFile\']");
        const setupInput = document.getElementById("setupFile");
        
        if (setupLabel && setupInput) {
            setupLabel.addEventListener("dragover", function(e) {
                e.preventDefault();
                this.style.borderColor = "#3498db";
                this.style.background = "#e3f2fd";
            });
            
            setupLabel.addEventListener("dragleave", function(e) {
                e.preventDefault();
                this.style.borderColor = "#adb5bd";
                this.style.background = "#f8f9fa";
            });
            
            setupLabel.addEventListener("drop", function(e) {
                e.preventDefault();
                this.style.borderColor = "#adb5bd";
                this.style.background = "#f8f9fa";
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    setupInput.files = files;
                    updateFileName(setupInput, "setupFileName");
                }
            });
        }
        
        // Validación del formulario antes de enviar
        const uploadForm = document.getElementById("versionUploadForm");
        if (uploadForm) {
            uploadForm.addEventListener("submit", function(e) {
                const versionInput = document.getElementById("version");
                const versionValue = versionInput ? versionInput.value.trim() : "";
                
                console.log("DEBUG - Valor de versión antes de enviar:", versionValue);
                console.log("DEBUG - Campo version:", versionInput);
                
                if (!versionValue) {
                    e.preventDefault();
                    alert("ERROR: El campo de versión está vacío. Por favor ingresa una versión.");
                    versionInput.focus();
                    return false;
                }
                
                const pattern = /^\d+\.\d+\.\d+\.\d+$/;
                if (!pattern.test(versionValue)) {
                    e.preventDefault();
                    alert("ERROR: La versión debe tener el formato X.X.X.X (ej: 1.2.3.0)\\nValor actual: " + versionValue);
                    versionInput.focus();
                    return false;
                }
                
                console.log("Formulario válido, enviando...");
            });
        }
    </script>
';

$customStyles .= <<<'CSS'
    .version-manager-wrap { max-width: 1050px; }
    .version-manager-card {
        border: 1px solid #d7eafd;
        border-radius: 18px;
        box-shadow: 0 18px 44px rgba(47, 128, 237, 0.1);
    }
    .version-manager-card .card-header {
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, #f7fbff, #eaf6ff);
        color: #15395b;
        border-bottom: 1px solid #d7eafd;
    }
    .version-manager-card .card-header h3 { font-size: 1.2rem; font-weight: 800; }
    .version-info {
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid #d7eafd;
        border-radius: 14px;
        background: #f3f9ff;
    }
    .version-badge {
        border-radius: 999px;
        background: #1299dc;
        font-weight: 800;
    }
    .replacement-notice {
        padding: 0.75rem 0.9rem;
        border: 1px solid #c8e8fb;
        border-radius: 12px;
        background: #edf8ff;
        color: #315574;
        font-size: 0.85rem;
    }
    .upload-files-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .upload-file-field { min-width: 0; display: flex; flex-direction: column; }
    .upload-file-field .file-upload-wrapper { height: auto; }
    .file-upload-label {
        min-height: 0;
        height: 185px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1.1rem;
        border: 2px dashed #a9d4f2;
        border-radius: 14px;
        background: linear-gradient(145deg, #fbfdff, #f1f8ff);
        color: #315574;
        transition: border-color 0.15s ease, background 0.15s ease, transform 0.15s ease;
    }
    .file-upload-label:hover,
    .file-upload-label.is-dragover {
        border-color: #1299dc !important;
        background: #eaf8ff !important;
        transform: translateY(-2px);
    }
    .file-upload-label.has-file {
        border-style: solid;
        border-color: #54b9ea !important;
        background: #eefaff !important;
    }
    .file-upload-icon { color: #1299dc !important; font-size: 1.65rem; }
    .file-upload-name {
        max-width: 100%;
        color: #087cba !important;
        font-size: 0.8rem;
        font-weight: 800;
        overflow-wrap: anywhere;
    }
    .upload-progress-panel {
        padding: 1rem;
        border: 1px solid #c8e8fb;
        border-radius: 14px;
        background: #f5fbff;
    }
    .upload-progress-track {
        height: 12px;
        overflow: hidden;
        border-radius: 999px;
        background: #dceefa;
    }
    .upload-progress-bar {
        width: 0;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #1299dc, #2f80ed);
        transition: width 0.2s ease;
    }
    .upload-progress-bar.processing {
        width: 100% !important;
        background-size: 200% 100%;
        animation: upload-processing 1.15s linear infinite;
    }
    @keyframes upload-processing {
        from { background-position: 100% 0; }
        to { background-position: -100% 0; }
    }
    @media (max-width: 767px) {
        .upload-files-grid { grid-template-columns: 1fr; }
        .file-upload-label { height: 165px; }
    }
CSS;

$customScripts .= <<<'JS'
    <script>
        function formatUploadBytes(bytes) {
            const size = Number(bytes || 0);
            if (size < 1024) return `${size} B`;
            const units = ['KB', 'MB', 'GB'];
            let value = size / 1024;
            let index = 0;
            while (value >= 1024 && index < units.length - 1) {
                value /= 1024;
                index += 1;
            }
            return `${value.toFixed(value >= 100 ? 0 : 1)} ${units[index]}`;
        }

        updateFileName = function(input, displayElementId) {
            const fileName = document.getElementById(displayElementId);
            const label = input.nextElementSibling;
            if (input.files && input.files.length > 0) {
                const file = input.files[0];
                fileName.textContent = `${file.name} · ${formatUploadBytes(file.size)}`;
                fileName.style.display = 'block';
                label.classList.add('has-file');
            } else {
                fileName.style.display = 'none';
                label.classList.remove('has-file');
            }
        };

        ['exeFile', 'setupFile'].forEach(inputId => {
            const input = document.getElementById(inputId);
            const label = document.querySelector(`label[for="${inputId}"]`);
            if (!input || !label) return;
            label.addEventListener('dragover', event => {
                event.preventDefault();
                label.classList.add('is-dragover');
            });
            label.addEventListener('dragleave', () => label.classList.remove('is-dragover'));
            label.addEventListener('drop', () => label.classList.remove('is-dragover'));
        });

        const versionUploadForm = document.getElementById('versionUploadForm');
        versionUploadForm?.addEventListener('submit', function(event) {
            event.preventDefault();
            if (!versionUploadForm.checkValidity()) {
                versionUploadForm.reportValidity();
                return;
            }

            const button = document.getElementById('uploadSubmitBtn');
            const panel = document.getElementById('uploadProgressPanel');
            const bar = document.getElementById('uploadProgressBar');
            const track = document.getElementById('uploadProgressTrack');
            const percent = document.getElementById('uploadProgressPercent');
            const detail = document.getElementById('uploadProgressDetail');
            const status = document.getElementById('uploadProgressStatus');
            const startedAt = Date.now();
            const request = new XMLHttpRequest();

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i>Subiendo archivos...';
            panel.classList.remove('d-none');
            bar.classList.remove('processing');
            bar.style.width = '0%';
            track.setAttribute('aria-valuenow', '0');
            percent.textContent = '0%';
            status.textContent = 'Subiendo al servidor';
            detail.textContent = 'Preparando archivos...';

            request.open('POST', versionUploadForm.action || window.location.href, true);
            request.upload.addEventListener('progress', uploadEvent => {
                if (!uploadEvent.lengthComputable) {
                    detail.textContent = `${formatUploadBytes(uploadEvent.loaded)} transferidos`;
                    return;
                }
                const value = Math.min(100, Math.round((uploadEvent.loaded / uploadEvent.total) * 100));
                const elapsed = Math.max(0.1, (Date.now() - startedAt) / 1000);
                const rate = uploadEvent.loaded / elapsed;
                const remaining = rate > 0 ? Math.ceil((uploadEvent.total - uploadEvent.loaded) / rate) : 0;
                bar.style.width = `${value}%`;
                track.setAttribute('aria-valuenow', String(value));
                percent.textContent = `${value}%`;
                detail.textContent = `${formatUploadBytes(uploadEvent.loaded)} de ${formatUploadBytes(uploadEvent.total)}${remaining > 0 ? ` · aprox. ${remaining}s restantes` : ''}`;
            });
            request.upload.addEventListener('load', () => {
                bar.style.width = '100%';
                track.setAttribute('aria-valuenow', '100');
                bar.classList.add('processing');
                percent.textContent = '100%';
                status.textContent = 'Procesando archivos';
                detail.textContent = 'La transferencia termino. El servidor esta verificando y guardando la version...';
                button.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i>Procesando...';
            });
            request.addEventListener('load', () => {
                if (request.responseText) {
                    document.open();
                    document.write(request.responseText);
                    document.close();
                    return;
                }
                restoreUploadButton('No se recibio respuesta del servidor.');
            });
            request.addEventListener('error', () => restoreUploadButton('No se pudo completar la subida. Revisa tu conexion.'));
            request.addEventListener('timeout', () => restoreUploadButton('La subida excedio el tiempo de espera. Puedes reintentar.'));
            request.send(new FormData(versionUploadForm));

            function restoreUploadButton(message) {
                bar.classList.remove('processing');
                status.textContent = 'Subida no completada';
                detail.textContent = message;
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-upload me-2"></i>Reintentar subida';
            }
        });
    </script>
JS;

ob_start();
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 version-manager-wrap">
            <div class="card version-manager-card">
                <div class="card-header text-center">
                    <h3 class="mb-0">
                        <i class="fas fa-cloud-upload-alt me-2"></i>
                        Gestor de Versiones
                    </h3>
                </div>
                <div class="card-body p-3 p-lg-4">
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Información de versión actual -->
                    <div class="version-info">
                        <h5 class="mb-3" style="color: #2c3e50;">
                            <i class="fas fa-info-circle me-2"></i>
                            Versión Actual
                        </h5>
                        <div class="row">
                            <div class="col-sm-6">
                                <span class="version-badge">
                                    v<?= htmlspecialchars($currentVersion['latestVersion']) ?>
                                </span>
                            </div>
                            <div class="col-sm-6 text-sm-end">
                                <small style="color: #6c757d;">
                                    <?= $currentVersion['mandatory'] ? '<i class="fas fa-exclamation-triangle" style="color: #e67e22;"></i> Obligatoria' : '<i class="fas fa-info-circle" style="color: #3498db;"></i> Opcional' ?>
                                </small>
                            </div>
                        </div>
                        <?php if ($currentVersion['releaseNotes']): ?>
                            <div class="mt-2">
                                <small style="color: #6c757d;">
                                    <strong>Notas:</strong> <?= htmlspecialchars($currentVersion['releaseNotes']) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($currentVersion['uploadDate'])): ?>
                            <div class="mt-2">
                                <small style="color: #6c757d;">
                                    <i class="fas fa-calendar me-1"></i>
                                    <strong>Subida:</strong> <?= htmlspecialchars($currentVersion['uploadDate']) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($currentVersion['setupUrl'])): ?>
                            <div class="mt-2">
                                <small style="color: #6c757d;">
                                    <i class="fas fa-download me-1"></i>
                                    <strong>Setup ZIP disponible:</strong> Sí
                                    <?php if (!empty($currentVersion['setupFileSize'])): ?>
                                        (<?= number_format($currentVersion['setupFileSize'] / 1024 / 1024, 2) ?> MB)
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($isAuthenticated && $canUpload): ?>
                        <!-- Aviso sobre reemplazo de archivos -->
                        <div class="replacement-notice">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Reemplazo automático:</strong> Si subes archivos con la misma versión, los archivos anteriores serán respaldados automáticamente y reemplazados.
                        </div>
                        
                        <!-- Formulario -->
                        <form method="POST" enctype="multipart/form-data" id="versionUploadForm">
                        <div class="mb-3">
                            <label for="version" class="form-label" style="color: #2c3e50; font-weight: 500;">
                                <i class="fas fa-tag me-2"></i>
                                Versión <span style="color: #e74c3c;">*</span>
                            </label>
                            <input type="text" class="form-control" id="version" name="version" 
                                   placeholder="1.2.3.0" pattern="^\d+\.\d+\.\d+\.\d+$" required>
                            <div class="form-text" style="color: #6c757d;">Formato: X.X.X.X (ejemplo: 1.2.3.0)</div>
                        </div>
                        
                        <div class="upload-files-grid">
                        <div class="upload-file-field">
                            <label class="form-label" style="color: #2c3e50; font-weight: 500;">
                                <i class="fas fa-file-alt me-2"></i>
                                Archivo Ejecutable <span style="color: #e74c3c;">*</span>
                            </label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="exeFile" name="exeFile" accept=".exe" 
                                       class="file-upload-input" required onchange="updateFileName(this, 'exeFileName')">
                                <label for="exeFile" class="file-upload-label">
                                    <i class="fas fa-file-code file-upload-icon mb-2"></i>
                                    <div>
                                        <strong style="color: #2c3e50;">Haz clic para seleccionar el archivo .exe</strong>
                                        <div style="color: #6c757d;" class="mt-1">o arrastra y suelta aquí</div>
                                    </div>
                                    <div id="exeFileName" class="file-upload-name mt-2" style="display: none;"></div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="upload-file-field">
                            <label class="form-label" style="color: #2c3e50; font-weight: 500;">
                                <i class="fas fa-download me-2"></i>
                                Archivo Setup ZIP (Instalador) <span style="color: #e74c3c;">*</span>
                            </label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="setupFile" name="setupFile" accept=".zip" 
                                       class="file-upload-input" required onchange="updateFileName(this, 'setupFileName')">
                                <label for="setupFile" class="file-upload-label">
                                    <i class="fas fa-file-zipper file-upload-icon mb-2"></i>
                                    <div>
                                        <strong style="color: #2c3e50;">Haz clic para seleccionar el archivo Setup.zip</strong>
                                        <div style="color: #6c757d;" class="mt-1">o arrastra y suelta aquí</div>
                                    </div>
                                    <div id="setupFileName" class="file-upload-name mt-2" style="display: none;"></div>
                                </label>
                            </div>
                            <div class="form-text" style="color: #6c757d;">El archivo Setup ZIP será descargable públicamente por todos los usuarios</div>
                        </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="mandatory" name="mandatory">
                                <label class="form-check-label" for="mandatory" style="color: #2c3e50;">
                                    <i class="fas fa-exclamation-triangle me-2" style="color: #f39c12;"></i>
                                    Actualización obligatoria
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="releaseNotes" class="form-label" style="color: #2c3e50; font-weight: 500;">
                                <i class="fas fa-sticky-note me-2"></i>
                                Notas de la versión
                            </label>
                            <textarea class="form-control" id="releaseNotes" name="releaseNotes" 
                                     rows="3" placeholder="Describe los cambios, correcciones y mejoras..."></textarea>
                        </div>
                        
                        <div class="upload-progress-panel d-none mb-3" id="uploadProgressPanel" aria-live="polite">
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                                <strong id="uploadProgressStatus">Subiendo al servidor</strong>
                                <strong class="text-primary" id="uploadProgressPercent">0%</strong>
                            </div>
                            <div class="upload-progress-track" id="uploadProgressTrack" role="progressbar" aria-label="Progreso de subida" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div class="upload-progress-bar" id="uploadProgressBar"></div>
                            </div>
                            <div class="small text-muted mt-2" id="uploadProgressDetail">Preparando archivos...</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" id="uploadSubmitBtn">
                                <i class="fas fa-upload me-2"></i>
                                Subir Nueva Versión (EXE + Setup ZIP)
                            </button>
                        </div>
                    </form>
                    
                    <?php else: ?>
                        <!-- Mensaje para usuarios no autenticados o sin permisos -->
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-lock me-2"></i>
                            <?php if (!$isAuthenticated): ?>
                                <strong>Acceso requerido:</strong> Debes <a href="<?= app_url('/login') ?>" class="alert-link">iniciar sesión</a> para subir archivos.
                            <?php else: ?>
                                <strong>Permisos insuficientes:</strong> Tu cuenta no tiene permisos para subir archivos.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Enlaces adicionales -->
            <div class="text-center mt-3">
                <?php if (isset($userModel) && $userModel->hasPermission('admin_access') && current_path() !== '/admin/versions'): ?>
                    <a href="<?= app_url('/admin') ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-shield-alt me-1"></i>
                        Panel Admin
                    </a>
                <?php endif; ?>
                <a href="<?= app_url('/api/version') ?>" class="btn btn-outline-light me-2" target="_blank">
                    <i class="fas fa-code me-1"></i>
                    API Completa
                </a>
                <a href="<?= app_url('/api/check-update') ?>" class="btn btn-outline-light me-2" target="_blank">
                    <i class="fas fa-sync-alt me-1"></i>
                    Verificar Updates
                </a>
                <a href="<?= app_url('/api/download') ?>" class="btn btn-outline-light me-2" target="_blank">
                    <i class="fas fa-download me-1"></i>
                    Descargar EXE
                </a>
                <a href="<?= app_url('/api/download-zip') ?>" class="btn btn-outline-success me-2" target="_blank">
                    <i class="fas fa-download me-1"></i>
                    Descargar Setup ZIP
                </a>
                <a href="<?= app_url('/uploads/') ?>" class="btn btn-outline-light">
                    <i class="fas fa-folder me-1"></i>
                    Ver Archivos
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
