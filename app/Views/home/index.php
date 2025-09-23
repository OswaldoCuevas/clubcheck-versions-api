<?php
$title = 'Gestor de Versiones - ClubCheck';
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
        function updateFileName(input) {
            const fileName = document.getElementById("fileName");
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
        
        // Drag and drop functionality
        const fileLabel = document.querySelector(".file-upload-label");
        const fileInput = document.getElementById("exeFile");
        
        if (fileLabel && fileInput) {
            fileLabel.addEventListener("dragover", function(e) {
                e.preventDefault();
                this.style.borderColor = "#3498db";
                this.style.background = "#e3f2fd";
            });
            
            fileLabel.addEventListener("dragleave", function(e) {
                e.preventDefault();
                this.style.borderColor = "#adb5bd";
                this.style.background = "#f8f9fa";
            });
            
            fileLabel.addEventListener("drop", function(e) {
                e.preventDefault();
                this.style.borderColor = "#adb5bd";
                this.style.background = "#f8f9fa";
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    updateFileName(fileInput);
                }
            });
        }
    </script>
';

ob_start();
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header text-center">
                    <h3 class="mb-0">
                        <i class="fas fa-cloud-upload-alt me-2"></i>
                        Gestor de Versiones ClubCheck
                    </h3>
                </div>
                <div class="card-body p-4">
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
                    </div>
                    
                    <?php if ($isAuthenticated && $canUpload): ?>
                        <!-- Aviso sobre reemplazo de archivos -->
                        <div class="replacement-notice">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Reemplazo automático:</strong> Si subes un archivo con la misma versión, el archivo anterior será respaldado automáticamente y reemplazado.
                        </div>
                        
                        <!-- Formulario -->
                        <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label for="version" class="form-label" style="color: #2c3e50; font-weight: 500;">
                                <i class="fas fa-tag me-2"></i>
                                Versión <span style="color: #e74c3c;">*</span>
                            </label>
                            <input type="text" class="form-control" id="version" name="version" 
                                   placeholder="1.2.3.0" pattern="^\d+\.\d+\.\d+\.\d+$" required>
                            <div class="form-text" style="color: #6c757d;">Formato: X.X.X.X (ejemplo: 1.2.3.0)</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label" style="color: #2c3e50; font-weight: 500;">
                                <i class="fas fa-file-alt me-2"></i>
                                Archivo Ejecutable <span style="color: #e74c3c;">*</span>
                            </label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="exeFile" name="exeFile" accept=".exe" 
                                       class="file-upload-input" required onchange="updateFileName(this)">
                                <label for="exeFile" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2" style="color: #95a5a6;"></i>
                                    <div>
                                        <strong style="color: #2c3e50;">Haz clic para seleccionar el archivo .exe</strong>
                                        <div style="color: #6c757d;" class="mt-1">o arrastra y suelta aquí</div>
                                    </div>
                                    <div id="fileName" class="mt-2" style="color: #3498db; display: none;"></div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="mandatory" name="mandatory">
                                <label class="form-check-label" for="mandatory" style="color: #2c3e50;">
                                    <i class="fas fa-exclamation-triangle me-2" style="color: #f39c12;"></i>
                                    Actualización obligatoria
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="releaseNotes" class="form-label" style="color: #2c3e50; font-weight: 500;">
                                <i class="fas fa-sticky-note me-2"></i>
                                Notas de la versión
                            </label>
                            <textarea class="form-control" id="releaseNotes" name="releaseNotes" 
                                     rows="3" placeholder="Describe los cambios, correcciones y mejoras..."></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-upload me-2"></i>
                                Subir Nueva Versión
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
                <?php if (isset($userModel) && $userModel->hasPermission('admin_access')): ?>
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
