<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - ClubCheck</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
        }
        
        .login-container {
            max-width: 450px;
            margin: 0 auto;
        }
        
        .login-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            background: #ffffff;
        }
        
        .card-header {
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            text-align: center;
            padding: 2.5rem 2rem 1.5rem;
            border-radius: 8px 8px 0 0;
        }
        
        .card-header .logo {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .card-header h3 {
            color: #2c3e50;
            font-weight: 600;
            font-size: 1.75rem;
            margin: 0 0 0.5rem 0;
        }
        
        .card-header .subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: 400;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-label {
            color: #495057;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }
        
        .form-control {
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 0.75rem;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #ffffff;
        }
        
        .form-control:focus {
            border-color: #2c3e50;
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.15);
            background: #ffffff;
        }
        
        .form-check-input {
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .form-check-input:checked {
            background-color: #2c3e50;
            border-color: #2c3e50;
        }
        
        .form-check-label {
            color: #495057;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: #2c3e50;
            border: 1px solid #2c3e50;
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: #34495e;
            border-color: #34495e;
            transform: translateY(-1px);
        }
        
        .btn-primary:focus {
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }
        
        .alert {
            border-radius: 6px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 2rem;
        }
        
        .footer-links a {
            color: #6c757d;
            text-decoration: none;
            margin: 0 1rem;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }
        
        .footer-links a:hover {
            color: #2c3e50;
        }
        
        .company-info {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            color: #adb5bd;
            font-size: 0.85rem;
        }
        
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem;
                max-width: none;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .card-header {
                padding: 2rem 1.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <div class="card-header">
                    <div class="logo">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3>ClubCheck</h3>
                    <p class="subtitle">Sistema Corporativo de Gestión</p>
                </div>
                <div class="card-body">
                    <?php if (isset($error) && $error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                Usuario
                            </label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Ingrese su usuario" required autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Contraseña
                            </label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Ingrese su contraseña" required>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Mantener sesión iniciada
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            Iniciar Sesión
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="footer-links">
                <a href="<?php echo app_url('/api/version'); ?>" target="_blank">
                    Información del Sistema
                </a>
                <a href="<?php echo app_url('/'); ?>">
                    Página Principal
                </a>
            </div>
        </div>
    </div>

    <div class="company-info">
        ClubCheck v2.0 - Sistema Empresarial
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
