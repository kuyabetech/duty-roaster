<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = '500 - Server Error';
http_response_code(500);
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
        .error-container {
            text-align: center;
            color: #fff;
            padding: 40px;
        }
        .error-container i {
            font-size: 80px;
            color: #ffc107;
            margin-bottom: 20px;
        }
        .error-container h1 {
            font-size: 72px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .error-container p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 30px;
        }
        .btn-home {
            background: #ffd700;
            color: #1a1a2e;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .btn-home:hover {
            background: #ffed4a;
            color: #1a1a2e;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <i class="fas fa-exclamation-circle"></i>
        <h1>500</h1>
        <h2>Internal Server Error</h2>
        <p>Something went wrong on our end. Please try again later or contact support if the problem persists.</p>
        <a href="<?php echo SITE_URL; ?>/" class="btn-home">
            <i class="fas fa-home me-2"></i> Back to Home
        </a>
    </div>
</body>
</html>