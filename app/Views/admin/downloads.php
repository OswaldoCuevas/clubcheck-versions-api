<?php
$title = 'Historial de Descargas - ClubCheck';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                    <h3 class="mb-1">
                        <i class="fas fa-download me-2"></i>
                        Historial de Descargas
                    </h3>
                    <p class="text-muted mb-0">Registro de todas las descargas de archivos ejecutables agrupadas por IP.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= app_url('/admin') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Volver al panel
                    </a>
                    <button type="button" class="btn btn-outline-secondary" id="refreshDownloads">
                        <i class="fas fa-rotate"></i>
                        Actualizar
                    </button>
                </div>
            </div>

            <!-- Resumen de estadísticas -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-primary mb-2">
                                <i class="fas fa-download fa-2x"></i>
                            </div>
                            <h3 class="mb-0" id="statTotal"><?= number_format($summary['TotalDownloads'] ?? 0) ?></h3>
                            <small class="text-muted">Total Descargas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-success mb-2">
                                <i class="fas fa-network-wired fa-2x"></i>
                            </div>
                            <h3 class="mb-0" id="statUniqueIps"><?= number_format($summary['UniqueIps'] ?? 0) ?></h3>
                            <small class="text-muted">IPs Únicas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-info mb-2">
                                <i class="fas fa-calendar-day fa-2x"></i>
                            </div>
                            <h3 class="mb-0" id="statToday"><?= number_format($summary['TodayDownloads'] ?? 0) ?></h3>
                            <small class="text-muted">Hoy</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-warning mb-2">
                                <i class="fas fa-calendar-week fa-2x"></i>
                            </div>
                            <h3 class="mb-0" id="statMonth"><?= number_format($summary['MonthDownloads'] ?? 0) ?></h3>
                            <small class="text-muted">Últimos 30 días</small>
                        </div>
                    </div>
                </div>
            </div>

            <div id="alertsContainer"></div>

            <!-- Filtro de búsqueda -->
            <div class="card shadow-sm mb-3">
                <div class="card-body py-2">
                    <form id="searchForm" class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="searchIp" name="ip" 
                                       placeholder="Buscar por IP..." value="<?= htmlspecialchars($searchIp ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="perPage" name="perPage">
                                <option value="10" <?= ($downloads['perPage'] ?? 20) == 10 ? 'selected' : '' ?>>10 por página</option>
                                <option value="20" <?= ($downloads['perPage'] ?? 20) == 20 ? 'selected' : '' ?>>20 por página</option>
                                <option value="50" <?= ($downloads['perPage'] ?? 20) == 50 ? 'selected' : '' ?>>50 por página</option>
                                <option value="100" <?= ($downloads['perPage'] ?? 20) == 100 ? 'selected' : '' ?>>100 por página</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                        </div>
                        <?php if ($searchIp): ?>
                        <div class="col-auto">
                            <a href="<?= app_url('/admin/downloads') ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Limpiar
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabla de descargas -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="downloadsTable">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">IP</th>
                                    <th scope="col" class="text-center">Total</th>
                                    <th scope="col" class="text-center">EXE</th>
                                    <th scope="col" class="text-center">Setup</th>
                                    <th scope="col">Versiones</th>
                                    <th scope="col">Última descarga</th>
                                    <th scope="col" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($downloads['data'])): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox me-2"></i>
                                        No hay descargas registradas
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($downloads['data'] as $row): ?>
                                <tr>
                                    <td>
                                        <code class="fs-6"><?= htmlspecialchars($row['IpAddress']) ?></code>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?= number_format($row['TotalDownloads']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= number_format($row['ExeDownloads']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?= number_format($row['SetupDownloads']) ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= htmlspecialchars($row['Versions'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <small><?= date('Y-m-d H:i', strtotime($row['LastDownload'])) ?></small>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            Primera: <?= date('Y-m-d', strtotime($row['FirstDownload'])) ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="showIpDetails('<?= htmlspecialchars($row['IpAddress']) ?>')"
                                                title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Paginación -->
                <?php if (($downloads['totalPages'] ?? 1) > 1): ?>
                <div class="card-footer bg-white">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="text-muted small">
                            Mostrando página <?= $downloads['page'] ?> de <?= $downloads['totalPages'] ?>
                            (<?= number_format($downloads['total']) ?> IPs en total)
                        </div>
                        <nav aria-label="Paginación">
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                $currentPage = $downloads['page'];
                                $totalPages = $downloads['totalPages'];
                                $perPage = $downloads['perPage'];
                                $searchParam = $searchIp ? '&ip=' . urlencode($searchIp) : '';
                                
                                // Primera página
                                if ($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= app_url('/admin/downloads?page=1&perPage=' . $perPage . $searchParam) ?>">
                                        <i class="fas fa-angles-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="<?= app_url('/admin/downloads?page=' . ($currentPage - 1) . '&perPage=' . $perPage . $searchParam) ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                                <?php endif;
                                
                                // Páginas cercanas
                                $start = max(1, $currentPage - 2);
                                $end = min($totalPages, $currentPage + 2);
                                
                                for ($i = $start; $i <= $end; $i++): ?>
                                <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= app_url('/admin/downloads?page=' . $i . '&perPage=' . $perPage . $searchParam) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                                <?php endfor;
                                
                                // Última página
                                if ($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= app_url('/admin/downloads?page=' . ($currentPage + 1) . '&perPage=' . $perPage . $searchParam) ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="<?= app_url('/admin/downloads?page=' . $totalPages . '&perPage=' . $perPage . $searchParam) ?>">
                                        <i class="fas fa-angles-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Detalle de IP -->
<div class="modal fade" id="ipDetailModal" tabindex="-1" aria-labelledby="ipDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ipDetailModalLabel">
                    <i class="fas fa-network-wired me-2"></i>
                    Detalle de IP: <code id="modalIpAddress"></code>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="ipDetailLoading" class="text-center py-4">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Cargando información...</p>
                </div>
                <div id="ipDetailContent" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Versión</th>
                                    <th>Archivo</th>
                                </tr>
                            </thead>
                            <tbody id="ipDetailTable"></tbody>
                        </table>
                    </div>
                </div>
                <div id="ipDetailError" class="alert alert-danger" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.getElementById('refreshDownloads');
    const searchForm = document.getElementById('searchForm');
    const perPageSelect = document.getElementById('perPage');
    const ipDetailModalEl = document.getElementById('ipDetailModal');
    const ipDetailModal = ipDetailModalEl ? new bootstrap.Modal(ipDetailModalEl) : null;

    // Refrescar página
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            window.location.reload();
        });
    }

    // Cambiar items por página
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            searchForm.submit();
        });
    }

    // Exponer función para mostrar detalles
    window.showIpDetails = async function(ipAddress) {
        if (!ipDetailModal) return;

        document.getElementById('modalIpAddress').textContent = ipAddress;
        document.getElementById('ipDetailLoading').style.display = 'block';
        document.getElementById('ipDetailContent').style.display = 'none';
        document.getElementById('ipDetailError').style.display = 'none';
        
        ipDetailModal.show();

        try {
            const response = await fetch(`<?= app_url('/admin/api/downloads/ip/') ?>${encodeURIComponent(ipAddress)}`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Error al obtener datos');
            }

            const tableBody = document.getElementById('ipDetailTable');
            
            if (!data.downloads || data.downloads.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted py-3">
                            No hay descargas registradas para esta IP
                        </td>
                    </tr>
                `;
            } else {
                tableBody.innerHTML = data.downloads.map(d => `
                    <tr>
                        <td><small>${formatDate(d.DownloadedAt)}</small></td>
                        <td>
                            <span class="badge ${d.DownloadType === 'exe' ? 'bg-info' : 'bg-success'}">
                                ${escapeHtml(d.DownloadType)}
                            </span>
                        </td>
                        <td><code>${escapeHtml(d.Version)}</code></td>
                        <td><small class="text-muted">${escapeHtml(d.FileName)}</small></td>
                    </tr>
                `).join('');
            }

            document.getElementById('ipDetailLoading').style.display = 'none';
            document.getElementById('ipDetailContent').style.display = 'block';

        } catch (error) {
            document.getElementById('ipDetailLoading').style.display = 'none';
            document.getElementById('ipDetailError').textContent = error.message;
            document.getElementById('ipDetailError').style.display = 'block';
        }
    };

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleString('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
