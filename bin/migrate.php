<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/helpers.php';

use App\Core\Config;
use App\Core\Database;

Config::load(dirname(__DIR__));

// Wait for DB up to 30s
$pdo = null;
for ($i = 0; $i < 15; $i++) {
    try {
        $pdo = Database::pdo();
        $pdo->query('SELECT 1');
        break;
    } catch (Throwable $e) {
        fwrite(STDERR, "[migrate] Waiting for DB ({$i}): " . $e->getMessage() . "\n");
        sleep(2);
    }
}

if (!$pdo) {
    fwrite(STDERR, "[migrate] Could not connect to MySQL.\n");
    exit(1);
}

$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
if ($schema !== false) {
    foreach (array_filter(array_map('trim', explode(";\n", $schema))) as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

$seed = file_get_contents(__DIR__ . '/../database/seed.sql');
if ($seed !== false) {
    foreach (array_filter(array_map('trim', explode(";\n", $seed))) as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

// Create initial accounts only if users table is empty
$count = (int)($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0);
if ($count === 0) {
    $adminId = Config::get('DEFAULT_ADMIN_ID', 'ADMIN001');
    $adminPwd = Config::get('DEFAULT_ADMIN_PASSWORD', '');
    if ($adminPwd === '' || str_starts_with($adminPwd, 'replace-with-')) {
        $adminPwd = bin2hex(random_bytes(8));
        echo "[migrate] Generated random admin password for {$adminId}: {$adminPwd}\n";
    }
    $demoPwd = Config::get('DEFAULT_DEMO_PASSWORD', '');
    if ($demoPwd === '' || str_starts_with($demoPwd, 'replace-with-')) {
        $demoPwd = bin2hex(random_bytes(8));
        echo "[migrate] Generated random password for seeded sample accounts (STAFF001, EMP001, EMP002): {$demoPwd}\n";
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status, can_order_for_guests, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );

    $stmt->execute([$adminId, 'System Administrator', password_hash($adminPwd, PASSWORD_DEFAULT), 'admin', 11, 1, 'active', 1]);
    $stmt->execute(['STAFF001', 'Kamal (Tea Staff)', password_hash($demoPwd, PASSWORD_DEFAULT), 'staff', 15, 5, 'active', 0]);
    $stmt->execute(['EMP001', 'Rahim Uddin', password_hash($demoPwd, PASSWORD_DEFAULT), 'employee', 9, 3, 'active', 0]);
    $stmt->execute(['EMP002', 'Tariqul Islam (Sales Head)', password_hash($demoPwd, PASSWORD_DEFAULT), 'employee', 13, 4, 'active', 1]);

    // Seed automatic 2-session Tea defaults for all seeded employees
    $empRows = $pdo->query("SELECT id FROM users WHERE role = 'employee'")->fetchAll(PDO::FETCH_COLUMN);
    $prefStmt = $pdo->prepare(
        "INSERT IGNORE INTO default_preferences (user_id, slot_key, enabled, beverage, sugar, quantity, note) VALUES (?, ?, 1, 'tea', 'normal', 1, '')"
    );
    foreach ($empRows as $eid) {
        $prefStmt->execute([$eid, 'morning']);
        $prefStmt->execute([$eid, 'afternoon']);
    }

    echo "[migrate] Initial accounts seeded (Admin: {$adminId}, Staff: STAFF001, Employees: EMP001, EMP002).\n";
}

// One-time upgrade: set default morning tea schedule to 9:45 AM (cutoff 9:30 AM)
$morningMigrated = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'default_morning_0945_applied'")->fetchColumn();
if ($morningMigrated !== '1') {
    $pdo->exec("UPDATE service_slots SET service_time = '09:45:00', cutoff_time = '09:30:00', updated_at = NOW() WHERE slot_key = 'morning' AND service_time = '09:00:00'");
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('default_morning_0945_applied', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");
    echo "[migrate] Updated default Morning Tea schedule to 9:45 AM (cutoff 9:30 AM).\n";
}

// Ensure every core table records created_at datetime for admin monitoring
$ensureCreatedAtTables = ['service_slots', 'default_preferences', 'order_items', 'settings', 'login_attempts'];
$colCheckStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'created_at'"
);
foreach ($ensureCreatedAtTables as $tbl) {
    $colCheckStmt->execute([$tbl]);
    if ((int)$colCheckStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$tbl}` ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        echo "[migrate] Added created_at column to {$tbl}.\n";
    }
}

// One-time upgrade: show only room numbers without department suffixes on seeded office rooms & snapshots
$roomsCleaned = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'rooms_strip_dept_suffix_applied'")->fetchColumn();
if ($roomsCleaned !== '1') {
    $roomRenames = [
        'Room 101 - Reception'   => 'Room 101',
        'Room 201 - Engineering' => 'Room 201',
        'Room 204 - Accounts'    => 'Room 204',
        'Room 301 - Sales'       => 'Room 301',
    ];
    $updRoom = $pdo->prepare('UPDATE rooms SET name = ?, updated_at = NOW() WHERE name = ?');
    $updSnap = $pdo->prepare('UPDATE orders SET room_name_snapshot = ? WHERE room_name_snapshot = ?');
    foreach ($roomRenames as $oldName => $newName) {
        $updRoom->execute([$newName, $oldName]);
        $updSnap->execute([$newName, $oldName]);
    }
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('rooms_strip_dept_suffix_applied', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");
    echo "[migrate] Stripped department suffixes from seeded room names.\n";
}

// One-time upgrade: replace departments with canonical 16 company departments
$deptSynced = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'departments_crm_sync_v1_applied'")->fetchColumn();
if ($deptSynced !== '1') {
    $canonicalDepts = [
        1  => 'Web & Software Development',
        2  => 'Customer Service',
        3  => 'RDP',
        4  => 'Digital Marketing',
        5  => 'Support',
        6  => 'Hardware & Maintenance',
        7  => 'Training & Communication',
        8  => 'Human Resources (HR)',
        9  => 'Accounts & Finance',
        10 => 'Media & Graphics',
        11 => 'Data Center & Administration',
        12 => 'PBX & SMS',
        13 => 'Sales',
        14 => 'Marketing',
        15 => 'Office Assistant',
        16 => 'First Line Agents',
    ];
    $oldIdToName = [];
    foreach ($pdo->query('SELECT id, name FROM departments')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $oldIdToName[(int)$row['id']] = (string)$row['name'];
    }
    $nameToNewId = array_flip($canonicalDepts);
    $legacyMap = [
        'Administration'     => 11,
        'Accounts & Finance' => 9,
        'Engineering & IT'   => 1,
        'Sales & Marketing'  => 13,
        'Tea & Hospitality'  => 15,
    ];
    $resolveNewDeptId = static function (?int $oldId) use ($oldIdToName, $nameToNewId, $legacyMap): ?int {
        if (!$oldId || !isset($oldIdToName[$oldId])) {
            return null;
        }
        $oldName = $oldIdToName[$oldId];
        if (isset($nameToNewId[$oldName])) {
            return (int)$nameToNewId[$oldName];
        }
        return $legacyMap[$oldName] ?? null;
    };

    $userDeptRows = $pdo->query('SELECT id, department_id FROM users WHERE department_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
    $roomDeptRows = $pdo->query('SELECT id, department_id FROM rooms WHERE department_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DELETE FROM departments');
    $pdo->exec('ALTER TABLE departments AUTO_INCREMENT = 17');
    $insDept = $pdo->prepare('INSERT INTO departments (id, name, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())');
    foreach ($canonicalDepts as $dId => $dName) {
        $insDept->execute([$dId, $dName]);
    }
    $updUserDept = $pdo->prepare('UPDATE users SET department_id = ? WHERE id = ?');
    foreach ($userDeptRows as $ur) {
        $updUserDept->execute([$resolveNewDeptId((int)$ur['department_id']), (int)$ur['id']]);
    }
    $updRoomDept = $pdo->prepare('UPDATE rooms SET department_id = ? WHERE id = ?');
    foreach ($roomDeptRows as $rr) {
        $updRoomDept->execute([$resolveNewDeptId((int)$rr['department_id']), (int)$rr['id']]);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('departments_crm_sync_v1_applied', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");
    echo "[migrate] Replaced departments with canonical 16 company departments.\n";
}

// Upgrade orders table with 'almost_done' & 'system_cancelled' status + estimated_minutes + almost_done_at + requested_within columns
$statusColType = (string)($pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'status'")->fetchColumn() ?: '');
if ($statusColType !== '' && (strpos($statusColType, 'almost_done') === false || strpos($statusColType, 'system_cancelled') === false)) {
    $pdo->exec("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'accepted', 'preparing', 'almost_done', 'delivered', 'cancelled', 'system_cancelled') NOT NULL DEFAULT 'pending'");
    echo "[migrate] Updated orders.status ENUM with almost_done & system_cancelled.\n";
}
foreach ([
    'estimated_minutes' => 'ADD COLUMN estimated_minutes TINYINT UNSIGNED NULL AFTER prepared_at',
    'almost_done_at'    => 'ADD COLUMN almost_done_at DATETIME NULL AFTER estimated_minutes',
    'requested_within'  => 'ADD COLUMN requested_within VARCHAR(40) NULL AFTER guest_count',
] as $colName => $alterSql) {
    $colCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = ?");
    $colCheckStmt->execute([$colName]);
    if ((int)$colCheckStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE orders {$alterSql}");
        echo "[migrate] Added {$colName} column to orders.\n";
    }
}

foreach ([
    'show_funny_monkey'     => 'ADD COLUMN show_funny_monkey TINYINT(1) NOT NULL DEFAULT 1 AFTER is_paused',
    'monkey_dismissed_date' => 'ADD COLUMN monkey_dismissed_date DATE NULL AFTER show_funny_monkey',
] as $uColName => $uAlterSql) {
    $uCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?");
    $uCheckStmt->execute([$uColName]);
    if ((int)$uCheckStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE users {$uAlterSql}");
        echo "[migrate] Added {$uColName} column to users.\n";
    }
}

// Performance indexes for paginated admin tables (fast ORDER BY / GROUP BY / COUNT at scale)
$perfIndexes = [
    ['users', 'idx_users_room_id', 'room_id'],
    ['orders', 'idx_orders_date_created', 'order_date, created_at'],
    ['notification_jobs', 'idx_notif_jobs_created', 'created_at'],
    ['whatsapp_jobs', 'idx_wa_jobs_created', 'created_at'],
    ['push_subscriptions', 'idx_push_subs_active_updated', 'is_active, updated_at'],
];
$idxCheckStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
);
foreach ($perfIndexes as [$tblName, $idxName, $idxCols]) {
    $idxCheckStmt->execute([$tblName, $idxName]);
    if ((int)$idxCheckStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$tblName}` ADD INDEX `{$idxName}` ({$idxCols})");
        echo "[migrate] Added index {$idxName} on {$tblName}.\n";
    }
}

echo "[migrate] Schema and seed up to date.\n";
