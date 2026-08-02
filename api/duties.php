<?php
// =============================================
// API - Duties
// =============================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Duty.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Initialize authentication
$auth = new Auth();

// Check if user is authenticated
if (!$auth->isLoggedIn()) {
    http_response_code(HTTP_UNAUTHORIZED);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGet($action);
        break;
    case 'POST':
        handlePost($action);
        break;
    case 'PUT':
        handlePut($action);
        break;
    case 'DELETE':
        handleDelete($action);
        break;
    default:
        http_response_code(HTTP_METHOD_NOT_ALLOWED);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGet($action) {
    $dutyModel = new Duty();
    
    switch ($action) {
        case 'list':
            $filters = $_GET;
            $duties = Duty::all($filters);
            echo json_encode(['success' => true, 'data' => $duties]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(HTTP_BAD_REQUEST);
                echo json_encode(['error' => 'ID required']);
                return;
            }
            $duty = new Duty($id);
            if (!$duty->getData()) {
                http_response_code(HTTP_NOT_FOUND);
                echo json_encode(['error' => 'Duty not found']);
                return;
            }
            echo json_encode(['success' => true, 'data' => $duty->getData()]);
            break;
            
        case 'statistics':
            $filters = $_GET;
            $stats = Duty::getStatistics($filters);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            http_response_code(HTTP_BAD_REQUEST);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePost($action) {
    global $auth;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(HTTP_BAD_REQUEST);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    switch ($action) {
        case 'create':
            $data['assigned_by'] = $auth->getUserId();
            $duty = new Duty();
            if ($duty->create($data)) {
                http_response_code(HTTP_CREATED);
                echo json_encode(['success' => true, 'data' => $duty->getData()]);
            } else {
                http_response_code(HTTP_INTERNAL_SERVER_ERROR);
                echo json_encode(['error' => 'Failed to create duty']);
            }
            break;
            
        case 'accept':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(HTTP_BAD_REQUEST);
                echo json_encode(['error' => 'ID required']);
                return;
            }
            $duty = new Duty($id);
            if (!$duty->getData()) {
                http_response_code(HTTP_NOT_FOUND);
                echo json_encode(['error' => 'Duty not found']);
                return;
            }
            if ($duty->accept()) {
                echo json_encode(['success' => true, 'data' => $duty->getData()]);
            } else {
                http_response_code(HTTP_INTERNAL_SERVER_ERROR);
                echo json_encode(['error' => 'Failed to accept duty']);
            }
            break;
            
        case 'reject':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(HTTP_BAD_REQUEST);
                echo json_encode(['error' => 'ID required']);
                return;
            }
            $duty = new Duty($id);
            if (!$duty->getData()) {
                http_response_code(HTTP_NOT_FOUND);
                echo json_encode(['error' => 'Duty not found']);
                return;
            }
            if ($duty->reject($data['reason'] ?? null)) {
                echo json_encode(['success' => true, 'data' => $duty->getData()]);
            } else {
                http_response_code(HTTP_INTERNAL_SERVER_ERROR);
                echo json_encode(['error' => 'Failed to reject duty']);
            }
            break;
            
        case 'complete':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(HTTP_BAD_REQUEST);
                echo json_encode(['error' => 'ID required']);
                return;
            }
            $duty = new Duty($id);
            if (!$duty->getData()) {
                http_response_code(HTTP_NOT_FOUND);
                echo json_encode(['error' => 'Duty not found']);
                return;
            }
            if ($duty->complete()) {
                echo json_encode(['success' => true, 'data' => $duty->getData()]);
            } else {
                http_response_code(HTTP_INTERNAL_SERVER_ERROR);
                echo json_encode(['error' => 'Failed to complete duty']);
            }
            break;
            
        case 'schedule':
            $params = $data;
            $params['assigned_by'] = $auth->getUserId();
            $result = Duty::autoGenerate($params);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(HTTP_BAD_REQUEST);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePut($action) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(HTTP_BAD_REQUEST);
        echo json_encode(['error' => 'Invalid data']);
        return;
    }
    
    $id = $data['id'] ?? null;
    if (!$id) {
        http_response_code(HTTP_BAD_REQUEST);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    
    $duty = new Duty($id);
    if (!$duty->getData()) {
        http_response_code(HTTP_NOT_FOUND);
        echo json_encode(['error' => 'Duty not found']);
        return;
    }
    
    if ($duty->update($data)) {
        echo json_encode(['success' => true, 'data' => $duty->getData()]);
    } else {
        http_response_code(HTTP_INTERNAL_SERVER_ERROR);
        echo json_encode(['error' => 'Failed to update duty']);
    }
}

function handleDelete($action) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(HTTP_BAD_REQUEST);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    
    $duty = new Duty($id);
    if (!$duty->getData()) {
        http_response_code(HTTP_NOT_FOUND);
        echo json_encode(['error' => 'Duty not found']);
        return;
    }
    
    if ($duty->delete()) {
        echo json_encode(['success' => true, 'message' => 'Duty deleted']);
    } else {
        http_response_code(HTTP_INTERNAL_SERVER_ERROR);
        echo json_encode(['error' => 'Failed to delete duty']);
    }
}