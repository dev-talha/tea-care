<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Services\AnnouncementService;
use App\Services\OrderService;
use App\Services\ScheduleService;
use App\Services\WhatsAppService;

Config::load(dirname(__DIR__));

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

// Use a clean test date far in the future to avoid polluting real today data
$testDate = '2099-06-15'; // Monday (working day)
$cutoffPassedNow = new DateTimeImmutable('2099-06-15 16:00:00', new DateTimeZone('Asia/Dhaka'));

// Cleanup previous test artifacts if any
Database::execute("DELETE FROM orders WHERE order_date = ?", [$testDate]);
Database::execute("DELETE FROM daily_overrides WHERE service_date = ?", [$testDate]);
Database::execute("DELETE FROM holidays WHERE holiday_date = ?", [$testDate]);
Database::execute("DELETE FROM users WHERE employee_id LIKE 'TEST_%'");

$empAId = (int)Database::insert(
    "INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status, can_order_for_guests, is_paused)
     VALUES ('TEST_A', 'Test Emp A', ?, 'employee', 1, 1, 'active', 0, 0)",
    [password_hash('Pass123!', PASSWORD_DEFAULT)]
);
$empBId = (int)Database::insert(
    "INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status, can_order_for_guests, is_paused)
     VALUES ('TEST_B', 'Test Manager B', ?, 'employee', 2, 3, 'active', 1, 0)",
    [password_hash('Pass123!', PASSWORD_DEFAULT)]
);
$empUnconfId = (int)Database::insert(
    "INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status, can_order_for_guests, is_paused)
     VALUES ('TEST_UNCONF', 'Test Unconfigured', ?, 'employee', 1, 1, 'active', 0, 0)",
    [password_hash('Pass123!', PASSWORD_DEFAULT)]
);
$empPausedId = (int)Database::insert(
    "INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status, can_order_for_guests, is_paused)
     VALUES ('TEST_PAUSED', 'Test Paused', ?, 'employee', 1, 1, 'active', 0, 1)",
    [password_hash('Pass123!', PASSWORD_DEFAULT)]
);
$staffId = (int)Database::insert(
    "INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status, can_order_for_guests)
     VALUES ('TEST_STAFF', 'Test Staff', ?, 'staff', 5, 5, 'active', 0)",
    [password_hash('Pass123!', PASSWORD_DEFAULT)]
);

$empA = Database::fetchOne('SELECT u.*, d.name AS department_name, r.name AS room_name FROM users u LEFT JOIN departments d ON d.id=u.department_id LEFT JOIN rooms r ON r.id=u.room_id WHERE u.id=?', [$empAId]);
$empB = Database::fetchOne('SELECT u.*, d.name AS department_name, r.name AS room_name FROM users u LEFT JOIN departments d ON d.id=u.department_id LEFT JOIN rooms r ON r.id=u.room_id WHERE u.id=?', [$empBId]);
$staff = Database::fetchOne('SELECT * FROM users WHERE id=?', [$staffId]);

// Configure defaults for TEST_A: morning tea, afternoon coffee
OrderService::saveDefaultPreference($empAId, 'morning', true, 'tea', 'less', 1, 'Milk tea');
OrderService::saveDefaultPreference($empAId, 'afternoon', true, 'coffee', 'none', 1, 'Black');
// Configure defaults for TEST_PAUSED: enabled but user is_paused=1
OrderService::saveDefaultPreference($empPausedId, 'morning', true, 'tea', 'normal', 1, '');

// Skip morning for TEST_A on testDate, leave afternoon untouched
Database::insert(
    "INSERT INTO daily_overrides (user_id, service_date, slot_key, override_type) VALUES (?, ?, 'morning', 'skip')",
    [$empAId, $testDate]
);

// Configure override for TEST_B on testDate morning (coffee) while default is tea
OrderService::saveDefaultPreference($empBId, 'morning', true, 'tea', 'normal', 1, '');
Database::insert(
    "INSERT INTO daily_overrides (user_id, service_date, slot_key, override_type, beverage, sugar, quantity, note)
     VALUES (?, ?, 'morning', 'custom', 'coffee', 'less', 1, 'Today coffee')",
    [$empBId, $testDate]
);

// Configure opted-out employee (explicitly disabled both sessions)
$empOptOutId = (int)Database::insert(
    "INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status, can_order_for_guests, is_paused)
     VALUES ('TEST_OPTOUT', 'Test Opted Out', ?, 'employee', 2, 2, 'active', 0, 0)",
    [password_hash('Pass123!', PASSWORD_DEFAULT)]
);
OrderService::saveDefaultPreference($empOptOutId, 'morning', false, 'tea', 'normal', 1, '');
OrderService::saveDefaultPreference($empOptOutId, 'afternoon', false, 'tea', 'normal', 1, '');

// 1. Run regular finalization
OrderService::finalizeRegularSlot($testDate, 'morning', $cutoffPassedNow);
OrderService::finalizeRegularSlot($testDate, 'afternoon', $cutoffPassedNow);

// Verify Rule 1: TEST_A skipped morning -> no morning order, but afternoon coffee generated
$aMorning = Database::fetchOne("SELECT * FROM orders WHERE regular_unique_key = ?", ["{$testDate}:morning:{$empAId}"]);
$aAfternoon = Database::fetchOne("SELECT * FROM orders WHERE regular_unique_key = ?", ["{$testDate}:afternoon:{$empAId}"]);
assertTrue($aMorning === null, 'Rule 1: Skipped morning produces no morning order');
assertTrue($aAfternoon !== null, 'Rule 1: Skipped morning still generates afternoon order');

// Verify Rule 2: TEST_B morning override is coffee, default remains tea
$bMorning = Database::fetchOne("SELECT o.*, oi.beverage FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.regular_unique_key = ?", ["{$testDate}:morning:{$empBId}"]);
$bDefault = OrderService::getDefaultPreferences($empBId)['morning'];
assertTrue($bMorning && $bMorning['beverage'] === 'coffee', 'Rule 2: Today override produces coffee');
assertTrue($bDefault && $bDefault['beverage'] === 'tea', 'Rule 2: Recurring default remains tea');

// Verify Auto 2-Session Default, Opt-Out, and Paused rules
$unconfOrders = Database::fetchAll("SELECT o.slot_key, oi.beverage FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.order_date = ? AND o.user_id = ? ORDER BY o.slot_key", [$testDate, $empUnconfId]);
$optOutOrder = Database::fetchOne("SELECT id FROM orders WHERE order_date = ? AND user_id = ?", [$testDate, $empOptOutId]);
$pausedOrder = Database::fetchOne("SELECT id FROM orders WHERE order_date = ? AND user_id = ?", [$testDate, $empPausedId]);
assertTrue(count($unconfOrders) === 2 && $unconfOrders[0]['beverage'] === 'tea' && $unconfOrders[1]['beverage'] === 'tea', 'Auto-Default: Employee without explicit rows gets Morning & Afternoon Tea');
assertTrue($optOutOrder === null, 'Opt-Out: Explicitly disabled employee generates no regular order');
assertTrue($pausedOrder === null, 'Rule 14: Paused user generates no regular order');

// Verify Staff Skip List + Room/Dept filtering
$skipAll = OrderService::getSkippedListForStaff($testDate);
$skipRoom1 = OrderService::getSkippedListForStaff($testDate, 1, null);
$skipDept2 = OrderService::getSkippedListForStaff($testDate, null, 2);
$skipIdsAll = array_column($skipAll, 'user_id');
$skipIdsDept2 = array_column($skipDept2, 'user_id');
assertTrue(in_array($empAId, $skipIdsAll, false) && in_array($empPausedId, $skipIdsAll, false) && in_array($empOptOutId, $skipIdsAll, false), 'Skip List includes skipped_today, paused, and disabled_default employees');
assertTrue(count($skipRoom1) >= 2 && in_array($empOptOutId, $skipIdsDept2, false) && !in_array($empAId, $skipIdsDept2, false), 'Skip List supports Room & Department filtering');

// Verify Rule 11: Re-running finalization creates zero duplicates
$beforeCnt = (int)Database::fetchOne("SELECT COUNT(*) AS cnt FROM orders WHERE order_date = ?", [$testDate])['cnt'];
OrderService::finalizeRegularSlot($testDate, 'morning', $cutoffPassedNow);
OrderService::finalizeRegularSlot($testDate, 'afternoon', $cutoffPassedNow);
$afterCnt = (int)Database::fetchOne("SELECT COUNT(*) AS cnt FROM orders WHERE order_date = ?", [$testDate])['cnt'];
assertTrue($beforeCnt === $afterCnt, 'Rule 11: Re-running finalization produces zero duplicate orders');

// Verify Rule 5: Holiday blocks order generation
$holDate = '2099-06-16';
Database::execute("DELETE FROM orders WHERE order_date = ?", [$holDate]);
Database::execute("INSERT INTO holidays (holiday_date, title, is_closed) VALUES (?, 'Test Holiday', 1) ON DUPLICATE KEY UPDATE is_closed=1", [$holDate]);
$holCount = OrderService::finalizeRegularSlot($holDate, 'morning', new DateTimeImmutable('2099-06-16 10:00:00', new DateTimeZone('Asia/Dhaka')));
assertTrue($holCount === 0, 'Rule 5: Holiday generates 0 regular orders');
Database::execute("DELETE FROM holidays WHERE holiday_date = ?", [$holDate]);

// Verify On-Demand Idempotency
$idem = 'test-idem-' . bin2hex(random_bytes(4));
$od1 = OrderService::createOnDemandOrder($empA, 'coffee', 'none', 1, 3, 'Strong', $idem);
$od2 = OrderService::createOnDemandOrder($empA, 'coffee', 'none', 1, 3, 'Strong', $idem);
assertTrue((int)$od1['id'] === (int)$od2['id'], 'On-demand idempotency returns same order ID');

// Verify Guest Order open to all employees & 5-cup mixed breakdown
$anyEmpGuest = OrderService::createGuestOrder($empA, 6, 'Team Catchup', 2, [['beverage' => 'tea', 'sugar' => 'normal', 'quantity' => 2]], '', 'any-emp-' . bin2hex(random_bytes(4)));
assertTrue((int)($anyEmpGuest['id'] ?? 0) > 0, 'Any employee can create guest/meeting order');

$guestOrder = OrderService::createGuestOrder($empB, 6, 'Board Briefing', 5, [
    ['beverage' => 'tea', 'sugar' => 'normal', 'quantity' => 2],
    ['beverage' => 'tea', 'sugar' => 'none', 'quantity' => 1],
    ['beverage' => 'coffee', 'sugar' => 'less', 'quantity' => 1],
    ['beverage' => 'coffee', 'sugar' => 'none', 'quantity' => 1],
], 'Serve together', 'guest-' . bin2hex(random_bytes(4)));
$guestItems = Database::fetchAll('SELECT * FROM order_items WHERE order_id = ?', [$guestOrder['id']]);
$guestTotal = array_sum(array_column($guestItems, 'quantity'));
assertTrue(count($guestItems) === 4 && $guestTotal === 5, 'Rule 8: 5-cup mixed guest order saves all 4 variations');

// Verify Staff workflow & Rule 6 double-delivery prevention
OrderService::transitionStatus((int)$od1['id'], 'accepted', $staff);
OrderService::transitionStatus((int)$od1['id'], 'preparing', $staff);
OrderService::transitionStatus((int)$od1['id'], 'delivered', $staff);
$doubleBlocked = false;
try {
    OrderService::transitionStatus((int)$od1['id'], 'delivered', $staff);
} catch (RuntimeException $e) {
    $doubleBlocked = true;
}
assertTrue($doubleBlocked, 'Rule 6: Cannot complete same order twice');

// Verify Rule 10: Snapshot remains unchanged if user changes room
$origSnap = $aAfternoon['room_name_snapshot'];
Database::execute('UPDATE users SET room_id = 4 WHERE id = ?', [$empAId]);
$reloadedOrder = Database::fetchOne('SELECT room_name_snapshot FROM orders WHERE id = ?', [$aAfternoon['id']]);
assertTrue($reloadedOrder['room_name_snapshot'] === $origSnap, 'Rule 10: Historical order room snapshot preserved after user room change');

// Verify Default Schedule (Morning 9:45 AM, Afternoon 3:30 PM)
$mSlot = ScheduleService::getSlot('morning');
$aSlot = ScheduleService::getSlot('afternoon');
assertTrue($mSlot && $mSlot['service_time'] === '09:45:00' && $aSlot && $aSlot['service_time'] === '15:30:00', 'Default Service Times: Morning 9:45 AM & Afternoon 3:30 PM');

// Verify Staff Announcements (Unavailable + Time Change + Clear)
$annId1 = AnnouncementService::create($staffId, $testDate, 'morning', 'time_change', '10:15', 'Staff busy in meeting');
$annActive = AnnouncementService::getActiveForDate($testDate);
$slotNotice = AnnouncementService::findSlotNotice($annActive, 'morning');
assertTrue(count($annActive) === 1 && $slotNotice && $slotNotice['adjusted_time'] === '10:15:00', 'Staff Announcement creates active time adjustment notice');
AnnouncementService::deactivate($annId1, $staffId);
assertTrue(count(AnnouncementService::getActiveForDate($testDate)) === 0, 'Staff Announcement clear deactivates notice');

// Verify Evolution API WhatsApp queueing when enabled
$origEvo = WhatsAppService::getConfig();
Database::execute("INSERT INTO settings (setting_key, setting_value) VALUES ('evo_enabled','1'),('evo_base_url','https://evo.test'),('evo_api_key','k'),('evo_instance','teacare'),('evo_group_jid','123@g.us') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
$waTestOrder = OrderService::createOnDemandOrder($empA, 'tea', 'normal', 1, 3, 'WA check', 'wa-test-' . bin2hex(random_bytes(4)));
$waJob = Database::fetchOne('SELECT * FROM whatsapp_jobs WHERE order_id = ?', [$waTestOrder['id']]);
assertTrue($waJob !== null && str_contains($waJob['message'], 'TeaCare Urgent Request'), 'Evolution API queues WhatsApp group message on urgent order');
Database::execute("INSERT INTO settings (setting_key, setting_value) VALUES ('evo_enabled',?),('evo_base_url',?),('evo_api_key',?),('evo_instance',?),('evo_group_jid',?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [
    $origEvo['enabled'] ? '1' : '0', $origEvo['base_url'], $origEvo['api_key'], $origEvo['instance'], $origEvo['group_jid']
]);

// Verify every table in the database has a created_at column for Admin monitoring
$missingCreatedAt = Database::fetchAll(
    "SELECT t.TABLE_NAME
     FROM INFORMATION_SCHEMA.TABLES t
     LEFT JOIN INFORMATION_SCHEMA.COLUMNS c
       ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND c.COLUMN_NAME = 'created_at'
     WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE' AND c.COLUMN_NAME IS NULL"
);
assertTrue(empty($missingCreatedAt), 'All database tables include created_at column');

// Verify English-only lock (BN/EN option removed)
\App\Core\Lang::init('bn');
assertTrue(\App\Core\Lang::current() === 'en', 'Language locked to English (BN/EN switcher removed)');

// Verify canonical 16 departments present
$deptNames = array_column(Database::fetchAll('SELECT name FROM departments ORDER BY id ASC'), 'name');
assertTrue(count($deptNames) === 16 && in_array('Web & Software Development', $deptNames, true) && in_array('First Line Agents', $deptNames, true), 'Canonical 16 company departments active');

// Verify On-Demand / Guest Preparing -> Almost Done (1-30 min) -> Requester Delivered workflow
$flowOrd = OrderService::createOnDemandOrder($empA, 'tea', 'less', 1, 3, 'Live flow check', 'flow-' . bin2hex(random_bytes(4)));
$flowPrep = OrderService::transitionStatus((int)$flowOrd['id'], 'preparing', $staff, 'Accepted & preparing');
$flowAlmost = OrderService::transitionStatus((int)$flowOrd['id'], 'almost_done', $staff, null, 12);
$flowDone = OrderService::transitionStatus((int)$flowOrd['id'], 'delivered', $empA, 'Marked delivered by requester');
assertTrue(
    $flowPrep['status'] === 'preparing' &&
    $flowAlmost['status'] === 'almost_done' &&
    (int)$flowAlmost['estimated_minutes'] === 12 &&
    $flowDone['status'] === 'delivered',
    'Order workflow: Accept -> Preparing -> Almost Done (1-30m) -> Requester Delivered works'
);

// Verify Guest requested_within + 3-Hour System Auto-Cancel rules
$guestOrd = OrderService::createGuestOrder($empA, 3, 'Client Sync', 4, [['beverage' => 'coffee', 'sugar' => 'less', 'quantity' => 2]], 'Test guest time', 'guest-within-' . bin2hex(random_bytes(4)), 'Within 15 min');
assertTrue(($guestOrd['requested_within'] ?? '') === 'Within 15 min', 'Guest order saves requested_within timeframe');

$stalePending = OrderService::createOnDemandOrder($empA, 'tea', 'normal', 1, 3, 'Stale pending', 'stale-pend-' . bin2hex(random_bytes(4)));
$staleAccepted = OrderService::createOnDemandOrder($empA, 'coffee', 'normal', 1, 3, 'Stale accepted', 'stale-acc-' . bin2hex(random_bytes(4)));
OrderService::transitionStatus((int)$staleAccepted['id'], 'preparing', $staff, 'Prep start');
$freshPending = OrderService::createOnDemandOrder($empA, 'tea', 'normal', 1, 3, 'Fresh pending', 'fresh-pend-' . bin2hex(random_bytes(4)));

$nowObj = ScheduleService::now();
$expiredTs = $nowObj->modify('-3 hours -5 minutes')->format('Y-m-d H:i:s');
$freshTs = $nowObj->modify('-10 minutes')->format('Y-m-d H:i:s');
Database::execute('UPDATE orders SET created_at = ? WHERE id = ?', [$expiredTs, $stalePending['id']]);
Database::execute('UPDATE orders SET accepted_at = ?, prepared_at = ?, created_at = ? WHERE id = ?', [$expiredTs, $expiredTs, $expiredTs, $staleAccepted['id']]);
Database::execute('UPDATE orders SET created_at = ? WHERE id = ?', [$freshTs, $freshPending['id']]);
Database::execute('UPDATE orders SET created_at = ? WHERE id = ?', [$freshTs, $guestOrd['id']]);

$autoCancelledCount = OrderService::autoCancelExpiredOrders($nowObj);
$reStalePending = Database::fetchOne('SELECT status FROM orders WHERE id = ?', [$stalePending['id']]);
$reStaleAccepted = Database::fetchOne('SELECT status FROM orders WHERE id = ?', [$staleAccepted['id']]);
$reFreshPending = Database::fetchOne('SELECT status FROM orders WHERE id = ?', [$freshPending['id']]);
$sysEvent = Database::fetchOne("SELECT actor_name, to_status FROM order_events WHERE order_id = ? AND to_status = 'system_cancelled'", [$stalePending['id']]);

assertTrue(
    $autoCancelledCount >= 2 &&
    ($reStalePending['status'] ?? '') === 'system_cancelled' &&
    ($reStaleAccepted['status'] ?? '') === 'system_cancelled' &&
    ($reFreshPending['status'] ?? '') === 'pending' &&
    ($sysEvent['actor_name'] ?? '') === 'System',
    '3-hour System Auto-Cancel cancels stale pending/accepted orders and leaves fresh orders intact'
);

// Verify Funny Monkey per-user daily dismiss & permanent show/hide columns
Database::execute('UPDATE users SET show_funny_monkey = 1, monkey_dismissed_date = ? WHERE id = ?', [$testDate, $empAId]);
$uM1 = Database::fetchOne('SELECT show_funny_monkey, monkey_dismissed_date FROM users WHERE id = ?', [$empAId]);
Database::execute('UPDATE users SET show_funny_monkey = 0 WHERE id = ?', [$empAId]);
$uM2 = Database::fetchOne('SELECT show_funny_monkey FROM users WHERE id = ?', [$empAId]);
assertTrue(
    (int)($uM1['show_funny_monkey'] ?? -1) === 1 &&
    ($uM1['monkey_dismissed_date'] ?? '') === $testDate &&
    (int)($uM2['show_funny_monkey'] ?? -1) === 0,
    'Funny Monkey daily dismiss & permanent visibility flags persist on users'
);

// Verify Paginator bounds & allowed sizes (25 default, 50, 100, 500) + performance indexes
$_GET['per_page'] = '999';
$_GET['page'] = '99';
$pgDef = \App\Core\Paginator::paginate(60, 'page', 'per_page');
$_GET['per_page'] = '50';
$_GET['page'] = '2';
$pg50 = \App\Core\Paginator::paginate(140, 'page', 'per_page');
$_GET['room_per_page'] = '500';
$_GET['room_page'] = '1';
$pg500 = \App\Core\Paginator::paginate(600, 'room_page', 'room_per_page');
$idxOrdersDateCreated = Database::fetchOne(
    "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_date_created'"
);
assertTrue(
    $pgDef['per_page'] === 25 &&
    $pgDef['total_pages'] === 3 &&
    $pgDef['page'] === 3 &&
    $pgDef['offset'] === 50 &&
    $pg50['per_page'] === 50 &&
    $pg50['offset'] === 50 &&
    $pg500['per_page'] === 500 &&
    $pg500['total_pages'] === 2 &&
    (int)($idxOrdersDateCreated['cnt'] ?? 0) >= 1,
    'Paginator (25/50/100/500) bounds & DB pagination performance indexes active'
);

// Verify Extra Sugar option persists across default_preferences, daily_overrides & orders
OrderService::saveDefaultPreference($empAId, 'morning', true, 'tea', 'extra', 1, 'Extra sweet');
$extraPref = Database::fetchOne("SELECT sugar FROM default_preferences WHERE user_id = ? AND slot_key = 'morning'", [$empAId]);
$extraGuest = OrderService::createGuestOrder($empA, 6, 'Extra Sugar Test', 1, [['beverage' => 'tea', 'sugar' => 'extra', 'quantity' => 1]], '', 'extra-sug-' . bin2hex(random_bytes(4)));
$extraGuestItem = Database::fetchOne('SELECT sugar FROM order_items WHERE order_id = ?', [$extraGuest['id']]);
assertTrue(
    ($extraPref['sugar'] ?? '') === 'extra' &&
    ($extraGuestItem['sugar'] ?? '') === 'extra' &&
    badge_sugar('extra') === 'Extra Sugar',
    'Extra sugar preference and order item persistence works'
);

// Verify newly inserted employees default to can_order_for_guests = 1
$defGuestUserId = (int)Database::insert(
    "INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status)
     VALUES ('TEST_DEF_GUEST', 'Test Default Guest', ?, 'employee', 1, 1, 'active')",
    [password_hash('Pass123!', PASSWORD_DEFAULT)]
);
$defGuestRow = Database::fetchOne('SELECT can_order_for_guests FROM users WHERE id = ?', [$defGuestUserId]);
assertTrue((int)($defGuestRow['can_order_for_guests'] ?? 0) === 1, 'New employees default to can_order_for_guests = 1');

// Verify flexible guest requested_within values, room-batch guard, and skipped count parity
$flexGuest = OrderService::createGuestOrder($empA, 6, 'Before Check', 2, [['beverage' => 'tea', 'sugar' => 'normal', 'quantity' => 1]], '', 'flex-rw-' . bin2hex(random_bytes(4)), 'Before 15 min');
assertTrue(($flexGuest['requested_within'] ?? '') === 'Before 15 min', 'Guest order preserves Before/custom requested_within intervals');
assertTrue(OrderService::deliverRoomOrders(0, $testDate, 'afternoon', [(int)$aAfternoon['id']], $staff) === 0, 'Room batch delivery rejects invalid room_id <= 0');
assertTrue(OrderService::getSkippedCountForStaff($testDate) === count(OrderService::getSkippedListForStaff($testDate, null, null)), 'Skipped count helper matches full skipped list count');

// Cleanup test data
Database::execute("DELETE FROM staff_announcements WHERE announce_date = ?", [$testDate]);
Database::execute("DELETE FROM whatsapp_jobs WHERE order_id IN (SELECT id FROM orders WHERE order_date = ? OR user_id IN (?, ?, ?, ?, ?))", [$testDate, $empAId, $empBId, $empUnconfId, $empPausedId, $empOptOutId]);
Database::execute("DELETE FROM orders WHERE order_date = ? OR user_id IN (?, ?, ?, ?, ?)", [$testDate, $empAId, $empBId, $empUnconfId, $empPausedId, $empOptOutId]);
Database::execute("DELETE FROM daily_overrides WHERE service_date = ?", [$testDate]);
Database::execute("DELETE FROM users WHERE employee_id LIKE 'TEST_%'");

echo "\nALL ACCEPTANCE TESTS PASSED SUCCESSFULLY!\n";
