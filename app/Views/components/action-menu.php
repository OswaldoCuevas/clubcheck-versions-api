<?php

if (!function_exists('admin_action_menu_styles')) {
    function admin_action_menu_styles(): string
    {
        return <<<'CSS'
        .admin-action-menu {
            position: relative;
            display: inline-flex;
            justify-content: flex-end;
        }

        .admin-action-menu-toggle {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cfe8f8;
            border-radius: 12px;
            background: #ffffff;
            color: #087cba;
            box-shadow: 0 10px 22px rgba(47, 128, 237, 0.08);
        }

        .admin-action-menu-toggle:hover,
        .admin-action-menu.open .admin-action-menu-toggle {
            background: #eaf8ff;
            border-color: #8cd4f4;
            color: #075f8e;
        }

        .admin-action-menu-popover {
            position: absolute;
            z-index: 50;
            top: calc(100% + 8px);
            right: 0;
            min-width: 230px;
            display: none;
            padding: 8px;
            border: 1px solid #d7eafd;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 18px 42px rgba(15, 39, 64, 0.14);
        }

        .admin-action-menu.open .admin-action-menu-popover {
            display: grid;
            gap: 4px;
        }

        .admin-action-menu-item {
            width: 100%;
            min-height: 40px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 10px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: #15395b;
            font-weight: 700;
            text-align: left;
        }

        .admin-action-menu-item:hover {
            background: #f0f8ff;
            color: #087cba;
        }

        .admin-action-menu-item.danger {
            color: #c62840;
        }

        .admin-action-menu-item.danger:hover {
            background: #fff1f3;
            color: #9f1f34;
        }

        .admin-action-menu-item:disabled {
            cursor: not-allowed;
            opacity: 0.48;
        }

        .admin-action-menu-item i {
            width: 18px;
            text-align: center;
        }
CSS;
    }
}

if (!function_exists('admin_action_menu_scripts')) {
    function admin_action_menu_scripts(): string
    {
        return <<<'HTML'
    <script>
        window.AdminActionMenu = window.AdminActionMenu || {};

        window.AdminActionMenu.escape = function(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
            });
        };

        window.AdminActionMenu.render = function(actions, options = {}) {
            const escape = window.AdminActionMenu.escape;
            const label = escape(options.label || 'Opciones');
            const items = (actions || []).map(function(action) {
                const attrs = Object.entries(action.attrs || {})
                    .map(function(entry) {
                        return `${escape(entry[0])}="${escape(entry[1])}"`;
                    })
                    .join(' ');
                return `
                    <button type="button"
                            class="admin-action-menu-item ${escape(action.tone || '')}"
                            data-action="${escape(action.action)}"
                            ${action.disabled ? 'disabled' : ''}
                            ${attrs}>
                        <i class="${escape(action.icon || 'fas fa-circle')}"></i>
                        <span>${escape(action.name || action.action || 'Accion')}</span>
                    </button>
                `;
            }).join('');

            return `
                <div class="admin-action-menu" data-action-menu>
                    <button type="button" class="admin-action-menu-toggle" data-action-menu-toggle aria-label="${label}" title="${label}">
                        <i class="fas fa-ellipsis-vertical"></i>
                    </button>
                    <div class="admin-action-menu-popover">
                        ${items}
                    </div>
                </div>
            `;
        };

        document.addEventListener('click', function(event) {
            const toggle = event.target.closest('[data-action-menu-toggle]');
            document.querySelectorAll('[data-action-menu].open').forEach(function(menu) {
                if (!toggle || !menu.contains(toggle)) {
                    menu.classList.remove('open');
                }
            });

            if (toggle) {
                event.preventDefault();
                toggle.closest('[data-action-menu]')?.classList.toggle('open');
            }
        });
    </script>
HTML;
    }
}
