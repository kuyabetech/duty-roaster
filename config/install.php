<?php
// =============================================
// Installation Script
// =============================================

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

$db = Database::getInstance();

echo "ADPS Installation Script\n";
echo "========================\n\n";

// Check if tables exist
try {
    $stmt = $db->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "⚠️  Tables already exist. Skipping installation.\n";
        echo "To reinstall, please drop all tables first.\n";
        exit;
    }
} catch (PDOException $e) {
    // Tables don't exist, continue
}

echo "Creating database tables...\n";

// Read SQL file
$sqlFile = __DIR__ . '/../database/schema.sql';
if (!file_exists($sqlFile)) {
    echo "❌ Schema file not found: $sqlFile\n";
    exit;
}

$sql = file_get_contents($sqlFile);

// Split SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    try {
        $db->query($statement);
        echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
    } catch (PDOException $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
        echo "Statement: " . $statement . "\n";
    }
}

echo "\n✅ Database tables created successfully!\n";

// Check admin user
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
$stmt->execute([ROLE_SUPER_ADMIN]);
$count = $stmt->fetchColumn();

if ($count == 0) {
    echo "\nCreating admin user...\n";
    
    $password = 'Admin@123';
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password, full_name, role, email_verified, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        'admin',
        'admin@adps.com',
        $hashedPassword,
        'System Administrator',
        ROLE_SUPER_ADMIN,
        1,
        STATUS_ACTIVE
    ]);
    
    if ($result) {
        echo "✅ Admin user created!\n";
        echo "   Username: admin\n";
        echo "   Password: Admin@123\n";
        echo "   Email: admin@adps.com\n";
        echo "\n⚠️  Please change the password immediately after first login!\n";
    } else {
        echo "❌ Failed to create admin user\n";
    }
}

echo "\n✅ Installation complete!\n";
echo "You can now access the system at: " . SITE_URL . "\n";