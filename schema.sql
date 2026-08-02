-- =============================================
-- ADPS Database Schema
-- =============================================

CREATE DATABASE IF NOT EXISTS adps;
USE adps;

-- =============================================
-- Users Table
-- =============================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'teacher') DEFAULT 'teacher',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255) NULL,
    remember_token VARCHAR(100) NULL,
    last_login DATETIME NULL,
    last_ip VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
);

-- =============================================
-- User Sessions
-- =============================================
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    device_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_user_active (user_id, is_active)
);

-- =============================================
-- Login History
-- =============================================
CREATE TABLE login_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    logout_time DATETIME NULL,
    status ENUM('success', 'failed') DEFAULT 'success',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_login (user_id, login_time)
);

-- =============================================
-- Departments
-- =============================================
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    hod_id INT NULL,
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    year_established YEAR,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    FOREIGN KEY (hod_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- =============================================
-- Teachers
-- =============================================
CREATE TABLE teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id VARCHAR(20) UNIQUE NOT NULL,
    staff_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    date_of_birth DATE,
    phone_primary VARCHAR(20) NOT NULL,
    phone_secondary VARCHAR(20),
    email VARCHAR(100) UNIQUE NOT NULL,
    nationality VARCHAR(50),
    permanent_address TEXT,
    current_address TEXT,
    department_id INT,
    qualification TEXT,
    position VARCHAR(50),
    employment_date DATE,
    experience_years INT DEFAULT 0,
    profile_photo VARCHAR(255),
    bio TEXT,
    skills TEXT,
    languages TEXT,
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    contract_type ENUM('permanent', 'contract', 'part_time') DEFAULT 'permanent',
    leave_start_date DATE NULL,
    leave_end_date DATE NULL,
    availability_status ENUM('available', 'unavailable') DEFAULT 'available',
    max_duties_per_week INT DEFAULT 5,
    max_duties_per_month INT DEFAULT 20,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_staff_number (staff_number),
    INDEX idx_email (email),
    INDEX idx_department (department_id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_status (status)
);

-- =============================================
-- Subjects
-- =============================================
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    department_id INT,
    description TEXT,
    credits INT DEFAULT 0,
    grade_level VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_department (department_id)
);

-- =============================================
-- Teacher Subjects (Many-to-Many)
-- =============================================
CREATE TABLE teacher_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    assigned_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_subject (teacher_id, subject_id)
);

-- =============================================
-- Classes
-- =============================================
CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    level VARCHAR(20) NOT NULL,
    section VARCHAR(10),
    capacity INT DEFAULT 40,
    academic_session VARCHAR(20) NOT NULL,
    class_teacher_id INT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    INDEX idx_session (academic_session)
);

-- =============================================
-- Duty Categories
-- =============================================
CREATE TABLE duty_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    icon VARCHAR(50) DEFAULT 'fas fa-tasks',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    duration_minutes INT DEFAULT 60,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code)
);

-- =============================================
-- Duties
-- =============================================
CREATE TABLE duties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    duty_code VARCHAR(50) UNIQUE NOT NULL,
    teacher_id INT NOT NULL,
    category_id INT NOT NULL,
    class_id INT NULL,
    duty_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(255),
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('pending', 'accepted', 'rejected', 'completed', 'missed', 'cancelled') DEFAULT 'pending',
    remarks TEXT,
    assigned_by INT NOT NULL,
    assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancelled_reason TEXT,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES duty_categories(id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(id),
    INDEX idx_teacher_date (teacher_id, duty_date),
    INDEX idx_status_date (status, duty_date),
    INDEX idx_date (duty_date),
    INDEX idx_priority (priority),
    UNIQUE KEY unique_teacher_duty (teacher_id, duty_date, start_time, end_time)
);

-- =============================================
-- Duty Swap Requests
-- =============================================
CREATE TABLE duty_swaps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    duty_id INT NOT NULL,
    requester_teacher_id INT NOT NULL,
    target_teacher_id INT NOT NULL,
    requested_date DATE NOT NULL,
    requested_start_time TIME NOT NULL,
    requested_end_time TIME NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved_by_admin', 'rejected_by_teacher', 'approved_by_teacher', 'completed', 'cancelled') DEFAULT 'pending',
    admin_approved_by INT NULL,
    teacher_approved_at DATETIME NULL,
    admin_approved_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (duty_id) REFERENCES duties(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (target_teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (admin_approved_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_duty (duty_id)
);

-- =============================================
-- Notifications
-- =============================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    priority ENUM('urgent', 'high', 'medium', 'low') DEFAULT 'medium',
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_priority (priority)
);

-- =============================================
-- Audit Logs
-- =============================================
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    old_values JSON,
    new_values JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_module (module),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =============================================
-- Settings
-- =============================================
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category VARCHAR(50) NOT NULL,
    key_name VARCHAR(100) NOT NULL,
    value TEXT,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category_key (category, key_name)
);

-- =============================================
-- Academic Sessions
-- =============================================
CREATE TABLE academic_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT FALSE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_current (is_current)
);

-- =============================================
-- Terms
-- =============================================
CREATE TABLE terms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    academic_session_id INT NOT NULL,
    term_number INT NOT NULL,
    term_name VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    holidays JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_term (academic_session_id, term_number)
);

-- =============================================
-- Attendance
-- =============================================
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    duty_id INT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'present',
    check_in_time TIME NULL,
    check_out_time TIME NULL,
    remarks TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (duty_id) REFERENCES duties(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    UNIQUE KEY unique_teacher_date (teacher_id, date)
);

-- =============================================
-- Leave Requests
-- =============================================
CREATE TABLE leave_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teacher_id INT NOT NULL,
    leave_type ENUM('sick', 'casual', 'vacation', 'study', 'other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
);

-- =============================================
-- Reports
-- =============================================
CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    parameters JSON,
    file_path VARCHAR(255),
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    download_count INT DEFAULT 0,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_type (report_type),
    INDEX idx_generated (generated_at)
);

-- =============================================
-- Initial Data Inserts
-- =============================================

-- Insert Default Duty Categories
INSERT INTO duty_categories (name, code, description, color, icon, priority) VALUES
('Morning Assembly', 'ASSEMBLY', 'Supervise morning assembly', '#28a745', 'fas fa-flag', 'normal'),
('Gate Duty', 'GATE', 'Monitor school gate entrance', '#17a2b8', 'fas fa-door-open', 'high'),
('Break Supervision', 'BREAK', 'Supervise students during break time', '#ffc107', 'fas fa-coffee', 'normal'),
('Examination Invigilation', 'EXAM', 'Invigilate during examinations', '#dc3545', 'fas fa-file-signature', 'urgent'),
('Laboratory Duty', 'LAB', 'Supervise laboratory activities', '#6f42c1', 'fas fa-flask', 'high'),
('Library Duty', 'LIBRARY', 'Supervise library activities', '#20c997', 'fas fa-book', 'normal'),
('Sports Duty', 'SPORTS', 'Supervise sports activities', '#fd7e14', 'fas fa-football-ball', 'normal');

-- Insert Default Settings
INSERT INTO settings (category, key_name, value, description) VALUES
('general', 'school_name', 'ADPS School', 'Name of the school'),
('general', 'school_motto', 'Excellence in Education', 'School motto'),
('duty', 'max_duties_per_week', '5', 'Maximum duties per teacher per week'),
('duty', 'max_duties_per_month', '20', 'Maximum duties per teacher per month'),
('duty', 'minimum_days_between', '2', 'Minimum days between duties'),
('duty', 'reminder_hours', '24', 'Hours before duty to send reminder'),
('notification', 'email_enabled', 'true', 'Enable email notifications'),
('notification', 'push_enabled', 'true', 'Enable push notifications');

-- Insert Super Admin (password: Admin@123)
INSERT INTO users (username, email, password, full_name, role, email_verified, status) VALUES
('admin', 'admin@adps.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin', TRUE, 'active');

-- Insert Sample Department
INSERT INTO departments (name, code, description, contact_email, contact_phone) VALUES
('Science Department', 'SCI', 'Department of Sciences', 'science@adps.com', '+1234567890');

-- Insert Sample Teacher
INSERT INTO teachers (teacher_id, staff_number, first_name, last_name, gender, email, phone_primary, department_id, position) VALUES
('T001', 'STF001', 'John', 'Doe', 'male', 'john.doe@adps.com', '+1234567890', 1, 'Senior Teacher');