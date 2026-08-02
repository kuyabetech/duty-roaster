<?php
// =============================================
// System Settings
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn() || !in_array($auth->getUser()['role'], ['admin', 'super_admin'])) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$pageTitle = 'System Settings';
$pageIcon = 'fas fa-cog';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Settings', 'active' => true]
];

$db = Database::getInstance();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_general':
                // Update general settings
                $settings = [
                    'school_name' => sanitizeInput($_POST['school_name'] ?? ''),
                    'school_motto' => sanitizeInput($_POST['school_motto'] ?? ''),
                    'school_address' => sanitizeInput($_POST['school_address'] ?? ''),
                    'school_phone' => sanitizeInput($_POST['school_phone'] ?? ''),
                    'school_email' => sanitizeInput($_POST['school_email'] ?? '')
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO settings (category, key_name, value) VALUES ('general', ?, ?) 
                                          ON DUPLICATE KEY UPDATE value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $success = 'General settings updated successfully!';
                break;
                
            case 'update_duty':
                $settings = [
                    'max_duties_per_week' => (int)$_POST['max_duties_per_week'] ?? 5,
                    'max_duties_per_month' => (int)$_POST['max_duties_per_month'] ?? 20,
                    'minimum_days_between' => (int)$_POST['minimum_days_between'] ?? 2,
                    'reminder_hours' => (int)$_POST['reminder_hours'] ?? 24
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO settings (category, key_name, value) VALUES ('duty', ?, ?) 
                                          ON DUPLICATE KEY UPDATE value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $success = 'Duty settings updated successfully!';
                break;
                
            case 'update_notification':
                $settings = [
                    'email_enabled' => isset($_POST['email_enabled']) ? 'true' : 'false',
                    'push_enabled' => isset($_POST['push_enabled']) ? 'true' : 'false',
                    'duty_reminder_emails' => isset($_POST['duty_reminder_emails']) ? 'true' : 'false',
                    'weekly_summary_emails' => isset($_POST['weekly_summary_emails']) ? 'true' : 'false',
                    'swap_notification_emails' => isset($_POST['swap_notification_emails']) ? 'true' : 'false'
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO settings (category, key_name, value) VALUES ('notification', ?, ?) 
                                          ON DUPLICATE KEY UPDATE value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $success = 'Notification settings updated successfully!';
                break;
        }
    }
}

// Get current settings
$settings = [];
$stmt = $db->query("SELECT category, key_name, value FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['category']][$row['key_name']] = $row['value'];
}

// Default values
$defaults = [
    'general' => [
        'school_name' => 'ADPS School',
        'school_motto' => 'Excellence in Education',
        'school_address' => '123 School Street, City',
        'school_phone' => '+1234567890',
        'school_email' => 'info@adps.com'
    ],
    'duty' => [
        'max_duties_per_week' => 5,
        'max_duties_per_month' => 20,
        'minimum_days_between' => 2,
        'reminder_hours' => 24
    ],
    'notification' => [
        'email_enabled' => 'true',
        'push_enabled' => 'true',
        'duty_reminder_emails' => 'true',
        'weekly_summary_emails' => 'true',
        'swap_notification_emails' => 'true'
    ]
];

// Merge settings with defaults
foreach ($defaults as $category => $items) {
    if (!isset($settings[$category])) {
        $settings[$category] = [];
    }
    foreach ($items as $key => $default) {
        if (!isset($settings[$category][$key])) {
            $settings[$category][$key] = $default;
        }
    }
}

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .settings-section {
        background: var(--bg-light);
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 20px;
    }
    .settings-section .section-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--text-dark);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
    }
    .settings-section .section-title i {
        color: var(--accent);
        margin-right: 8px;
    }
    
    .form-check-input:checked {
        background-color: var(--accent);
        border-color: var(--accent);
    }
    
    .settings-help {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 4px;
    }
    
    @media (max-width: 768px) {
        .settings-section {
            padding: 15px;
        }
    }
</style>

<div class="row">
    <div class="col-lg-12">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- General Settings -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-globe me-2"></i> General Settings
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="action" value="update_general">
                    
                    <div class="settings-section">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">School Name</label>
                                <input type="text" name="school_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['general']['school_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">School Motto</label>
                                <input type="text" name="school_motto" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['general']['school_motto'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">School Address</label>
                                <input type="text" name="school_address" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['general']['school_address'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">School Phone</label>
                                <input type="text" name="school_phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['general']['school_phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">School Email</label>
                                <input type="email" name="school_email" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['general']['school_email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-accent">
                        <i class="fas fa-save me-2"></i> Save General Settings
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Duty Settings -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-tasks me-2"></i> Duty Settings
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="action" value="update_duty">
                    
                    <div class="settings-section">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Max Duties Per Week</label>
                                <input type="number" name="max_duties_per_week" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['duty']['max_duties_per_week'] ?? 5); ?>" min="1" max="20">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Max Duties Per Month</label>
                                <input type="number" name="max_duties_per_month" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['duty']['max_duties_per_month'] ?? 20); ?>" min="1" max="50">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Min Days Between Duties</label>
                                <input type="number" name="minimum_days_between" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['duty']['minimum_days_between'] ?? 2); ?>" min="0" max="7">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Reminder Hours Before</label>
                                <input type="number" name="reminder_hours" class="form-control" 
                                       value="<?php echo htmlspecialchars($settings['duty']['reminder_hours'] ?? 24); ?>" min="1" max="72">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-accent">
                        <i class="fas fa-save me-2"></i> Save Duty Settings
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Notification Settings -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bell me-2"></i> Notification Settings
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="action" value="update_notification">
                    
                    <div class="settings-section">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="email_enabled" 
                                           <?php echo ($settings['notification']['email_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Enable Email Notifications</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="push_enabled" 
                                           <?php echo ($settings['notification']['push_enabled'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Enable Push Notifications</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="duty_reminder_emails" 
                                           <?php echo ($settings['notification']['duty_reminder_emails'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Send Duty Reminder Emails</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="weekly_summary_emails" 
                                           <?php echo ($settings['notification']['weekly_summary_emails'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Send Weekly Summary Emails</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="swap_notification_emails" 
                                           <?php echo ($settings['notification']['swap_notification_emails'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Send Swap Request Notification Emails</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-accent">
                        <i class="fas fa-save me-2"></i> Save Notification Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>