<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/Teacher.php';
require_once __DIR__ . '/../../models/DutyCategory.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || $auth->getUser()['role'] === 'teacher') {
    redirect(SITE_URL . '/views/duties/');
}

$user = $auth->getUser();
$pageTitle = 'Assign Duty';

$teachers = Teacher::all(['status' => STATUS_ACTIVE]);
$categories = DutyCategory::getActive();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $data = [
            'teacher_id' => $_POST['teacher_id'] ?? '',
            'category_id' => $_POST['category_id'] ?? '',
            'class_id' => $_POST['class_id'] ?? null,
            'duty_date' => $_POST['duty_date'] ?? '',
            'start_time' => $_POST['start_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'location' => $_POST['location'] ?? '',
            'priority' => $_POST['priority'] ?? PRIORITY_NORMAL,
            'remarks' => $_POST['remarks'] ?? ''
        ];
        
        // Validate
        if (empty($data['teacher_id']) || empty($data['category_id']) || 
            empty($data['duty_date']) || empty($data['start_time']) || empty($data['end_time'])) {
            $error = 'Please fill in all required fields.';
        } elseif (Duty::checkConflicts($data['teacher_id'], $data['duty_date'], 
                                       $data['start_time'], $data['end_time'])) {
            $error = 'Teacher has a conflicting duty at this time.';
        } else {
            $data['assigned_by'] = $auth->getUserId();
            $duty = new Duty();
            if ($duty->create($data)) {
                $success = 'Duty assigned successfully!';
                echo '<meta http-equiv="refresh" content="2;url=' . SITE_URL . '/views/duties/">';
            } else {
                $error = 'Failed to assign duty. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle me-2"></i><?php echo $pageTitle; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <?php echo CSRF::getTokenField(); ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Teacher *</label>
                                    <select name="teacher_id" class="form-control" required>
                                        <option value="">Select Teacher...</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>">
                                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                                (<?php echo htmlspecialchars($teacher['teacher_id']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category *</label>
                                    <select name="category_id" class="form-control" required>
                                        <option value="">Select Category...</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Date *</label>
                                    <input type="date" name="duty_date" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Start Time *</label>
                                    <input type="time" name="start_time" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">End Time *</label>
                                    <input type="time" name="end_time" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" class="form-control" placeholder="e.g., Main Hall, Room 201">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" class="form-control">
                                        <option value="<?php echo PRIORITY_LOW; ?>">Low</option>
                                        <option value="<?php echo PRIORITY_NORMAL; ?>" selected>Normal</option>
                                        <option value="<?php echo PRIORITY_HIGH; ?>">High</option>
                                        <option value="<?php echo PRIORITY_URGENT; ?>">Urgent</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3" 
                                          placeholder="Any additional notes..."></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="<?php echo SITE_URL; ?>/views/duties/" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Back
                                </a>
                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-save me-2"></i> Assign Duty
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>