<?php
$title = 'Generador de Contraseñas - ClubCheck';

$customStyles = '
    .code-output {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 1rem;
        font-family: "Courier New", monospace;
        font-size: 0.9rem;
        white-space: pre-wrap;
    }
    .password-strength {
        height: 5px;
        border-radius: 3px;
        margin-top: 5px;
        transition: all 0.3s ease;
    }
    .strength-weak { background-color: #dc3545; }
    .strength-medium { background-color: #ffc107; }
    .strength-strong { background-color: #28a745; }
';

$customScripts = '
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert("Copiado al portapapeles");
            });
        }
        
        function updatePasswordStrength() {
            const password = document.getElementById("verify_password").value;
            const strengthBar = document.getElementById("password-strength");
            
            if (!strengthBar) return;
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            strengthBar.className = "password-strength ";
            if (strength < 2) strengthBar.className += "strength-weak";
            else if (strength < 4) strengthBar.className += "strength-medium";
            else strengthBar.className += "strength-strong";
        }
    </script>
';

ob_start();
?>

<div class="container mt-4">
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($generatedData): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    Resultado Generado
                </h5>
            </div>
            <div class="card-body">
                <?php if ($generatedData['type'] === 'hash'): ?>
                    <h6>Contraseña Original:</h6>
                    <div class="code-output mb-3">
                        <?= htmlspecialchars($generatedData['password']) ?>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= htmlspecialchars($generatedData['password'], ENT_QUOTES) ?>')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    
                    <h6>Hash Generado:</h6>
                    <div class="code-output">
                        <?= htmlspecialchars($generatedData['hash']) ?>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= htmlspecialchars($generatedData['hash'], ENT_QUOTES) ?>')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>

                <?php elseif ($generatedData['type'] === 'random'): ?>
                    <h6>Contraseña Generada (<?= $generatedData['length'] ?> caracteres):</h6>
                    <div class="code-output mb-3">
                        <?= htmlspecialchars($generatedData['password']) ?>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= htmlspecialchars($generatedData['password'], ENT_QUOTES) ?>')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    
                    <h6>Hash de la Contraseña:</h6>
                    <div class="code-output">
                        <?= htmlspecialchars($generatedData['hash']) ?>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= htmlspecialchars($generatedData['hash'], ENT_QUOTES) ?>')">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>

                <?php elseif ($generatedData['type'] === 'verify'): ?>
                    <div class="alert alert-<?= $generatedData['valid'] ? 'success' : 'danger' ?>">
                        <h6>Resultado de Verificación:</h6>
                        <p class="mb-0">
                            <i class="fas fa-<?= $generatedData['valid'] ? 'check-circle' : 'times-circle' ?> me-2"></i>
                            La contraseña "<?= htmlspecialchars($generatedData['password']) ?>" 
                            <?= $generatedData['valid'] ? 'es válida' : 'NO es válida' ?> para el hash proporcionado.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Generar Hash -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-hashtag me-2"></i>
                        Generar Hash
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="hash_password">
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña:</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-lock me-2"></i>
                            Generar Hash
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Generar Contraseña Aleatoria -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-random me-2"></i>
                        Generar Contraseña Aleatoria
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_random">
                        
                        <div class="mb-3">
                            <label for="length" class="form-label">Longitud:</label>
                            <input type="number" class="form-control" id="length" name="length" value="12" min="4" max="50">
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_upper" name="include_upper" checked>
                                <label class="form-check-label" for="include_upper">Mayúsculas (A-Z)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_lower" name="include_lower" checked>
                                <label class="form-check-label" for="include_lower">Minúsculas (a-z)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_numbers" name="include_numbers" checked>
                                <label class="form-check-label" for="include_numbers">Números (0-9)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_symbols" name="include_symbols" checked>
                                <label class="form-check-label" for="include_symbols">Símbolos (!@#$%)</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-dice me-2"></i>
                            Generar Contraseña
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Verificar Contraseña -->
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-check-double me-2"></i>
                        Verificar Contraseña
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="verify_password">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="verify_password" class="form-label">Contraseña:</label>
                                <input type="password" class="form-control" id="verify_password" name="verify_password" 
                                       onkeyup="updatePasswordStrength()" required>
                                <div id="password-strength" class="password-strength"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="verify_hash" class="form-label">Hash:</label>
                                <textarea class="form-control" id="verify_hash" name="verify_hash" rows="3" 
                                         placeholder="$2y$10$..." required></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-shield-alt me-2"></i>
                            Verificar Contraseña
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary me-2">
            <i class="fas fa-arrow-left me-2"></i>
            Volver al Panel Admin
        </a>
        <a href="<?= app_url('/') ?>" class="btn btn-outline-primary">
            <i class="fas fa-home me-2"></i>
            Inicio
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
