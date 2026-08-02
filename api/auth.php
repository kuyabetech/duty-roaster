<?php
// =============================================
// API - Authentication
// =============================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../controllers/AuthController.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$authController = new AuthController();

switch ($method) {
    case 'POST':
        switch ($action) {
            case 'login':
                $result = $authController->login();
                echo json_encode($result);
                break;
                
            case 'register':
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $authController->register($data);
                echo json_encode($result);
                break;
                
            case 'logout':
                $result = $authController->logout();
                echo json_encode($result);
                break;
                
            case 'verify-email':
                $token = $_POST['token'] ?? '';
                $result = $authController->verifyEmail($token);
                echo json_encode($result);
                break;
                
            case 'forgot-password':
                $email = $_POST['email'] ?? '';
                $result = $authController->requestPasswordReset($email);
                echo json_encode($result);
                break;
                
            case 'reset-password':
                $token = $_POST['token'] ?? '';
                $password = $_POST['password'] ?? '';
                $result = $authController->resetPassword($token, $password);
                echo json_encode($result);
                break;
                
            default:
                http_response_code(HTTP_BAD_REQUEST);
                echo json_encode(['error' => 'Invalid action']);
        }
        break;
        
    case 'GET':
        switch ($action) {
            case 'user':
                if ($authController->isLoggedIn()) {
                    echo json_encode(['success' => true, 'data' => $authController->getCurrentUser()]);
                } else {
                    http_response_code(HTTP_UNAUTHORIZED);
                    echo json_encode(['error' => 'Unauthorized']);
                }
                break;
                
            case 'check':
                echo json_encode(['logged_in' => $authController->isLoggedIn()]);
                break;
                
            default:
                http_response_code(HTTP_BAD_REQUEST);
                echo json_encode(['error' => 'Invalid action']);
        }
        break;
        
    default:
        http_response_code(HTTP_METHOD_NOT_ALLOWED);
        echo json_encode(['error' => 'Method not allowed']);
}