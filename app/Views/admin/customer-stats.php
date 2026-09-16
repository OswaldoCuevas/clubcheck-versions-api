<?php
$title = 'Estadisticas de Clientes';

ob_start();
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-end align-items-center mb-3 gap-2">
                <button type="button" class="btn btn-outline-primary" id="refreshStats">
                    <i class="fas fa-rotate"></i>
                    Actualizar
                </button>
            </div>

            <section class="customer-stat-summary">
                <article class="customer-stat-tile">
                    <span class="customer-stat-icon"><i class="fas fa-building"></i></span>
                    <div>
                        <strong><?= number_format((int)($globalStats['totalCustomers'] ?? 0)) ?></strong>
                        <span>Clientes totales</span>
                    </div>
                </article>
                <article class="customer-stat-tile">
                    <span class="customer-stat-icon"><i class="fas fa-users"></i></span>
                    <div>
                        <strong><?= number_format((int)($globalStats['totalUsers'] ?? 0)) ?></strong>
                        <span>Usuarios registrados</span>
                    </div>
                </article>
                <article class="customer-stat-tile">
                    <span class="customer-stat-icon"><i class="fas fa-id-card"></i></span>
                    <div>
                        <strong><?= number_format((int)($globalStats['totalActiveSubscriptions'] ?? 0)) ?></strong>
                        <span>Membresias activas</span>
                    </div>
                </article>
                <article class="customer-stat-tile">
                    <span class="customer-stat-icon"><i class="fas fa-box"></i></span>
                    <div>
                        <strong><?= number_format((int)($globalStats['totalProducts'] ?? 0)) ?></strong>
                        <span>Productos</span>
                    </div>
                </article>
                <article class="customer-stat-tile">
                    <span class="customer-stat-icon"><i class="fas fa-door-open"></i></span>
                    <div>
                        <strong><?= number_format((int)($globalStats['todayAttendances'] ?? 0)) ?></strong>
                        <span>Asistencias hoy</span>
                    </div>
                </article>
                <article class="customer-stat-tile">
                    <span class="customer-stat-icon"><i class="fas fa-clipboard-check"></i></span>
                    <div>
                        <strong><?= number_format((int)($globalStats['totalAttendances'] ?? 0)) ?></strong>
                        <span>Asistencias totales</span>
                    </div>
                </article>
                <article class="customer-stat-tile">
                    <span class="customer-stat-icon"><i class="fab fa-whatsapp"></i></span>
                    <div>
                        <strong><?= number_format((int)($globalStats['monthlyMessages'] ?? 0)) ?></strong>
                        <span>Mensajes este mes</span>
                    </div>
                </article>
            </section>

            <section class="customer-stat-list-panel">
                <div id="customerStatsFilter"></div>
                <div class="customer-stat-list-meta">
                    <span id="customerStatsTotalLabel">0 clientes</span>
                    <span id="customerStatsPageLabel">Pagina 1</span>
                </div>
                <div id="customerStatsGrid" class="customer-stat-grid">
                    <div class="customer-stat-empty">
                        <i class="fas fa-circle-notch fa-spin me-2"></i>
                        Cargando estadisticas...
                    </div>
                </div>
                <div id="customerStatsPagination"></div>
            </section>
        </div>
    </div>
</div>

<?php
$initialStatsJson = json_encode($customersStats ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$planOptions = [];
foreach (($customersStats ?? []) as $customerStat) {
    $plan = $customerStat['customer']['planCode'] ?? null;
    if ($plan !== null && $plan !== '' && !in_array($plan, $planOptions, true)) {
        $planOptions[] = $plan;
    }
}
sort($planOptions);
$planOptionsJson = json_encode(array_map(
    fn ($plan) => ['value' => $plan, 'label' => $plan],
    $planOptions
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$customStyles = <<<'CSS'
.customer-stat-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.customer-stat-tile {
    min-height: 126px;
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 22px 24px;
    border: 1px solid #d7eafd;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 8px 18px rgba(47, 128, 237, 0.06);
}

.customer-stat-icon {
    width: 56px;
    height: 56px;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    background: #e1f4ff;
    color: #087cba;
    font-size: 20px;
}

.customer-stat-tile > div {
    min-width: 0;
}

.customer-stat-tile strong {
    display: block;
    color: #0f2740;
    font-size: clamp(28px, 3vw, 34px);
    line-height: 1;
}

.customer-stat-tile span:last-child {
    display: block;
    margin-top: 9px;
    color: #5a7490;
    font-size: 14px;
    font-weight: 800;
    line-height: 1.35;
}

.customer-stat-list-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 4px 0 14px;
    color: #5a7490;
    font-size: 13px;
    font-weight: 800;
}

.customer-stat-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

.customer-stat-card {
    padding: 20px;
    border: 1px solid #d7eafd;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 8px 18px rgba(47, 128, 237, 0.06);
}

.customer-stat-card-header {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr) auto;
    align-items: start;
    gap: 14px;
}

.customer-stat-avatar {
    width: 52px;
    height: 52px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: #e1f4ff;
    color: #087cba;
    font-weight: 800;
    font-size: 17px;
}

.customer-stat-title {
    min-width: 0;
}

.customer-stat-title strong {
    display: block;
    color: #0f2740;
    font-size: 18px;
    line-height: 1.25;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.customer-stat-meta {
    color: #5a7490;
    font-size: 12px;
    font-weight: 700;
}

.customer-stat-contact {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 14px;
    margin-top: 4px;
}

.customer-stat-contact span {
    min-width: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    overflow-wrap: anywhere;
}

.customer-stat-contact i {
    color: #087cba;
}

.customer-stat-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.customer-stat-chip,
.customer-stat-status {
    min-height: 28px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 0 10px;
    border-radius: 999px;
    background: #f0f8ff;
    color: #075f8e;
    font-size: 12px;
    font-weight: 800;
}

.customer-stat-status.active {
    background: #e7f9ef;
    color: #147a42;
}

.customer-stat-status.inactive {
    background: #fff1f3;
    color: #c62840;
}

.customer-stat-toggle {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #cfe8f8;
    border-radius: 12px;
    background: #ffffff;
    color: #087cba;
    box-shadow: 0 6px 14px rgba(47, 128, 237, 0.06);
}

.customer-stat-toggle:hover {
    background: #eaf8ff;
    border-color: #8cd4f4;
}

.customer-stat-toggle i {
    transition: transform 0.18s ease;
}

.customer-stat-card.expanded .customer-stat-toggle i {
    transform: rotate(180deg);
}

.customer-stat-overview {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-top: 18px;
}

.customer-stat-overview-item {
    min-width: 0;
    min-height: 74px;
    display: grid;
    align-content: center;
    gap: 6px;
    padding: 12px 14px;
    border: 1px solid #e1effb;
    border-radius: 14px;
    background: #f7fbff;
}

.customer-stat-overview-item span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #5a7490;
    font-size: 11px;
    font-weight: 800;
    line-height: 1.2;
    text-transform: uppercase;
}

.customer-stat-overview-item i {
    color: #087cba;
}

.customer-stat-overview-item strong {
    color: #0f2740;
    font-size: 22px;
    line-height: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.customer-stat-body {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: 10px;
    margin-top: 16px;
}

.customer-stat-card:not(.expanded) .customer-stat-body {
    display: none;
}

.customer-stat-field {
    min-width: 0;
    grid-column: span 3;
    padding: 14px;
    border: 1px solid #e1effb;
    border-radius: 14px;
    background: #fbfdff;
}

.customer-stat-field span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 7px;
    color: #5a7490;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}

.customer-stat-field span i {
    color: #087cba;
}

.customer-stat-field strong {
    display: block;
    color: #0f2740;
    font-size: 20px;
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.customer-stat-field.is-wide {
    grid-column: span 6;
}

.customer-stat-field.is-full {
    grid-column: 1 / -1;
}

.customer-stat-field.is-date strong {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    font-size: 17px;
    line-height: 1.35;
}

.customer-stat-field small {
    display: block;
    margin-top: 5px;
}

.customer-stat-empty {
    padding: 42px 18px;
    border: 1px dashed #bde2f8;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.72);
    color: #5a7490;
    text-align: center;
    font-weight: 700;
}

@media (max-width: 1180px) {
    .customer-stat-overview {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .customer-stat-body {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .customer-stat-field,
    .customer-stat-field.is-wide {
        grid-column: span 1;
    }
}

@media (max-width: 640px) {
    .customer-stat-overview,
    .customer-stat-body {
        grid-template-columns: 1fr;
    }

    .customer-stat-summary {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .customer-stat-tile {
        min-height: 108px;
        padding: 18px;
    }

    .customer-stat-icon {
        width: 50px;
        height: 50px;
    }

    .customer-stat-card-header {
        grid-template-columns: 42px minmax(0, 1fr) auto;
    }

    .customer-stat-card {
        padding: 16px;
    }

    .customer-stat-avatar {
        width: 42px;
        height: 42px;
        font-size: 15px;
    }

    .customer-stat-title strong {
        font-size: 16px;
    }

    .customer-stat-field,
    .customer-stat-field.is-wide,
    .customer-stat-field.is-full {
        grid-column: span 1;
    }
}
CSS;

$customScripts = <<<JS
<script>
(function() {
    let customerStats = {$initialStatsJson};
    customerStats = Array.isArray(customerStats) ? customerStats : [];
    const planOptions = {$planOptionsJson};
    const pageSize = 10;
    let currentPage = 1;
    const expandedCustomers = new Set();
    let filters = {
        search: '',
        plan: '',
        status: 'all',
        sortBy: 'name',
        direction: 'asc'
    };

    const grid = document.getElementById('customerStatsGrid');
    const pagination = document.getElementById('customerStatsPagination');
    const totalLabel = document.getElementById('customerStatsTotalLabel');
    const pageLabel = document.getElementById('customerStatsPageLabel');
    const refreshBtn = document.getElementById('refreshStats');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNumber(value) {
        return new Intl.NumberFormat('es-MX').format(Number(value || 0));
    }

    function initials(name, fallback) {
        const source = String(name || fallback || '?').trim();
        return source.split(/\\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || '?';
    }

    function formatDate(value) {
        if (!value) return 'Sin actividad reciente';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return value;
        return new Intl.DateTimeFormat('es-MX', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        }).format(date);
    }

    function statValue(item, field) {
        const customer = item.customer || {};
        const stats = item.stats || {};
        const values = {
            name: customer.name || customer.customerId || '',
            plan: customer.planCode || '',
            users: stats.users || 0,
            subscriptions: stats.activeSubscriptions || 0,
            products: stats.products || 0,
            attendances: stats.monthlyAttendances || 0,
            messages: stats.messagesSentThisMonth || 0,
            lastSeen: customer.lastSeen || ''
        };
        return values[field] ?? values.name;
    }

    function filteredStats() {
        const search = String(filters.search || '').trim().toLowerCase();
        const filtered = customerStats.filter((item) => {
            const customer = item.customer || {};
            const matchesSearch = !search || [
                customer.name,
                customer.email,
                customer.customerId,
                customer.planCode
            ].some((value) => String(value || '').toLowerCase().includes(search));
            const matchesPlan = !filters.plan || customer.planCode === filters.plan;
            const matchesStatus = filters.status === 'all'
                || (filters.status === 'active' && customer.isActive)
                || (filters.status === 'inactive' && !customer.isActive);

            return matchesSearch && matchesPlan && matchesStatus;
        });

        const direction = filters.direction === 'desc' ? -1 : 1;
        return filtered.sort((a, b) => {
            const sortBy = filters.sortBy || 'name';
            const aValue = statValue(a, sortBy);
            const bValue = statValue(b, sortBy);
            if (['users', 'subscriptions', 'products', 'attendances', 'messages'].includes(sortBy)) {
                return (Number(aValue) - Number(bValue)) * direction;
            }

            return String(aValue).localeCompare(String(bValue), 'es', { sensitivity: 'base' }) * direction;
        });
    }

    function renderField(label, value, meta = '', icon = 'fas fa-circle-info', className = '') {
        return `
            <div class="customer-stat-field \${escapeHtml(className)}">
                <span><i class="\${escapeHtml(icon)}"></i>\${escapeHtml(label)}</span>
                <strong title="\${escapeHtml(value)}">\${escapeHtml(value)}</strong>
                \${meta ? `<small class="customer-stat-meta">\${escapeHtml(meta)}</small>` : ''}
            </div>
        `;
    }

    function renderOverviewItem(label, value, icon, meta = '') {
        return `
            <div class="customer-stat-overview-item">
                <span><i class="\${escapeHtml(icon)}"></i>\${escapeHtml(label)}</span>
                <strong title="\${escapeHtml(value)}">\${escapeHtml(value)}</strong>
                \${meta ? `<small class="customer-stat-meta">\${escapeHtml(meta)}</small>` : ''}
            </div>
        `;
    }

    function renderStats() {
        if (!grid) return;

        const visible = filteredStats();
        const page = window.AdminPagination
            ? window.AdminPagination.range(visible, currentPage, pageSize)
            : { items: visible, page: 1, pageSize, totalItems: visible.length, totalPages: 1 };

        currentPage = page.page;

        if (totalLabel) {
            totalLabel.textContent = page.totalItems === customerStats.length
                ? `\${formatNumber(page.totalItems)} clientes`
                : `\${formatNumber(page.totalItems)} de \${formatNumber(customerStats.length)} clientes`;
        }

        if (pageLabel) {
            pageLabel.textContent = `Pagina \${page.page} de \${page.totalPages}`;
        }

        if (window.AdminPagination) {
            window.AdminPagination.render({
                container: pagination,
                page: page.page,
                pageSize: page.pageSize,
                totalItems: page.totalItems,
                summaryLabel: 'Mostrando clientes',
                label: 'Paginacion de estadisticas',
                onChange: (nextPage) => {
                    currentPage = nextPage;
                    renderStats();
                    grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        if (!visible.length) {
            grid.innerHTML = `
                <div class="customer-stat-empty">
                    <i class="fas fa-inbox me-2"></i>
                    No hay clientes con esos filtros.
                </div>
            `;
            return;
        }

        grid.innerHTML = page.items.map((item) => {
            const customer = item.customer || {};
            const stats = item.stats || {};
            const id = customer.customerId || '';
            const expanded = expandedCustomers.has(id);
            const active = customer.isActive !== false;
            const plan = customer.planCode || 'Sin plan';
            const lastSeen = formatDate(customer.lastSeen);
            const monthlyAttendances = formatNumber(stats.monthlyAttendances);
            const activeSubscriptions = formatNumber(stats.activeSubscriptions);
            const totalSubscriptions = formatNumber(stats.totalSubscriptions);

            return `
                <article class="customer-stat-card \${expanded ? 'expanded' : ''}" data-customer-id="\${escapeHtml(id)}">
                    <div class="customer-stat-card-header">
                        <div class="customer-stat-avatar">\${escapeHtml(initials(customer.name, id))}</div>
                        <div class="customer-stat-title">
                            <strong title="\${escapeHtml(customer.name || id)}">\${escapeHtml(customer.name || 'Sin nombre')}</strong>
                            <div class="customer-stat-contact customer-stat-meta">
                                <span><i class="fas fa-envelope"></i>\${escapeHtml(customer.email || 'Sin email')}</span>
                                <span><i class="fas fa-fingerprint"></i>\${escapeHtml(id || 'Sin ID')}</span>
                            </div>
                            <div class="customer-stat-chips">
                                <span class="customer-stat-status \${active ? 'active' : 'inactive'}">
                                    <i class="fas fa-circle"></i>\${active ? 'Activo' : 'Inactivo'}
                                </span>
                                <span class="customer-stat-chip"><i class="fas fa-layer-group"></i>\${escapeHtml(plan)}</span>
                                <span class="customer-stat-chip"><i class="fas fa-clock"></i>\${escapeHtml(lastSeen)}</span>
                            </div>
                        </div>
                        <button type="button" class="customer-stat-toggle" data-toggle-stat="\${escapeHtml(id)}" aria-label="\${expanded ? 'Contraer cliente' : 'Desplegar cliente'}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="customer-stat-overview">
                        \${renderOverviewItem('Usuarios', formatNumber(stats.users), 'fas fa-users')}
                        \${renderOverviewItem('Membresias activas', activeSubscriptions, 'fas fa-id-card', `de \${totalSubscriptions} totales`)}
                        \${renderOverviewItem('Asistencias este mes', monthlyAttendances, 'fas fa-door-open')}
                        \${renderOverviewItem('Mensajes del mes', formatNumber(stats.messagesSentThisMonth), 'fab fa-whatsapp')}
                    </div>
                    <div class="customer-stat-body">
                        \${renderField('Productos', formatNumber(stats.products), '', 'fas fa-box')}
                        \${renderField('Asistencias hoy', formatNumber(stats.todayAttendances), `\${monthlyAttendances} este mes`, 'fas fa-calendar-day')}
                        \${renderField('Asistencias totales', formatNumber(stats.attendances), '', 'fas fa-clipboard-check')}
                        \${renderField('Plan actual', plan, '', 'fas fa-layer-group')}
                        \${renderField('Cliente', customer.name || 'Sin nombre', customer.email || 'Sin email', 'fas fa-building', 'is-wide')}
                        \${renderField('Ultima actividad', lastSeen, customer.lastSeen ? 'Fecha sincronizada del cliente' : '', 'fas fa-clock', 'is-wide is-date')}
                    </div>
                </article>
            `;
        }).join('');
    }

    if (grid) {
        grid.addEventListener('click', function(event) {
            const toggle = event.target.closest('[data-toggle-stat]');
            if (!toggle) return;

            const id = toggle.getAttribute('data-toggle-stat');
            if (expandedCustomers.has(id)) {
                expandedCustomers.delete(id);
            } else {
                expandedCustomers.add(id);
            }
            renderStats();
        });
    }

    if (window.AdminFilters) {
        window.AdminFilters.mount({
            container: '#customerStatsFilter',
            title: 'Filtros de estadisticas',
            defaults: filters,
            values: filters,
            fields: [
                {
                    name: 'search',
                    label: 'Buscar',
                    type: 'search',
                    placeholder: 'Buscar por nombre, email, ID o plan'
                },
                {
                    name: 'plan',
                    label: 'Plan',
                    type: 'select',
                    options: [{ value: '', label: 'Todos los planes' }, ...planOptions],
                    hideChipValues: ['']
                },
                {
                    name: 'status',
                    label: 'Estado',
                    type: 'select',
                    options: [
                        { value: 'all', label: 'Todos' },
                        { value: 'active', label: 'Activos' },
                        { value: 'inactive', label: 'Inactivos' }
                    ],
                    hideChipValues: ['all']
                },
                {
                    name: 'sortBy',
                    label: 'Ordenar por',
                    chipLabel: 'Ordenar por',
                    type: 'select',
                    showChipWhenDefault: true,
                    options: [
                        { value: 'name', label: 'Nombre' },
                        { value: 'users', label: 'Usuarios' },
                        { value: 'subscriptions', label: 'Membresias' },
                        { value: 'products', label: 'Productos' },
                        { value: 'attendances', label: 'Asistencias' },
                        { value: 'messages', label: 'Mensajes' },
                        { value: 'lastSeen', label: 'Ultima actividad' }
                    ]
                },
                {
                    name: 'direction',
                    label: 'Direccion',
                    chipLabel: 'Direccion',
                    type: 'select',
                    showChipWhenDefault: true,
                    options: [
                        { value: 'asc', label: 'Ascendente' },
                        { value: 'desc', label: 'Descendente' }
                    ]
                }
            ],
            onApply: (values) => {
                filters = values;
                currentPage = 1;
                renderStats();
            }
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            window.location.reload();
        });
    }

    renderStats();
})();
</script>
JS;
?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
?>
