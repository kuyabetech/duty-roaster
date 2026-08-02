<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/Swap.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    redirect(SITE_URL . '/views/swaps/');
}

$swap = new Swap($id);
if (!$swap->getData()) {
    redirect(SITE_URL . '/views/swaps/');
}

$data = $swap->getData();
$pageTitle = 'Swap Request Details';
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
                        <h5><i class="fas fa-exchange-alt me-2"></i><?php echo $pageTitle; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Requester</h6>
                                <p><strong><?php echo htmlspecialchars(($data['requester_first'] ?? '') . ' ' . ($data['requester_last'] ?? '')); ?></strong></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Target Teacher</h6>
                                <p><strong><?php echo htmlspecialchars(($data['target_first'] ?? '') . ' ' . ($data['target_last'] ?? '')); ?></strong></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Duty</h6>
                                <p><strong><?php echo htmlspecialchars($data['duty_code'] ?? 'N/A'); ?></strong></p>
                                <p><?php echo htmlspecialchars($data['category_name'] ?? ''); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Status</h6>
                                <span class="badge bg-<?php echo getStatusBadgeColor($data['status']); ?>">
                                    <?php echo str_replace('_', ' ', ucfirst($data['status'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Original Date</h6>
                                <p><?php echo formatDate($data['duty_date'] ?? ''); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Requested Date</h6>
                                <p><?php echo formatDate($data['requested_date']); ?></p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Original Time</h6>
                                <p><?php echo date('h:i A', strtotime($data['start_time'] ?? '')); ?> - <?php echo date('h:i A', strtotime($data['end_time'] ?? '')); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Requested Time</h6>
                                <p><?php echo date('h:i A', strtotime($data['requested_start_time'])); ?> - <?php echo date('h:i A', strtotime($data['requested_end_time'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($data['reason']): ?>
                            <hr>
                            <div class="mb-3">
                                <h6 class="text-muted">Reason</h6>
                                <p><?php echo nl2br(htmlspecialchars($data['reason'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($data['admin_approved_by']): ?>
                            <hr>
                            <div class="mb-3">
                                <h6 class="text-muted">Admin Approval</h6>
                                <p>Approved by: <?php echo htmlspecialchars($data['admin_name'] ?? 'Admin'); ?></p>
                                <p>Approved at: <?php echo formatDateTime($data['admin_approved_at']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mt-3">
                            <a href="<?php echo SITE_URL; ?>/views/swaps/" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </a>
                            <?php if ($data['status'] === SWAP_PENDING && $auth->hasRole(ROLE_ADMIN)): ?>
                                <div>
                                    <a href="<?php echo SITE_URL; ?>/views/swaps/approve.php?id=<?php echo $data['id']; ?>" 
                                       class="btn btn-success">
                                        <i class="fas fa-check me-2"></i> Approve
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>/views/swaps/reject.php?id=<?php echo $data['id']; ?>" 
                                       class="btn btn-danger">
                                        <i class="fas fa-times me-2"></i> Reject
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($data['status'] === SWAP_APPROVED_BY_ADMIN && $auth->getUser()['role'] === ROLE_TEACHER): ?>
                                <div>
                                    <a href="<?php echo SITE_URL; ?>/views/swaps/approve-teacher.php?id=<?php echo $data['id']; ?>" 
                                       class="btn btn-success">
                                        <i class="fas fa-check me-2"></i> Accept
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>/views/swaps/reject-teacher.php?id=<?php echo $data['id']; ?>" 
                                       class="btn btn-danger">
                                        <i class="fas fa-times me-2"></i> Reject
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>