<?php
// =============================================
// Password Reset Helper
// =============================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mailer.php';

class PasswordReset {
    private $db;
    private $mailer;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->mailer = new Mailer();
    }
    
    public function requestReset($email) {
        // Check if user exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email not found'];
        }
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        
        // Store token
        $stmt = $this->db->prepare("
            INSERT INTO password_resets (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $token,
            date('Y-m-d H:i:s', time() + 3600) // 1 hour
        ]);
        
        // Send email
        $resetLink = SITE_URL . '/auth/reset-password.php?token=' . $token;
        
        $subject = 'Reset Your Password - ' . SITE_NAME;
        $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #1a1a2e; color: #ffd700; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                    .btn { display: inline-block; background: #ffd700; color: #1a1a2e; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: 600; }
                    .footer { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>" . SITE_NAME . "</h1>
                        <p>Password Reset</p>
                    </div>
                    <div class='content'>
                        <h2>Forgot your password?</h2>
                        <p>We received a request to reset your password. Click the button below to create a new password:</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='{$resetLink}' class='btn'>Reset Password</a>
                        </p>
                        <p>Or copy and paste this link in your browser:</p>
                        <p><code style='background: #eee; padding: 10px; display: block; word-break: break-all;'>{$resetLink}</code></p>
                        <p><small>This link will expire in 1 hour.</small></p>
                        <p><small>If you didn't request a password reset, please ignore this email.</small></p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $this->mailer->send($email, $subject, $body);
        
        return ['success' => true, 'message' => 'Password reset link sent to your email'];
    }
    
    public function reset($token, $newPassword) {
        $stmt = $this->db->prepare("
            SELECT user_id FROM password_resets 
            WHERE token = ? AND expires_at > NOW() AND used = 0
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return ['success' => false, 'message' => 'Invalid or expired token'];
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $result['user_id']]);
        
        // Mark token as used
        $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        
        return ['success' => true, 'message' => 'Password reset successfully'];
    }
}