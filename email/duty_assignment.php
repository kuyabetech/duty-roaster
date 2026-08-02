<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duty Assignment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #1a1a2e;
            color: #fff;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .header h1 {
            margin: 0;
            color: #ffd700;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .duty-details {
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #ffd700;
        }
        .btn {
            display: inline-block;
            background: #ffd700;
            color: #1a1a2e;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
        }
        .footer {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo SITE_NAME; ?></h1>
        <p>Duty Assignment Notification</p>
    </div>
    
    <div class="content">
        <h2>Hello <?php echo $name; ?>,</h2>
        <p>You have been assigned a new duty.</p>
        
        <div class="duty-details">
            <table>
                <tr>
                    <td><strong>Duty:</strong></td>
                    <td><?php echo $category; ?></td>
                </tr>
                <tr>
                    <td><strong>Date:</strong></td>
                    <td><?php echo $date; ?></td>
                </tr>
                <tr>
                    <td><strong>Time:</strong></td>
                    <td><?php echo $time; ?></td>
                </tr>
                <tr>
                    <td><strong>Location:</strong></td>
                    <td><?php echo $location ?? 'Not specified'; ?></td>
                </tr>
                <tr>
                    <td><strong>Priority:</strong></td>
                    <td><?php echo $priority ?? 'Normal'; ?></td>
                </tr>
            </table>
        </div>
        
        <p>Please log in to the system to accept or reject this duty assignment.</p>
        
        <p style="text-align: center;">
            <a href="<?php echo SITE_URL; ?>/duties/view.php?id=<?php echo $duty_id; ?>" class="btn">
                View Duty Details
            </a>
        </p>
        
        <p><small>If you have any questions, please contact your administrator.</small></p>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        <p>This is an automated message, please do not reply.</p>
    </div>
</body>
</html>