<?php
// =============================================
// Swap Controller
// =============================================

require_once __DIR__ . '/../models/Swap.php';
require_once __DIR__ . '/../models/Duty.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Audit.php';
require_once __DIR__ . '/../includes/auth.php';

class SwapController {
    private $auth;
    private $audit;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->audit = new Audit();
    }
    
    public function index($filters = []) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        // If user is teacher, only show their swaps
        $user = $this->auth->getUser();
        if ($user['role'] === ROLE_TEACHER) {
            // Get teacher ID from user email
            $teacher = Teacher::findByEmail($user['email']);
            if ($teacher) {
                $filters['requester_id'] = $teacher['id'];
            }
        }
        
        $swaps = Swap::all($filters);
        return ['success' => true, 'data' => $swaps];
    }
    
    public function create($data) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $required = ['duty_id', 'target_teacher_id', 'requested_date', 'requested_start_time', 'requested_end_time'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
            }
        }
        
        // Get current user's teacher ID
        $user = $this->auth->getUser();
        $teacher = Teacher::findByEmail($user['email']);
        if (!$teacher) {
            return ['success' => false, 'message' => 'Teacher record not found'];
        }
        
        // Check if duty belongs to this teacher
        $duty = new Duty($data['duty_id']);
        if (!$duty->getData()) {
            return ['success' => false, 'message' => 'Duty not found'];
        }
        
        if ($duty->getData()['teacher_id'] != $teacher['id']) {
            return ['success' => false, 'message' => 'You can only swap your own duties'];
        }
        
        // Check if target teacher exists
        $targetTeacher = new Teacher($data['target_teacher_id']);
        if (!$targetTeacher->getData()) {
            return ['success' => false, 'message' => 'Target teacher not found'];
        }
        
        // Check if target teacher is available
        if ($targetTeacher->isOnLeave($data['requested_date'])) {
            return ['success' => false, 'message' => 'Target teacher is on leave on the requested date'];
        }
        
        if (!Duty::checkConflicts($data['target_teacher_id'], $data['requested_date'], 
                                  $data['requested_start_time'], $data['requested_end_time'])) {
            return ['success' => false, 'message' => 'Target teacher has a conflict at the requested time'];
        }
        
        $data['requester_teacher_id'] = $teacher['id'];
        $swap = new Swap();
        $result = $swap->create($data);
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'create_swap',
                'swaps',
                'Created swap request for duty: ' . $duty->getData()['duty_code'],
                null,
                $swap->getData()
            );
            
            return ['success' => true, 'data' => $swap->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to create swap request'];
    }
    
    public function approveByAdmin($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        if (!$this->auth->hasRole(ROLE_ADMIN) && !$this->auth->hasRole(ROLE_SUPER_ADMIN)) {
            return ['success' => false, 'message' => 'Permission denied'];
        }
        
        $swap = new Swap($id);
        if (!$swap->getData()) {
            return ['success' => false, 'message' => 'Swap request not found'];
        }
        
        if ($swap->getData()['status'] !== SWAP_PENDING) {
            return ['success' => false, 'message' => 'Swap request is not pending'];
        }
        
        $result = $swap->approveByAdmin($this->auth->getUserId());
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'approve_swap_admin',
                'swaps',
                'Approved swap request by admin: ' . $swap->getData()['duty_code'],
                null,
                $swap->getData()
            );
            
            return ['success' => true, 'data' => $swap->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to approve swap request'];
    }
    
    public function approveByTeacher($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $swap = new Swap($id);
        if (!$swap->getData()) {
            return ['success' => false, 'message' => 'Swap request not found'];
        }
        
        // Check if user is the target teacher
        $user = $this->auth->getUser();
        $teacher = Teacher::findByEmail($user['email']);
        if (!$teacher || $teacher['id'] != $swap->getData()['target_teacher_id']) {
            return ['success' => false, 'message' => 'You are not the target teacher for this swap'];
        }
        
        if ($swap->getData()['status'] !== SWAP_APPROVED_BY_ADMIN) {
            return ['success' => false, 'message' => 'Swap request has not been approved by admin yet'];
        }
        
        $result = $swap->approveByTeacher();
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'approve_swap_teacher',
                'swaps',
                'Approved swap request by teacher: ' . $swap->getData()['duty_code'],
                null,
                $swap->getData()
            );
            
            return ['success' => true, 'data' => $swap->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to approve swap request'];
    }
    
    public function rejectByTeacher($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $swap = new Swap($id);
        if (!$swap->getData()) {
            return ['success' => false, 'message' => 'Swap request not found'];
        }
        
        // Check if user is the target teacher
        $user = $this->auth->getUser();
        $teacher = Teacher::findByEmail($user['email']);
        if (!$teacher || $teacher['id'] != $swap->getData()['target_teacher_id']) {
            return ['success' => false, 'message' => 'You are not the target teacher for this swap'];
        }
        
        if ($swap->getData()['status'] !== SWAP_APPROVED_BY_ADMIN) {
            return ['success' => false, 'message' => 'Swap request has not been approved by admin yet'];
        }
        
        $result = $swap->rejectByTeacher();
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'reject_swap',
                'swaps',
                'Rejected swap request by teacher: ' . $swap->getData()['duty_code'],
                null,
                $swap->getData()
            );
            
            return ['success' => true, 'data' => $swap->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to reject swap request'];
    }
    
    public function complete($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        if (!$this->auth->hasRole(ROLE_ADMIN) && !$this->auth->hasRole(ROLE_SUPER_ADMIN)) {
            return ['success' => false, 'message' => 'Permission denied'];
        }
        
        $swap = new Swap($id);
        if (!$swap->getData()) {
            return ['success' => false, 'message' => 'Swap request not found'];
        }
        
        if ($swap->getData()['status'] !== SWAP_APPROVED_BY_TEACHER) {
            return ['success' => false, 'message' => 'Swap request has not been approved by teacher yet'];
        }
        
        $result = $swap->complete();
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'complete_swap',
                'swaps',
                'Completed swap request: ' . $swap->getData()['duty_code'],
                null,
                $swap->getData()
            );
            
            return ['success' => true, 'data' => $swap->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to complete swap'];
    }
    
    public function cancel($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $swap = new Swap($id);
        if (!$swap->getData()) {
            return ['success' => false, 'message' => 'Swap request not found'];
        }
        
        // Check if user is requester or admin
        $user = $this->auth->getUser();
        $teacher = Teacher::findByEmail($user['email']);
        $isRequester = $teacher && $teacher['id'] == $swap->getData()['requester_teacher_id'];
        $isAdmin = $this->auth->hasRole(ROLE_ADMIN) || $this->auth->hasRole(ROLE_SUPER_ADMIN);
        
        if (!$isRequester && !$isAdmin) {
            return ['success' => false, 'message' => 'You can only cancel your own swap requests'];
        }
        
        if (!in_array($swap->getData()['status'], [SWAP_PENDING, SWAP_APPROVED_BY_ADMIN, SWAP_APPROVED_BY_TEACHER])) {
            return ['success' => false, 'message' => 'This swap request cannot be cancelled'];
        }
        
        $result = $swap->cancel();
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'cancel_swap',
                'swaps',
                'Cancelled swap request: ' . $swap->getData()['duty_code'],
                null,
                $swap->getData()
            );
            
            return ['success' => true, 'data' => $swap->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to cancel swap request'];
    }
    
    public function get($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $swap = new Swap($id);
        if (!$swap->getData()) {
            return ['success' => false, 'message' => 'Swap request not found'];
        }
        
        return ['success' => true, 'data' => $swap->getData()];
    }
}