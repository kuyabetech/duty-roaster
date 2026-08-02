<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

$auth = new Auth();

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    redirect(SITE_URL . '/auth/login.php');
}

// Verify email
$result = $auth->verifyEmail($token);

$pageTitle = 'Email Verification';
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
        .verify-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        .verify-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .verify-icon.success { color: #28a745; }
        .verify-icon.error { color: #dc3545; }
        .verify-icon.info { color: #ffd700; }
        .verify-title {
            color: #fff;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .verify-message {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 25px;
        }
        .btn-primary {
            background: #ffd700;
            color: #1a1a2e;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 10px;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #ffed4a;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
        }
        .btn-outline {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 12px 30px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <?php if ($result['success']): ?>
            <div class="verify-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="verify-title">Email Verified!</h2>
            <p class="verify-message"><?php echo $result['message']; ?></p>
            <p class="verify-message">You can now login to your account.</p>
            <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i> Login Now
            </a>
        <?php else: ?>
            <div class="verify-icon error">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2 class="verify-title">Verification Failed</h2>
            <p class="verify-message"><?php echo $result['message']; ?></p>
            <div class="mt-3">
                <a href="<?php echo SITE_URL; ?>/auth/resend-verification.php" class="btn btn-outline">
                    <i class="fas fa-redo me-2"></i> Resend Verification Email
                </a>
                <br><br>
                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i> Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>