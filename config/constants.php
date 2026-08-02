<?php
// =============================================
// Application Constants
// =============================================

// User Roles
if (!defined('ROLE_SUPER_ADMIN')) define('ROLE_SUPER_ADMIN', 'super_admin');
if (!defined('ROLE_ADMIN')) define('ROLE_ADMIN', 'admin');
if (!defined('ROLE_TEACHER')) define('ROLE_TEACHER', 'teacher');

// User Statuses
if (!defined('STATUS_ACTIVE')) define('STATUS_ACTIVE', 'active');
if (!defined('STATUS_INACTIVE')) define('STATUS_INACTIVE', 'inactive');
if (!defined('STATUS_SUSPENDED')) define('STATUS_SUSPENDED', 'suspended');
if (!defined('STATUS_ON_LEAVE')) define('STATUS_ON_LEAVE', 'on_leave');

// Duty Statuses
if (!defined('DUTY_PENDING')) define('DUTY_PENDING', 'pending');
if (!defined('DUTY_ACCEPTED')) define('DUTY_ACCEPTED', 'accepted');
if (!defined('DUTY_REJECTED')) define('DUTY_REJECTED', 'rejected');
if (!defined('DUTY_COMPLETED')) define('DUTY_COMPLETED', 'completed');
if (!defined('DUTY_MISSED')) define('DUTY_MISSED', 'missed');
if (!defined('DUTY_CANCELLED')) define('DUTY_CANCELLED', 'cancelled');

// Duty Priorities
if (!defined('PRIORITY_LOW')) define('PRIORITY_LOW', 'low');
if (!defined('PRIORITY_NORMAL')) define('PRIORITY_NORMAL', 'normal');
if (!defined('PRIORITY_HIGH')) define('PRIORITY_HIGH', 'high');
if (!defined('PRIORITY_URGENT')) define('PRIORITY_URGENT', 'urgent');

// Swap Statuses
if (!defined('SWAP_PENDING')) define('SWAP_PENDING', 'pending');
if (!defined('SWAP_APPROVED_BY_ADMIN')) define('SWAP_APPROVED_BY_ADMIN', 'approved_by_admin');
if (!defined('SWAP_REJECTED_BY_TEACHER')) define('SWAP_REJECTED_BY_TEACHER', 'rejected_by_teacher');
if (!defined('SWAP_APPROVED_BY_TEACHER')) define('SWAP_APPROVED_BY_TEACHER', 'approved_by_teacher');
if (!defined('SWAP_COMPLETED')) define('SWAP_COMPLETED', 'completed');
if (!defined('SWAP_CANCELLED')) define('SWAP_CANCELLED', 'cancelled');

// Notification Priorities
if (!defined('NOTIFY_URGENT')) define('NOTIFY_URGENT', 'urgent');
if (!defined('NOTIFY_HIGH')) define('NOTIFY_HIGH', 'high');
if (!defined('NOTIFY_MEDIUM')) define('NOTIFY_MEDIUM', 'medium');
if (!defined('NOTIFY_LOW')) define('NOTIFY_LOW', 'low');

// Gender
if (!defined('GENDER_MALE')) define('GENDER_MALE', 'male');
if (!defined('GENDER_FEMALE')) define('GENDER_FEMALE', 'female');
if (!defined('GENDER_OTHER')) define('GENDER_OTHER', 'other');

// Contract Types - ADD THESE
if (!defined('CONTRACT_PERMANENT')) define('CONTRACT_PERMANENT', 'permanent');
if (!defined('CONTRACT_CONTRACT')) define('CONTRACT_CONTRACT', 'contract');
if (!defined('CONTRACT_PART_TIME')) define('CONTRACT_PART_TIME', 'part_time');

// Attendance Statuses
if (!defined('ATTENDANCE_PRESENT')) define('ATTENDANCE_PRESENT', 'present');
if (!defined('ATTENDANCE_ABSENT')) define('ATTENDANCE_ABSENT', 'absent');
if (!defined('ATTENDANCE_LATE')) define('ATTENDANCE_LATE', 'late');
if (!defined('ATTENDANCE_EXCUSED')) define('ATTENDANCE_EXCUSED', 'excused');

// Leave Types
if (!defined('LEAVE_SICK')) define('LEAVE_SICK', 'sick');
if (!defined('LEAVE_CASUAL')) define('LEAVE_CASUAL', 'casual');
if (!defined('LEAVE_VACATION')) define('LEAVE_VACATION', 'vacation');
if (!defined('LEAVE_STUDY')) define('LEAVE_STUDY', 'study');
if (!defined('LEAVE_OTHER')) define('LEAVE_OTHER', 'other');

// Session
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600);
if (!defined('DATE_FORMAT')) define('DATE_FORMAT', 'Y-m-d');
if (!defined('DATETIME_FORMAT')) define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// HTTP Response Codes
if (!defined('HTTP_OK')) define('HTTP_OK', 200);
if (!defined('HTTP_CREATED')) define('HTTP_CREATED', 201);
if (!defined('HTTP_BAD_REQUEST')) define('HTTP_BAD_REQUEST', 400);
if (!defined('HTTP_UNAUTHORIZED')) define('HTTP_UNAUTHORIZED', 401);
if (!defined('HTTP_FORBIDDEN')) define('HTTP_FORBIDDEN', 403);
if (!defined('HTTP_NOT_FOUND')) define('HTTP_NOT_FOUND', 404);
if (!defined('HTTP_INTERNAL_SERVER_ERROR')) define('HTTP_INTERNAL_SERVER_ERROR', 500);

// File Upload Constants
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', __DIR__ . '/../uploads/');
if (!defined('PROFILE_PHOTO_PATH')) define('PROFILE_PHOTO_PATH', UPLOAD_PATH . 'profiles/');
if (!defined('REPORT_PATH')) define('REPORT_PATH', UPLOAD_PATH . 'reports/');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5242880); // 5MB
if (!defined('ALLOWED_IMAGE_TYPES')) define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);