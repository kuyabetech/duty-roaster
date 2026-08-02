<?php
// =============================================
// Email Verification Helper
// =============================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/mailer.php';

class EmailVerification {
    private $db;
    private $mailer;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->mailer = new Mailer();
    }
    
    public function sendVerificationEmail($userId, $email) {
        // Generate token
        $token = bin2hex(random_bytes(32));
        
        // Store token
        $stmt = $this->db->prepare("
            INSERT INTO email_verifications (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $token,
            date('Y-m-d H:i:s', time() + 86400) // 24 hours
        ]);
        
        // Send email
        $verificationLink = SITE_URL . '/auth/verify-email.php?token=' . $token;
        
        $subject = 'Verify Your Email - ' . SITE_NAME;
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
                        <p>Email Verification</p>
                    </div>
                    <div class='content'>
                        <h2>Welcome!</h2>
                        <p>Thank you for registering. Please verify your email address by clicking the button below:</p>
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='{$verificationLink}' class='btn'>Verify Email</a>
                        </p>
                        <p>Or copy and paste this link in your browser:</p>
                        <p><code style='background: #eee; padding: 10px; display: block; word-break: break-all;'>{$verificationLink}</code></p>
                        <p><small>This link will expire in 24 hours.</small></p>
                        <p><small>If you didn't create an account, please ignore this email.</small></p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        return $this->mailer->send($email, $subject, $body);
    }
    
    public function verify($token) {
        $stmt = $this->db->prepare("
            SELECT user_id FROM email_verifications 
            WHERE token = ? AND expires_at > NOW() AND used = 0
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return ['success' => false, 'message' => 'Invalid or expired verification token'];
        }
        
        // Mark email as verified
        $stmt = $this->db->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
        $stmt->execute([$result['user_id']]);
        
        // Mark token as used
        $stmt = $this->db->prepare("UPDATE email_verifications SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        
        return ['success' => true, 'message' => 'Email verified successfully'];
    }
    
    public function resend($email) {
        // Check if user exists and is not verified
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND email_verified = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'No unverified account found with this email'];
        }
        
        return $this->sendVerificationEmail($user['id'], $email);
    }
}