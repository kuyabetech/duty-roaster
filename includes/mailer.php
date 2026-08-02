<?php
// =============================================
// Email Helper
// =============================================

require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host = SMTP_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = SMTP_USER;
        $this->mail->Password = SMTP_PASS;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = SMTP_PORT;
        
        $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $this->mail->isHTML(true);
    }
    
    public function send($to, $subject, $body, $altBody = null) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = $altBody ?? strip_tags($body);
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    public function sendTemplate($to, $template, $data) {
        $body = $this->renderTemplate($template, $data);
        return $this->send($to, $data['subject'] ?? '', $body);
    }
    
    private function renderTemplate($template, $data) {
        $templateFile = __DIR__ . '/../emails/' . $template . '.php';
        if (!file_exists($templateFile)) {
            return '';
        }
        
        extract($data);
        ob_start();
        include $templateFile;
        return ob_get_clean();
    }
}