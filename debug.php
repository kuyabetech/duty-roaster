<?php
// =============================================
// Test Full Swap Workflow
// =============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/models/Swap.php';
require_once __DIR__ . '/models/Duty.php';

echo "<h1>Testing Full Swap Workflow</h1>";

$db = Database::getInstance();

// 1. Get a pending swap
$stmt = $db->prepare("SELECT * FROM duty_swaps WHERE status = ? LIMIT 1");
$stmt->execute([SWAP_PENDING]);
$swapData = $stmt->fetch();

if (!$swapData) {
    echo "<p style='color:red;'>No pending swap requests found</p>";
    exit;
}

echo "<h2>1. Current Swap Status: " . $swapData['status'] . "</h2>";
echo "<pre>";
print_r($swapData);
echo "</pre>";

// 2. Admin approves
echo "<h2>2. Admin Approves Swap</h2>";
try {
    $swap = new Swap($swapData['id']);
    $result = $swap->approveByAdmin(1);
    echo "<p>Admin approve result: " . ($result ? '✅ Success' : '❌ Failed') . "</p>";
    $swap->load();
    echo "<p>New status: " . $swap->getData()['status'] . "</p>";
    $swapData = $swap->getData();
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

// 3. Teacher approves (target teacher)
echo "<h2>3. Teacher Approves Swap</h2>";
try {
    $swap = new Swap($swapData['id']);
    $result = $swap->approveByTeacher();
    echo "<p>Teacher approve result: " . ($result ? '✅ Success' : '❌ Failed') . "</p>";
    $swap->load();
    echo "<p>New status: " . $swap->getData()['status'] . "</p>";
    $swapData = $swap->getData();
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

// 4. Admin completes swap
echo "<h2>4. Admin Completes Swap</h2>";
try {
    $swap = new Swap($swapData['id']);
    $result = $swap->complete();
    echo "<p>Complete result: " . ($result ? '✅ Success' : '❌ Failed') . "</p>";
    $swap->load();
    echo "<p>New status: " . $swap->getData()['status'] . "</p>";
    
    // Check if duty was reassigned
    if ($swap->getData()['duty_id']) {
        $duty = new Duty($swap->getData()['duty_id']);
        if ($duty->getData()) {
            echo "<p>Duty reassigned to teacher ID: " . $duty->getData()['teacher_id'] . "</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>✅ Workflow Complete!</h2>";
?>