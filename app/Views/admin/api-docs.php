<?php
$title = 'API Endpoints - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">Referencia de API</h3>
            <p class="text-muted mb-0">Consulta rápidamente los endpoints disponibles y ejemplos listos para copiar.</p>
        </div>
        <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Volver al panel
        </a>
    </div>

    <?php if (!empty($sections)) : ?>
        <div class="accordion" id="apiDocsAccordion">
            <?php foreach ($sections as $sectionIndex => $section) :
                $sectionId = 'section-' . $sectionIndex;
            ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-white" id="heading-<?= htmlspecialchars($sectionId) ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($section['title'] ?? 'Sección') ?></h5>
                                <?php if (!empty($section['description'])) : ?>
                                    <p class="text-muted mb-0 small"><?= htmlspecialchars($section['description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= htmlspecialchars($sectionId) ?>" aria-expanded="<?= $sectionIndex === 0 ? 'true' : 'false' ?>" aria-controls="collapse-<?= htmlspecialchars($sectionId) ?>">
                                <span class="fw-semibold">Ver endpoints</span>
                                <i class="fas fa-chevron-<?= $sectionIndex === 0 ? 'up' : 'down' ?> ms-2"></i>
                            </button>
                        </div>
                    </div>
                    <div id="collapse-<?= htmlspecialchars($sectionId) ?>" class="collapse <?= $sectionIndex === 0 ? 'show' : '' ?>" aria-labelledby="heading-<?= htmlspecialchars($sectionId) ?>" data-bs-parent="#apiDocsAccordion">
                        <div class="card-body p-0">
                            <?php if (!empty($section['endpoints'])) : ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($section['endpoints'] as $endpointIndex => $endpoint) :
                                        $method = strtoupper($endpoint['method'] ?? 'GET');
                                        $methodClass = [
                                            'GET' => 'badge bg-primary',
                                            'POST' => 'badge bg-success',
                                            'PUT' => 'badge bg-warning text-dark',
                                            'PATCH' => 'badge bg-warning text-dark',
                                            'DELETE' => 'badge bg-danger',
                                        ][$method] ?? 'badge bg-secondary';
                                        $endpointId = $sectionId . '-endpoint-' . $endpointIndex;
                                    ?>
                                        <div class="list-group-item py-4">
                                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                                <div>
                                                    <span class="<?= $methodClass ?> me-2"><?= htmlspecialchars($method) ?></span>
                                                    <code><?= htmlspecialchars($endpoint['path'] ?? '/') ?></code>
                                                    <?php if (!empty($endpoint['description'])) : ?>
                                                        <p class="text-muted small mb-0 mt-2"><?= htmlspecialchars($endpoint['description']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end small text-muted">
                                                    <span>Copiar ejemplos:</span>
                                                    <?php if (!empty($endpoint['requestExample'])) : ?>
                                                        <button class="btn btn-sm btn-outline-primary ms-2 copy-json" data-target="request-<?= htmlspecialchars($endpointId) ?>">
                                                            Body
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (!empty($endpoint['responseExample'])) : ?>
                                                        <button class="btn btn-sm btn-outline-secondary ms-1 copy-json" data-target="response-<?= htmlspecialchars($endpointId) ?>">
                                                            Respuesta
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($endpoint['query'])) : ?>
                                                <div class="mb-3">
                                                    <h6 class="fw-semibold mb-2">Parámetros de consulta</h6>
                                                    <pre class="bg-light p-3 rounded"><code><?= htmlspecialchars(json_encode($endpoint['query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($endpoint['requestExample'])) : ?>
                                                <div class="mb-3">
                                                    <h6 class="fw-semibold mb-2">Ejemplo de body</h6>
                                                    <pre class="bg-dark text-white p-3 rounded position-relative"><code id="request-<?= htmlspecialchars($endpointId) ?>"><?= htmlspecialchars(json_encode($endpoint['requestExample'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($endpoint['responseExample'])) : ?>
                                                <div class="mb-3">
                                                    <h6 class="fw-semibold mb-2">Respuesta enviada</h6>
                                                    <pre class="bg-dark text-white p-3 rounded position-relative"><code id="response-<?= htmlspecialchars($endpointId) ?>"><?= htmlspecialchars(json_encode($endpoint['responseExample'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($endpoint['notes'])) : ?>
                                                <ul class="list-unstyled small text-muted mb-0">
                                                    <?php foreach ($endpoint['notes'] as $note) : ?>
                                                        <li class="mb-1">
                                                            <i class="fas fa-info-circle me-2 text-primary"></i><?= htmlspecialchars($note) ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="fas fa-circle-info me-2"></i>No hay endpoints documentados para esta sección.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No hay endpoints registrados todavía.
        </div>
    <?php endif; ?>
</div>

<?php
$customStyles = <<<CSS
.copy-json {
    transition: transform 0.1s ease;
}

.copy-json:active {
    transform: scale(0.95);
}

pre {
    overflow-x: auto;
}
CSS;

$customScripts = <<<HTML
<script>
(function() {
    document.querySelectorAll('.copy-json').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = button.getAttribute('data-target');
            const codeElement = document.getElementById(targetId);
            if (!codeElement) {
                return;
            }

            const content = codeElement.textContent;

            if (!navigator.clipboard) {
                const textarea = document.createElement('textarea');
                textarea.value = content;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            } else {
                navigator.clipboard.writeText(content);
            }

            button.classList.add('btn-success');
            button.classList.remove('btn-outline-primary', 'btn-outline-secondary');
            button.innerHTML = '<i class="fas fa-check me-1"></i>Copiado';

            setTimeout(function() {
                button.innerHTML = button.textContent.trim() === 'Copiado' ? 'Copiar' : button.innerHTML;
                button.classList.remove('btn-success');
                if (button.getAttribute('data-target').startsWith('request-')) {
                    button.classList.add('btn-outline-primary');
                    button.textContent = 'Body';
                } else {
                    button.classList.add('btn-outline-secondary');
                    button.textContent = 'Respuesta';
                }
            }, 1800);
        });
    });
})();
</script>
HTML;
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
