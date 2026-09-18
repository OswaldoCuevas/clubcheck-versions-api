<?php

if (!function_exists('admin_pagination_styles')) {
    function admin_pagination_styles(): string
    {
        return <<<'CSS'
        .admin-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 18px;
            padding: 14px 0;
        }

        .admin-pagination-summary {
            color: #5a7490;
            font-size: 13px;
            font-weight: 700;
        }

        .admin-pagination-controls {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .admin-pagination-button {
            min-width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cfe8f8;
            border-radius: 12px;
            background: #ffffff;
            color: #087cba;
            font-size: 13px;
            font-weight: 800;
            box-shadow: 0 10px 22px rgba(47, 128, 237, 0.08);
            text-decoration: none;
        }

        .admin-pagination-button:hover:not(:disabled),
        .admin-pagination-button.active {
            border-color: #1299dc;
            background: #1299dc;
            color: #ffffff;
        }

        .admin-pagination-button:disabled,
        .admin-pagination-button.disabled {
            cursor: not-allowed;
            opacity: 0.48;
            box-shadow: none;
            pointer-events: none;
        }

        @media (max-width: 640px) {
            .admin-pagination {
                align-items: stretch;
            }

            .admin-pagination-summary,
            .admin-pagination-controls {
                width: 100%;
                justify-content: center;
            }
        }
CSS;
    }
}

if (!function_exists('admin_pagination_scripts')) {
    function admin_pagination_scripts(): string
    {
        return <<<'HTML'
    <script>
        window.AdminPagination = window.AdminPagination || {};

        window.AdminPagination.escape = function(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
            });
        };

        window.AdminPagination.pages = function(currentPage, totalPages, maxButtons = 5) {
            const total = Math.max(1, Number(totalPages) || 1);
            const current = Math.min(Math.max(1, Number(currentPage) || 1), total);
            const max = Math.max(3, Number(maxButtons) || 5);
            let start = Math.max(1, current - Math.floor(max / 2));
            let end = Math.min(total, start + max - 1);
            start = Math.max(1, end - max + 1);

            const pages = [];
            for (let page = start; page <= end; page += 1) {
                pages.push(page);
            }
            return pages;
        };

        window.AdminPagination.range = function(items, currentPage, pageSize) {
            const size = Math.max(1, Number(pageSize) || 10);
            const totalItems = Array.isArray(items) ? items.length : 0;
            const totalPages = Math.max(1, Math.ceil(totalItems / size));
            const page = Math.min(Math.max(1, Number(currentPage) || 1), totalPages);
            const start = (page - 1) * size;

            return {
                items: (items || []).slice(start, start + size),
                page,
                pageSize: size,
                totalItems,
                totalPages,
                from: totalItems === 0 ? 0 : start + 1,
                to: Math.min(totalItems, start + size)
            };
        };

        window.AdminPagination.render = function(config) {
            const container = typeof config.container === 'string'
                ? document.querySelector(config.container)
                : config.container;
            if (!container) return;

            const escape = window.AdminPagination.escape;
            const page = Math.max(1, Number(config.page) || 1);
            const pageSize = Math.max(1, Number(config.pageSize) || 10);
            const totalItems = Math.max(0, Number(config.totalItems) || 0);
            const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
            const safePage = Math.min(page, totalPages);
            const from = totalItems === 0 ? 0 : ((safePage - 1) * pageSize) + 1;
            const to = Math.min(totalItems, safePage * pageSize);
            const pages = window.AdminPagination.pages(safePage, totalPages, config.maxButtons || 5);

            container.innerHTML = `
                <nav class="admin-pagination" aria-label="${escape(config.label || 'Paginacion')}">
                    <div class="admin-pagination-summary">
                        ${escape(config.summaryLabel || 'Mostrando')} ${from}-${to} de ${totalItems}
                    </div>
                    <div class="admin-pagination-controls">
                        <button type="button" class="admin-pagination-button" data-page="${safePage - 1}" ${safePage <= 1 ? 'disabled' : ''} aria-label="Pagina anterior">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        ${pages.map((item) => `
                            <button type="button" class="admin-pagination-button ${item === safePage ? 'active' : ''}" data-page="${item}">
                                ${item}
                            </button>
                        `).join('')}
                        <button type="button" class="admin-pagination-button" data-page="${safePage + 1}" ${safePage >= totalPages ? 'disabled' : ''} aria-label="Pagina siguiente">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </nav>
            `;

            container.querySelectorAll('[data-page]').forEach((button) => {
                button.addEventListener('click', () => {
                    const nextPage = Number(button.dataset.page) || 1;
                    if (typeof config.onChange === 'function') {
                        config.onChange(Math.min(Math.max(1, nextPage), totalPages));
                    }
                });
            });
        };
    </script>
HTML;
    }
}
