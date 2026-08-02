<?php
// =============================================
// Notification Model - Fixed
// =============================================

require_once __DIR__ . '/../config/database.php';

class Notification {
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
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        return $this->data;
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, priority, icon)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['user_id'],
            $data['type'],
            $data['title'],
            $data['message'],
            $data['link'] ?? null,
            $data['priority'] ?? 'medium',
            $data['icon'] ?? 'fas fa-bell'
        ]);
        
        if ($result) {
            $this->id = $this->db->lastInsertId();
            $this->load();
            return true;
        }
        return false;
    }
    
    public function markAsRead() {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    public function markAsUnread() {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 0, read_at = NULL WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    public function delete() {
        $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Get notifications by user with pagination - FIXED
     */
    public static function getByUser($userId, $filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if (isset($filters['is_read'])) {
            $sql .= " AND is_read = ?";
            $params[] = (int)$filters['is_read'];
        }
        
        if (isset($filters['priority'])) {
            $sql .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (isset($filters['type'])) {
            $sql .= " AND type = ?";
            $params[] = $filters['type'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (title LIKE ? OR message LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        // FIX: Cast LIMIT and OFFSET to integers and append directly
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
     * Get unread count for a user
     */
    public static function getUnreadCount($userId) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }
    
    /**
     * Get recent notifications
     */
    public static function getRecent($userId, $limit = 5) {
        return self::getByUser($userId, ['limit' => $limit, 'is_read' => 0]);
    }
    
    /**
     * Mark all notifications as read
     */
    public static function markAllAsRead($userId) {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Delete all notifications for a user
     */
    public static function deleteAll($userId) {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
    
    /**
     * Delete old notifications
     */
    public static function deleteOld($days = 30) {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        return $stmt->execute([$days]);
    }
    
    /**
     * Send notification to a user
     */
    public static function send($userId, $type, $title, $message, $link = null, $priority = 'medium', $icon = null) {
        $notification = new self();
        return $notification->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'priority' => $priority,
            'icon' => $icon ?? 'fas fa-bell'
        ]);
    }
    
    /**
     * Send notification to all users with a specific role
     */
    public static function sendToRole($role, $type, $title, $message, $link = null, $priority = 'medium') {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM users WHERE role = ? AND status = ?");
        $stmt->execute([$role, 'active']);
        $users = $stmt->fetchAll();
        
        $sent = 0;
        foreach ($users as $user) {
            if (self::send($user['id'], $type, $title, $message, $link, $priority)) {
                $sent++;
            }
        }
        return $sent;
    }
    
    /**
     * Send notification to all active users
     */
    public static function sendToAll($type, $title, $message, $link = null, $priority = 'medium') {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM users WHERE status = ?");
        $stmt->execute(['active']);
        $users = $stmt->fetchAll();
        
        $sent = 0;
        foreach ($users as $user) {
            if (self::send($user['id'], $type, $title, $message, $link, $priority)) {
                $sent++;
            }
        }
        return $sent;
    }
    
    /**
     * Count notifications with filters
     */
    public static function count($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM notifications WHERE 1=1";
        $params = [];
        
        if (isset($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['is_read'])) {
            $sql .= " AND is_read = ?";
            $params[] = (int)$filters['is_read'];
        }
        
        if (isset($filters['priority'])) {
            $sql .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (isset($filters['type'])) {
            $sql .= " AND type = ?";
            $params[] = $filters['type'];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getId() {
        return $this->id;
    }
}