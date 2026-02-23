<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? htmlspecialchars($title) : 'ClubCheck' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f5f7fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #2c3e50;
        }
        
        .container {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }
        
        .card {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            background: #ffffff;
        }
        
        .card-header {
            background: #34495e;
            color: white;
            border-radius: 4px 4px 0 0 !important;
            padding: 1.5rem;
            border-bottom: 1px solid #2c3e50;
        }
        
        .form-control, .form-select {
            border-radius: 4px;
            border: 1px solid #ced4da;
            padding: 0.75rem 1rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background-color: #ffffff;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
            outline: 0;
        }
        
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            border-radius: 4px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
            border: 1px solid #3498db;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
            transform: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .alert {
            border-radius: 4px;
            border: 1px solid;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .btn-outline-light {
            color: #6c757d;
            border-color: #6c757d;
            background: transparent;
            border-radius: 4px;
        }
        
        .btn-outline-light:hover {
            color: #ffffff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        <?= isset($customStyles) ? $customStyles : '' ?>
    </style>
</head>
<body>
    <?php if (!isset($hideNavbar) || !$hideNavbar): ?>
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="<?= app_url('/') ?>">>
                <i class="fas fa-cloud-upload-alt me-2"></i>
                ClubCheck Versioning
            </a>
            <div class="navbar-nav ms-auto">
                <?php if (isset($isAuthenticated) && $isAuthenticated): ?>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?= htmlspecialchars($currentUser['username'] ?? 'Usuario') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-id-badge me-2"></i>
                                <?= htmlspecialchars($currentUser['role'] ?? 'Usuario') ?>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= app_url('/logout') ?>">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Cerrar Sesión
                            </a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a class="nav-link" href="<?= app_url('/login') ?>">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        Iniciar Sesión
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Contenido principal -->
    <?= $content ?? '' ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?= isset($customScripts) ? $customScripts : '' ?>
    
    <script>
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 8000);
    </script>
</body>
</html>
