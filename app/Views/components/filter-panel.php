<?php

if (!function_exists('admin_filter_panel_styles')) {
    function admin_filter_panel_styles(): string
    {
        return <<<'CSS'
        .admin-filter-shell {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
        }

        .admin-filter-summary {
            min-width: 0;
            display: flex;
            flex: 1;
            align-items: center;
            gap: 10px;
            overflow-x: auto;
            padding: 2px 2px 6px;
            scrollbar-color: #1299dc #eaf8ff;
        }

        .admin-filter-summary::-webkit-scrollbar {
            height: 8px;
        }

        .admin-filter-summary::-webkit-scrollbar-track {
            background: #eaf8ff;
            border-radius: 999px;
        }

        .admin-filter-summary::-webkit-scrollbar-thumb {
            background: #1299dc;
            border-radius: 999px;
        }

        .admin-filter-label {
            flex: 0 0 auto;
            color: #0f2740;
            font-weight: 700;
        }

        .admin-filter-chip {
            min-height: 38px;
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px 0 16px;
            border: 1px solid #dbe6ef;
            border-radius: 999px;
            background: #edf3f8;
            color: #2f455b;
            font-weight: 600;
        }

        .admin-filter-chip button {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            background: transparent;
            color: #647386;
        }

        .admin-filter-chip button:hover {
            background: #d8e6f0;
            color: #0f2740;
        }

        .admin-filter-button {
            min-width: 110px;
            min-height: 52px;
        }

        .admin-filter-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1060;
            display: none;
            background: rgba(15, 23, 42, 0.42);
        }

        .admin-filter-backdrop.open {
            display: block;
        }

        .admin-filter-drawer {
            position: fixed;
            z-index: 1061;
            top: 0;
            right: 0;
            width: min(410px, 100vw);
            height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 24px;
            background: #ffffff;
            box-shadow: -22px 0 48px rgba(15, 39, 64, 0.14);
            transform: translateX(100%);
            transition: transform 0.2s ease;
        }

        .admin-filter-drawer.open {
            transform: translateX(0);
        }

        .admin-filter-drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .admin-filter-drawer-title {
            margin: 0;
            color: #344052;
            font-size: 22px;
            font-weight: 800;
        }

        .admin-filter-close {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            background: #ffffff;
            color: #657386;
            font-size: 18px;
        }

        .admin-filter-close:hover {
            background: #f0f8ff;
            color: #087cba;
        }

        .admin-filter-fields {
            display: grid;
            gap: 16px;
            overflow-y: auto;
            padding-right: 4px;
            scrollbar-color: #1299dc #eaf8ff;
        }

        .admin-filter-fields::-webkit-scrollbar {
            width: 8px;
        }

        .admin-filter-fields::-webkit-scrollbar-track {
            background: #eaf8ff;
            border-radius: 999px;
        }

        .admin-filter-fields::-webkit-scrollbar-thumb {
            background: #1299dc;
            border-radius: 999px;
        }

        .admin-filter-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: auto;
        }

        @media (max-width: 640px) {
            .admin-filter-shell {
                align-items: stretch;
                flex-direction: column;
            }

            .admin-filter-button {
                width: 100%;
            }
        }
CSS;
    }
}

if (!function_exists('admin_filter_panel_scripts')) {
    function admin_filter_panel_scripts(): string
    {
        return <<<'HTML'
    <script>
        window.AdminFilters = window.AdminFilters || {};

        window.AdminFilters.escape = function(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
            });
        };

        window.AdminFilters.mount = function(config) {
            const escape = window.AdminFilters.escape;
            const container = typeof config.container === 'string'
                ? document.querySelector(config.container)
                : config.container;

            console.log('[AdminFilters] mount:start', {
                selector: typeof config.container === 'string' ? config.container : null,
                containerFound: Boolean(container),
                title: config.title || 'Filtros'
            });

            if (!container) return null;

            const id = config.id || ('admin-filter-' + Date.now());
            const defaults = config.defaults || {};
            const fields = config.fields || [];
            let values = Object.assign({}, defaults, config.values || {});
            const optionCache = {};
            let backdrop = null;
            let drawer = null;

            const valueLabel = function(field, value) {
                if (field.type === 'select') {
                    const options = optionCache[field.name] || field.options || [];
                    const match = options.find(function(option) {
                        return String(option.value) === String(value);
                    });
                    return match ? match.label : value;
                }

                return value;
            };

            const shouldShowChip = function(field) {
                const value = values[field.name];
                const empty = value === undefined || value === null || value === '';
                if (empty) return false;
                if ((field.hideChipValues || []).map(String).includes(String(value))) return false;
                if (field.showChipWhenDefault) return true;
                return String(value) !== String(defaults[field.name] ?? '');
            };

            const renderChips = function() {
                const chips = fields.filter(shouldShowChip).map(function(field) {
                    const label = field.chipLabel || field.label || field.name;
                    return `
                        <span class="admin-filter-chip">
                            ${escape(label)}: ${escape(valueLabel(field, values[field.name]))}
                            <button type="button" data-filter-clear="${escape(field.name)}" aria-label="Quitar filtro">
                                <i class="fas fa-xmark"></i>
                            </button>
                        </span>
                    `;
                }).join('');

                container.querySelector('[data-filter-chips]').innerHTML = chips || '<span class="text-muted">Sin filtros aplicados</span>';
            };

            const selectOptions = function(field) {
                const options = optionCache[field.name] || field.options || [];
                return options.map(function(option) {
                    return `<option value="${escape(option.value)}">${escape(option.label)}</option>`;
                }).join('');
            };

            const fieldInput = function(name) {
                if (!drawer) return null;
                return Array.from(drawer.querySelectorAll('[data-filter-field]')).find(function(input) {
                    return input.getAttribute('data-filter-field') === name;
                }) || null;
            };

            const fieldHtml = function(field) {
                const value = values[field.name] ?? defaults[field.name] ?? '';
                if (field.type === 'select') {
                    return `
                        <div>
                            <label class="form-label">${escape(field.label || field.name)}</label>
                            <select class="form-select" data-filter-field="${escape(field.name)}" data-search-select data-label="${escape(field.label || field.name)}" data-page-size="10">
                                ${selectOptions(field)}
                            </select>
                        </div>
                    `;
                }

                return `
                    <div>
                        <label class="form-label">${escape(field.label || field.name)}</label>
                        <input type="${escape(field.type || 'text')}" class="form-control" data-filter-field="${escape(field.name)}" value="${escape(value)}" placeholder="${escape(field.placeholder || '')}">
                    </div>
                `;
            };

            const renderDrawerFields = function() {
                console.log('[AdminFilters] renderDrawerFields:start', {
                    drawerFound: Boolean(drawer),
                    fieldCount: fields.length
                });
                if (!drawer) return;
                drawer.querySelector('[data-filter-fields]').innerHTML = fields.map(fieldHtml).join('');
                fields.forEach(function(field) {
                    const input = fieldInput(field.name);
                    if (input) input.value = values[field.name] ?? defaults[field.name] ?? '';
                });
                if (window.AdminUI && typeof window.AdminUI.initSearchSelects === 'function') {
                    window.AdminUI.initSearchSelects(drawer);
                }
                console.log('[AdminFilters] renderDrawerFields:done');
            };

            const loadRemoteOptions = async function(field) {
                if (!field.endpoint || optionCache[field.name]) return;
                try {
                    const response = await fetch(field.endpoint, { headers: { 'Accept': 'application/json' } });
                    const data = await response.json();
                    const path = field.dataPath ? field.dataPath.split('.') : [];
                    const rows = path.reduce(function(current, key) {
                        return current && current[key] !== undefined ? current[key] : [];
                    }, data);
                    optionCache[field.name] = (Array.isArray(rows) ? rows : []).map(function(row) {
                        return {
                            value: row[field.valueKey || 'value'],
                            label: row[field.labelKey || 'label']
                        };
                    });
                } catch (error) {
                    optionCache[field.name] = field.options || [];
                    console.warn('No se pudieron cargar opciones del filtro', field.name, error);
                }
            };

            const open = async function() {
                console.log('[AdminFilters] open:start', {
                    id,
                    backdropFound: Boolean(backdrop),
                    drawerFound: Boolean(drawer)
                });
                await Promise.all(fields.map(loadRemoteOptions));
                console.log('[AdminFilters] open:optionsLoaded');
                renderDrawerFields();
                if (!backdrop || !drawer) {
                    console.error('[AdminFilters] open:missingElements', {
                        id,
                        backdropFound: Boolean(backdrop),
                        drawerFound: Boolean(drawer),
                        container
                    });
                    return;
                }
                backdrop.classList.add('open');
                drawer.classList.add('open');
                console.log('[AdminFilters] open:done', {
                    backdropClasses: backdrop.className,
                    drawerClasses: drawer.className
                });
            };

            const close = function() {
                if (backdrop) backdrop.classList.remove('open');
                if (drawer) drawer.classList.remove('open');
            };

            const apply = function() {
                console.log('[AdminFilters] apply:start', { id });
                fields.forEach(function(field) {
                    const input = fieldInput(field.name);
                    if (input) values[field.name] = input.value;
                });
                renderChips();
                close();
                console.log('[AdminFilters] apply:values', values);
                if (typeof config.onApply === 'function') config.onApply(Object.assign({}, values));
            };

            const reset = function() {
                console.log('[AdminFilters] reset', { id });
                values = Object.assign({}, defaults);
                renderDrawerFields();
                renderChips();
                if (typeof config.onApply === 'function') config.onApply(Object.assign({}, values));
            };

            container.innerHTML = `
                <div class="admin-filter-shell">
                    <div class="admin-filter-summary">
                        <span class="admin-filter-label">Filtrado por:</span>
                        <span data-filter-chips></span>
                    </div>
                    <button type="button" class="btn btn-outline-primary admin-filter-button" data-filter-open>
                        <i class="fas fa-filter me-1"></i>Filtrar
                    </button>
                </div>
            `;

            const portal = document.createElement('div');
            portal.innerHTML = `
                <div class="admin-filter-backdrop" data-filter-backdrop></div>
                <aside class="admin-filter-drawer" id="${escape(id)}" data-filter-drawer aria-label="${escape(config.title || 'Filtros')}">
                    <div class="admin-filter-drawer-header">
                        <h2 class="admin-filter-drawer-title">${escape(config.title || 'Filtros')}</h2>
                        <button type="button" class="admin-filter-close" data-filter-close aria-label="Cerrar filtros">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>
                    <div class="admin-filter-fields" data-filter-fields></div>
                    <div class="admin-filter-actions">
                        <button type="button" class="btn btn-outline-primary" data-filter-reset>Limpiar</button>
                        <button type="button" class="btn btn-primary" data-filter-apply>Aplicar</button>
                    </div>
                </aside>
            `;
            document.body.appendChild(portal);
            backdrop = portal.querySelector('[data-filter-backdrop]');
            drawer = portal.querySelector('[data-filter-drawer]');
            const openButton = container.querySelector('[data-filter-open]');

            console.log('[AdminFilters] mount:ready', {
                id,
                openButtonFound: Boolean(openButton),
                backdropFound: Boolean(backdrop),
                drawerFound: Boolean(drawer),
                fields: fields.map(function(field) { return field.name; })
            });

            fields.forEach(function(field) {
                if (field.options) optionCache[field.name] = field.options;
            });

            if (openButton) {
                openButton.addEventListener('click', function(event) {
                    event.preventDefault();
                    console.log('[AdminFilters] openButton:click', { id });
                    open();
                });
            }

            if (backdrop) {
                backdrop.addEventListener('click', close);
            }

            if (drawer) {
                const closeButton = drawer.querySelector('[data-filter-close]');
                const applyButton = drawer.querySelector('[data-filter-apply]');
                const resetButton = drawer.querySelector('[data-filter-reset]');
                if (closeButton) closeButton.addEventListener('click', close);
                if (applyButton) applyButton.addEventListener('click', apply);
                if (resetButton) resetButton.addEventListener('click', reset);
            }

            container.addEventListener('click', function(event) {
                const clearButton = event.target.closest('[data-filter-clear]');
                if (clearButton) {
                    event.preventDefault();
                    const field = clearButton.getAttribute('data-filter-clear');
                    console.log('[AdminFilters] chip:clear', { id, field });
                    const definition = fields.find(function(item) {
                        return item.name === field;
                    });
                    values[field] = definition && Object.prototype.hasOwnProperty.call(definition, 'clearValue')
                        ? definition.clearValue
                        : (defaults[field] ?? '');
                    renderChips();
                    if (typeof config.onApply === 'function') config.onApply(Object.assign({}, values));
                }
            });

            renderChips();
            return {
                getValues: function() { return Object.assign({}, values); },
                setValues: function(nextValues) {
                    values = Object.assign({}, values, nextValues || {});
                    renderChips();
                },
                open: open,
                close: close
            };
        };
    </script>
HTML;
    }
}
