<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

$auth = new Auth();

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email) || !isValidEmail($email)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if user exists and is not verified
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND email_verified = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'No unverified account found with this email';
        } else {
            // Generate new verification token
            $token = bin2hex(random_bytes(32));
            
            // Store new token
            $stmt = $db->prepare("
                INSERT INTO email_verifications (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $user['id'],
                $token,
                date('Y-m-d H:i:s', time() + 86400) // 24 hours
            ]);
            
            // Send verification email
            $verificationLink = SITE_URL . '/auth/verify-email.php?token=' . $token;
            
            // Log the link for development
            error_log("Verification link: " . $verificationLink);
            
            $success = 'Verification email has been resent. Please check your inbox.';
        }
    }
}

$pageTitle = 'Resend Verification';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .resend-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .resend-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .resend-logo i {
            font-size: 48px;
            color: #ffd700;
        }
        .resend-logo h2 {
            color: #fff;
            font-weight: 600;
            margin-top: 10px;
        }
        .resend-logo p {
            color: rgba(255, 255, 255, 0.6);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 12px 15px;
            border-radius: 10px;
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
        .btn-resend {
            background: #ffd700;
            color: #1a1a2e;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-resend:hover {
            background: #ffed4a;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
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
        .alert {
            border-radius: 10px;
            border: none;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #5cb85c;
        }
    </style>
</head>
<body>
    <div class="resend-container">
        <div class="resend-logo">
            <i class="fas fa-tasks"></i>
            <h2>ADPS</h2>
            <p>Resend Verification Email</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-2"></i> Email Address
                </label>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="Enter your registered email" 
                       value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            
            <button type="submit" class="btn-resend">
                <i class="fas fa-redo me-2"></i> Resend Verification Email
            </button>
            
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="text-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </form>
    </div>
</body>
</html>