<?php

if (!function_exists('admin_toast_styles')) {
    function admin_toast_styles(): string
    {
        return <<<'CSS'
        .admin-toast-container {
            position: fixed;
            z-index: 1200;
            top: 18px;
            right: 18px;
            width: min(390px, calc(100vw - 28px));
            display: grid;
            gap: 10px;
            pointer-events: none;
        }

        .admin-toast {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr) 30px;
            gap: 10px;
            align-items: start;
            padding: 14px;
            border: 1px solid #d7eafd;
            border-left-width: 5px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 18px 42px rgba(15, 39, 64, 0.16);
            color: #0f2740;
            opacity: 0;
            pointer-events: auto;
            transform: translateY(-8px);
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .admin-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .admin-toast.leaving {
            opacity: 0;
            transform: translateY(-8px);
        }

        .admin-toast-icon {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            font-size: 16px;
        }

        .admin-toast-title {
            margin: 0 0 2px;
            color: #0f2740;
            font-size: 14px;
            font-weight: 800;
            line-height: 1.25;
        }

        .admin-toast-message {
            margin: 0;
            color: #5a7490;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .admin-toast-close {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 50%;
            background: transparent;
            color: #657386;
        }

        .admin-toast-close:hover {
            background: #f0f8ff;
            color: #087cba;
        }

        .admin-toast.success {
            border-left-color: #18a058;
        }

        .admin-toast.success .admin-toast-icon {
            background: #e7f9ef;
            color: #147a42;
        }

        .admin-toast.danger,
        .admin-toast.error {
            border-left-color: #d92d4b;
        }

        .admin-toast.danger .admin-toast-icon,
        .admin-toast.error .admin-toast-icon {
            background: #fff1f3;
            color: #c62840;
        }

        .admin-toast.warning {
            border-left-color: #f59e0b;
        }

        .admin-toast.warning .admin-toast-icon {
            background: #fff7df;
            color: #936300;
        }

        .admin-toast.info {
            border-left-color: #1299dc;
        }

        .admin-toast.info .admin-toast-icon {
            background: #eaf8ff;
            color: #087cba;
        }

        @media (max-width: 640px) {
            .admin-toast-container {
                top: 12px;
                right: 12px;
                left: 12px;
                width: auto;
            }
        }
CSS;
    }
}

if (!function_exists('admin_toast_scripts')) {
    function admin_toast_scripts(): string
    {
        return <<<'HTML'
    <script>
        window.AdminToast = window.AdminToast || {};

        window.AdminToast.escape = function(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
            });
        };

        window.AdminToast.container = function() {
            let container = document.querySelector('[data-admin-toast-container]');
            if (!container) {
                container = document.createElement('div');
                container.className = 'admin-toast-container';
                container.setAttribute('data-admin-toast-container', '1');
                document.body.appendChild(container);
            }
            return container;
        };

        window.AdminToast.show = function(message, type = 'info', options = {}) {
            const escape = window.AdminToast.escape;
            const normalizedType = type === 'error' ? 'danger' : type;
            const titles = {
                success: 'Operacion exitosa',
                danger: 'No se pudo completar',
                warning: 'Atencion',
                info: 'Informacion'
            };
            const icons = {
                success: 'fas fa-check',
                danger: 'fas fa-triangle-exclamation',
                warning: 'fas fa-circle-exclamation',
                info: 'fas fa-circle-info'
            };
            const duration = Number(options.duration ?? 5000);
            const toast = document.createElement('div');
            toast.className = `admin-toast ${escape(normalizedType)}`;
            toast.setAttribute('role', normalizedType === 'danger' ? 'alert' : 'status');
            toast.innerHTML = `
                <span class="admin-toast-icon"><i class="${icons[normalizedType] || icons.info}"></i></span>
                <span>
                    <strong class="admin-toast-title">${escape(options.title || titles[normalizedType] || titles.info)}</strong>
                    <p class="admin-toast-message">${escape(message)}</p>
                </span>
                <button type="button" class="admin-toast-close" aria-label="Cerrar notificacion">
                    <i class="fas fa-xmark"></i>
                </button>
            `;

            const remove = function() {
                toast.classList.add('leaving');
                setTimeout(function() {
                    toast.remove();
                }, 180);
            };

            toast.querySelector('.admin-toast-close').addEventListener('click', remove);
            window.AdminToast.container().appendChild(toast);
            requestAnimationFrame(function() {
                toast.classList.add('show');
            });

            if (duration > 0) {
                setTimeout(remove, duration);
            }

            return toast;
        };

        window.AdminToast.success = function(message, options = {}) {
            return window.AdminToast.show(message, 'success', options);
        };

        window.AdminToast.danger = function(message, options = {}) {
            return window.AdminToast.show(message, 'danger', options);
        };

        window.AdminToast.warning = function(message, options = {}) {
            return window.AdminToast.show(message, 'warning', options);
        };

        window.AdminToast.info = function(message, options = {}) {
            return window.AdminToast.show(message, 'info', options);
        };
    </script>
HTML;
    }
}
