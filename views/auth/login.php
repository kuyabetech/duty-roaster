<?php
// =============================================
// Login Page - Fully Debugged Version
// =============================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


// Try to load required files with error handling
try {
    $configFile = __DIR__ . '/../../config/config.php';
    $functionsFile = __DIR__ . '/../../includes/functions.php';
    $authFile = __DIR__ . '/../../includes/auth.php';
    $securityFile = __DIR__ . '/../../includes/security.php';
    
    // Check if files exist
    if (!file_exists($configFile)) {
        throw new Exception("Config file not found: " . $configFile);
    }
    if (!file_exists($functionsFile)) {
        throw new Exception("Functions file not found: " . $functionsFile);
    }
    if (!file_exists($authFile)) {
        throw new Exception("Auth file not found: " . $authFile);
    }
    if (!file_exists($securityFile)) {
        throw new Exception("Security file not found: " . $securityFile);
    }
    
    // Load files
    require_once $configFile;
    require_once $functionsFile;
    require_once $authFile;
    require_once $securityFile;
    
} catch (Exception $e) {
    // Display error nicely
    die("<h1>System Error</h1>
         <p>Error loading required files:</p>
         <pre>" . $e->getMessage() . "</pre>
         <p>Please check your installation.</p>");
}

// Initialize auth
try {
    $auth = new Auth();
} catch (Exception $e) {
    die("<h1>Authentication Error</h1>
         <p>Failed to initialize authentication:</p>
         <pre>" . $e->getMessage() . "</pre>");
}

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect(SITE_URL . '/dashboard/');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request. Please try again.';
        } else {
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);
            
            if (empty($email) || empty($password)) {
                $error = 'Please fill in all fields.';
            } else {
                $result = $auth->login($email, $password, $remember);
                
                if ($result['success']) {
                    redirect(SITE_URL . '/dashboard/');
                } else {
                    $error = $result['message'];
                }
            }
        }
    } catch (Exception $e) {
        $error = 'System error: ' . $e->getMessage();
        error_log("Login error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo i {
            font-size: 48px;
            color: #ffd700;
        }
        .login-logo h2 {
            color: #fff;
            font-weight: 600;
            margin-top: 10px;
        }
        .login-logo p {
            color: rgba(255, 255, 255, 0.6);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #ffd700;
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25);
            color: #fff;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .form-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        .btn-login {
            background: #ffd700;
            color: #1a1a2e;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: #ffed4a;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
        }
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .text-link {
            color: #ffd700;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .text-link:hover {
            color: #ffed4a;
            text-decoration: underline;
        }
        .error-message {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .form-check-label {
            color: rgba(255, 255, 255, 0.7);
        }
        .form-check-input:checked {
            background-color: #ffd700;
            border-color: #ffd700;
        }
        .debug-info {
            margin-top: 20px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.3);
            text-align: center;
        }
        /* Password toggle */
        .password-toggle {
            position: relative;
        }
        .password-toggle .toggle-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
        }
        .password-toggle .toggle-btn:hover {
            color: rgba(255, 255, 255, 0.8);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <i class="fas fa-tasks"></i>
            <h2>ADPS</h2>
            <p>Automated Duty Processing System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm" novalidate>
            <?php 
            try {
                echo CSRF::getTokenField(); 
            } catch (Exception $e) {
                echo '<input type="hidden" name="csrf_token" value="' . bin2hex(random_bytes(32)) . '">';
            }
            ?>
            
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-2"></i> Email Address
                </label>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" 
                       required autofocus>
                <div class="invalid-feedback">Please enter a valid email address</div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-2"></i> Password
                </label>
                <div class="password-toggle">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                    <button type="button" class="toggle-btn" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordIcon"></i>
                    </button>
                </div>
                <div class="invalid-feedback">Please enter your password</div>
            </div>
            
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Remember me
                    </label>
                </div>
                <a href="<?php echo SITE_URL; ?>/views/auth/forgot-password.php" class="text-link">
                    Forgot Password?
                </a>
            </div>
            
            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
            
            <div class="text-center mt-3">
                <span style="color: rgba(255, 255, 255, 0.5);">Don't have an account?</span>
                <a href="<?php echo SITE_URL; ?>/views/auth/register.php" class="text-link">
                    Register
                </a>
            </div>
        </form>
        
        <!-- Debug Info -->
        <div class="debug-info">
            <small>
                Version: <?php echo date('Y-m-d H:i:s'); ?> | 
                PHP: <?php echo phpversion(); ?>
            </small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const submitBtn = document.getElementById('loginBtn');
            let isValid = true;
            
            // Validate email
            if (!email.value.trim()) {
                email.classList.add('is-invalid');
                isValid = false;
            } else {
                email.classList.remove('is-invalid');
            }
            
            // Validate password
            if (!password.value.trim()) {
                password.classList.add('is-invalid');
                isValid = false;
            } else {
                password.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Logging in...';
        });
        
        // Real-time validation
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email && emailRegex.test(email)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (email) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
        
        document.getElementById('password').addEventListener('input', function() {
            if (this.value.length >= 6) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (this.value) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
    </script>
</body>
</html>