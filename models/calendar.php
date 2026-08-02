<?php
// =============================================
// Duty Swap Model - Fixed
// =============================================

require_once __DIR__ . '/../config/database.php';

class Swap {
    private $db;
    private $id;
    private $data;
    
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }
    
    public function load() {
        $stmt = $this->db->prepare("
            SELECT s.*,
                   r.first_name as requester_first, r.last_name as requester_last,
                   t.first_name as target_first, t.last_name as target_last,
                   d.duty_code, d.duty_date, d.start_time, d.end_time,
                   c.name as category_name,
                   u.full_name as admin_name
            FROM duty_swaps s
            LEFT JOIN teachers r ON s.requester_teacher_id = r.id
            LEFT JOIN teachers t ON s.target_teacher_id = t.id
            LEFT JOIN duties d ON s.duty_id = d.id
            LEFT JOIN duty_categories c ON d.category_id = c.id
            LEFT JOIN users u ON s.admin_approved_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        return $this->data;
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO duty_swaps (
                duty_id, requester_teacher_id, target_teacher_id,
                requested_date, requested_start_time, requested_end_time,
                reason, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['duty_id'],
            $data['requester_teacher_id'],
            $data['target_teacher_id'],
            $data['requested_date'],
            $data['requested_start_time'],
            $data['requested_end_time'],
            $data['reason'] ?? null,
            SWAP_PENDING
        ]);
        
        if ($result) {
            $this->id = $this->db->lastInsertId();
            $this->load();
            
            // Create notifications
            $this->createNotifications();
            
            return true;
        }
        return false;
    }
    
    public function update($data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['status', 'reason'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['status'])) {
            switch ($data['status']) {
                case SWAP_APPROVED_BY_ADMIN:
                    $fields[] = "admin_approved_by = ?";
                    $fields[] = "admin_approved_at = NOW()";
                    $params[] = $data['admin_id'];
                    break;
                case SWAP_APPROVED_BY_TEACHER:
                    $fields[] = "teacher_approved_at = NOW()";
                    break;
                case SWAP_COMPLETED:
                    $fields[] = "completed_at = NOW()";
                    break;
                case SWAP_CANCELLED:
                    $fields[] = "cancelled_at = NOW()";
                    break;
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $this->id;
        $sql = "UPDATE duty_swaps SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $this->load();
            
            // Update duty if swap is completed
            if (isset($data['status']) && $data['status'] === SWAP_COMPLETED) {
                $this->completeSwap();
            }
            
            return true;
        }
        return false;
    }
    
    private function completeSwap() {
        // Get original duty
        $duty = new Duty($this->data['duty_id']);
        if (!$duty->getData()) return;
        
        // Update duty to target teacher
        $duty->update([
            'teacher_id' => $this->data['target_teacher_id']
        ]);
        
        // Create notification for both teachers
        $this->notifySwapComplete();
    }
    
    private function createNotifications() {
        // Notify target teacher
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, priority)
            SELECT u.id, 'swap_request', ?, ?, ?, ?
            FROM users u
            JOIN teachers t ON u.email = t.email
            WHERE t.id = ?
        ");
        
        $title = 'New Swap Request';
        $message = "You have received a swap request for duty on " . 
                   date('M d, Y', strtotime($this->data['duty_date']));
        $link = SITE_URL . '/views/swaps/view.php?id=' . $this->id;
        
        $stmt->execute([
            $title,
            $message,
            $link,
            NOTIFY_HIGH,
            $this->data['target_teacher_id']
        ]);
        
        // Notify admins
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, priority)
            SELECT u.id, 'swap_request', ?, ?, ?, ?
            FROM users u
            WHERE u.role IN (?, ?)
        ");
        
        $stmt->execute([
            'New Swap Request Pending',
            'A swap request has been submitted and needs approval',
            $link,
            NOTIFY_URGENT,
            ROLE_ADMIN,
            ROLE_SUPER_ADMIN
        ]);
    }
    
    private function notifySwapComplete() {
        // Notify requester
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, priority)
            SELECT u.id, 'swap_completed', ?, ?, ?, ?
            FROM users u
            JOIN teachers t ON u.email = t.email
            WHERE t.id = ?
        ");
        
        $title = 'Swap Completed';
        $message = "Your swap request has been completed successfully";
        $link = SITE_URL . '/views/swaps/view.php?id=' . $this->id;
        
        $stmt->execute([
            $title,
            $message,
            $link,
            NOTIFY_HIGH,
            $this->data['requester_teacher_id']
        ]);
    }
    
    /**
     * Get all swaps with pagination support
     */
    public static function all($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT s.*,
                       r.first_name as requester_first, r.last_name as requester_last,
                       t.first_name as target_first, t.last_name as target_last,
                       d.duty_code, d.duty_date, d.start_time, d.end_time,
                       c.name as category_name
                FROM duty_swaps s
                LEFT JOIN teachers r ON s.requester_teacher_id = r.id
                LEFT JOIN teachers t ON s.target_teacher_id = t.id
                LEFT JOIN duties d ON s.duty_id = d.id
                LEFT JOIN duty_categories c ON d.category_id = c.id
                WHERE 1=1";
        $params = [];
        
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND s.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND s.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        if (isset($filters['requester_id'])) {
            $sql .= " AND s.requester_teacher_id = ?";
            $params[] = $filters['requester_id'];
        }
        
        if (isset($filters['target_id'])) {
            $sql .= " AND s.target_teacher_id = ?";
            $params[] = $filters['target_id'];
        }
        
        if (isset($filters['teacher_id'])) {
            $sql .= " AND (s.requester_teacher_id = ? OR s.target_teacher_id = ?)";
            $params[] = $filters['teacher_id'];
            $params[] = $filters['teacher_id'];
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND s.requested_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND s.requested_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (r.first_name LIKE ? OR r.last_name LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        // Fix: Properly handle LIMIT and OFFSET as integers
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (isset($filters['offset']) && is_numeric($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Count swaps with filters
     */
    public static function count($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM duty_swaps s WHERE 1=1";
        $params = [];
        
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND s.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND s.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        if (isset($filters['requester_id'])) {
            $sql .= " AND s.requester_teacher_id = ?";
            $params[] = $filters['requester_id'];
        }
        
        if (isset($filters['target_id'])) {
            $sql .= " AND s.target_teacher_id = ?";
            $params[] = $filters['target_id'];
        }
        
        if (isset($filters['teacher_id'])) {
            $sql .= " AND (s.requester_teacher_id = ? OR s.target_teacher_id = ?)";
            $params[] = $filters['teacher_id'];
            $params[] = $filters['teacher_id'];
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND s.requested_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND s.requested_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (r.first_name LIKE ? OR r.last_name LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    public static function getPending($limit = null) {
        $filters = ['status' => SWAP_PENDING];
        if ($limit) {
            $filters['limit'] = $limit;
        }
        return self::all($filters);
    }
    
    public static function getByTeacher($teacherId, $filters = []) {
        $filters['teacher_id'] = $teacherId;
        return self::all($filters);
    }
    
    public static function getByDuty($dutyId) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM duty_swaps WHERE duty_id = ? ORDER BY created_at DESC");
        $stmt->execute([$dutyId]);
        return $stmt->fetchAll();
    }
    
    public function approveByAdmin($adminId) {
        return $this->update([
            'status' => SWAP_APPROVED_BY_ADMIN,
            'admin_id' => $adminId
        ]);
    }
    
    public function approveByTeacher() {
        return $this->update(['status' => SWAP_APPROVED_BY_TEACHER]);
    }
    
    public function rejectByTeacher() {
        return $this->update(['status' => SWAP_REJECTED_BY_TEACHER]);
    }
    
    public function complete() {
        return $this->update(['status' => SWAP_COMPLETED]);
    }
    
    public function cancel() {
        return $this->update(['status' => SWAP_CANCELLED]);
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getId() {
        return $this->id;
    }
}