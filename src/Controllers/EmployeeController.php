<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\AnnouncementService;
use App\Services\ImageService;
use App\Services\OrderService;
use App\Services\ScheduleService;

class EmployeeController
{
    public function dashboard(): void
    {
        $user = Auth::requireRole(['employee', 'admin', 'staff']);
        $today = ScheduleService::today();
        OrderService::autoCancelExpiredOrders();
        $dayStatus = ScheduleService::getDayStatus($today);
        $announcements = AnnouncementService::getActiveForDate($today);

        $morning = OrderService::getTodaySlotState($user, 'morning');
        $afternoon = OrderService::getTodaySlotState($user, 'afternoon');

        $todayRequests = Database::fetchAll(
            "SELECT o.*, ab.full_name AS accepted_by_name FROM orders o
             LEFT JOIN users ab ON ab.id = o.accepted_by
             WHERE o.user_id = ? AND o.order_date = ? AND o.order_type IN ('on_demand', 'guest')
             ORDER BY o.created_at DESC",
            [$user['id'], $today]
        );
        $todayRequests = OrderService::getOrdersWithItems($todayRequests);

        $rooms = Database::fetchAll("SELECT id, name, floor FROM rooms WHERE is_active = 1 AND room_type = 'office' ORDER BY name ASC");

        Response::html(View::render('employee/dashboard', [
            'user'            => $user,
            'today'           => $today,
            'dayStatus'       => $dayStatus,
            'announcements'   => $announcements,
            'morning'         => $morning,
            'afternoon'       => $afternoon,
            'todayRequests'   => $todayRequests,
            'rooms'           => $rooms,
            'canGuestOrder'   => Auth::canOrderForGuests() && setting('guest_ordering_enabled', '1') === '1',
            'activeTab'       => 'today',
        ], 'app'));
    }

    public function poll(): void
    {
        $user = Auth::requireRole(['employee', 'admin', 'staff']);
        $today = ScheduleService::today();
        OrderService::autoCancelExpiredOrders();
        $clientFp = Request::get('fp');

        $ordersSig = Database::fetchOne(
            "SELECT COUNT(*) AS cnt, MAX(updated_at) AS max_upd,
                    GROUP_CONCAT(CONCAT(id, ':', status, ':', COALESCE(estimated_minutes, 0), ':', COALESCE(accepted_by, 0)) ORDER BY id ASC) AS sig
             FROM orders
             WHERE user_id = ? AND order_date = ?",
            [$user['id'], $today]
        );
        $ovSig = Database::fetchOne(
            "SELECT COUNT(*) AS cnt, MAX(updated_at) AS max_upd FROM daily_overrides WHERE user_id = ? AND service_date = ?",
            [$user['id'], $today]
        );
        $annSig = Database::fetchOne(
            "SELECT COUNT(*) AS cnt, MAX(created_at) AS max_cr FROM staff_announcements WHERE announce_date = ? AND is_active = 1",
            [$today]
        );
        $slotsSig = Database::fetchAll("SELECT slot_key, service_time, cutoff_time, is_active FROM service_slots ORDER BY slot_key ASC");
        $fp = md5(json_encode([$ordersSig, $ovSig, $annSig, $slotsSig, (int)$user['is_paused']]));

        Response::json([
            'ok'          => true,
            'changed'     => $clientFp === null || $clientFp !== $fp,
            'fingerprint' => $fp,
        ]);
    }

    public function updateToday(): void
    {
        $user = Auth::requireRole(['employee', 'admin', 'staff']);
        $slotKey = Validator::enum(Request::post('slot_key'), ['morning', 'afternoon'], 'morning');
        $action = Validator::enum(Request::post('action'), ['custom', 'skip', 'reset'], 'custom');

        try {
            if ($action === 'skip') {
                OrderService::skipTodaySlot($user, $slotKey);
                Session::flash('success', __('skipped'));
            } elseif ($action === 'reset') {
                OrderService::resetTodayOverride($user, $slotKey);
                Session::flash('success', 'Reset to your regular default for today.');
            } else {
                $beverage = Validator::enum(Request::post('beverage'), ['tea', 'coffee'], 'tea');
                $sugar = Validator::enum(Request::post('sugar'), ['normal', 'less', 'none'], 'normal');
                $quantity = Validator::intRange(Request::post('quantity'), 1, 5, 1);
                $note = Validator::str(Request::post('note'), 200);
                OrderService::saveTodayOverride($user, $slotKey, $beverage, $sugar, $quantity, $note);
                Session::flash('success', 'Today\'s preference updated (your recurring default was not changed).');
            }
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/today');
    }

    public function settings(?string $defaultPostMode = null): void
    {
        $user = Auth::requireRole(['employee', 'admin', 'staff']);
        $canEditDeptRoom = setting('allow_employee_dept_room_edit', '1') === '1' || $user['role'] === 'admin';
        $defaultTab = $defaultPostMode === 'defaults' ? 'regular' : 'profile';
        $settingsTab = Validator::enum(Request::get('tab'), ['profile', 'regular', 'fun'], $defaultTab);

        if (Request::method() === 'POST') {
            $mode = Request::post('form_mode', $defaultPostMode ?? 'defaults');

            if ($mode === 'pause') {
                $paused = Request::post('is_paused') === '1' ? 1 : 0;
                Database::execute('UPDATE users SET is_paused = ?, updated_at = NOW() WHERE id = ?', [$paused, $user['id']]);
                Session::flash('success', $paused ? 'Regular tea/coffee orders turned OFF.' : 'Regular tea/coffee orders turned ON.');
                $redir = Request::post('redirect_to') === '/today' ? '/today' : '/settings?tab=regular';
                Response::redirect($redir);
            }

            if ($mode === 'monkey_pref') {
                $showMonkey = Request::post('show_funny_monkey') === '1' ? 1 : 0;
                if ($showMonkey === 1) {
                    Database::execute(
                        'UPDATE users SET show_funny_monkey = 1, monkey_dismissed_date = NULL, updated_at = NOW() WHERE id = ?',
                        [$user['id']]
                    );
                    Session::flash('success', 'Funny Tea Monkey turned ON!');
                } else {
                    Database::execute(
                        'UPDATE users SET show_funny_monkey = 0, updated_at = NOW() WHERE id = ?',
                        [$user['id']]
                    );
                    Session::flash('success', 'Funny Tea Monkey hidden permanently. You can turn it back ON anytime here.');
                }
                Response::redirect('/settings?tab=fun');
            }

            if ($mode === 'profile') {
                $fullName = Validator::str(Request::post('full_name'), 150);
                if ($fullName === '') {
                    Session::flash('error', 'Full Name is required.');
                    Response::redirect('/settings?tab=profile');
                }

                $deptId = $user['department_id'];
                $roomId = $user['room_id'];
                if ($canEditDeptRoom) {
                    $newDept = Validator::intRange(Request::post('department_id'), 1, 999999, 0);
                    $newRoom = Validator::intRange(Request::post('room_id'), 1, 999999, 0);
                    if ($newDept && Database::fetchOne('SELECT id FROM departments WHERE id = ? AND is_active = 1', [$newDept])) {
                        $deptId = $newDept;
                    }
                    if ($newRoom && Database::fetchOne("SELECT id FROM rooms WHERE id = ? AND is_active = 1 AND room_type = 'office'", [$newRoom])) {
                        $roomId = $newRoom;
                    }
                }

                Database::execute(
                    'UPDATE users SET full_name = ?, department_id = ?, room_id = ?, updated_at = NOW() WHERE id = ?',
                    [$fullName, $deptId, $roomId, $user['id']]
                );

                $newPassword = (string)Request::post('new_password', '');
                if ($newPassword !== '') {
                    if (strlen($newPassword) < 6) {
                        Session::flash('warning', 'Profile saved, but password was skipped (must be at least 6 characters).');
                    } else {
                        Database::execute(
                            'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?',
                            [password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]
                        );
                    }
                }

                if (!empty($_FILES['cup_photo']) && ($_FILES['cup_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    try {
                        $oldCup = $user['cup_image'];
                        $newCup = ImageService::saveCupPhoto($_FILES['cup_photo'], (int)$user['id']);
                        if ($newCup) {
                            Database::execute('UPDATE users SET cup_image = ?, updated_at = NOW() WHERE id = ?', [$newCup, $user['id']]);
                            ImageService::deleteStoredFile($oldCup);
                        }
                    } catch (\Throwable $e) {
                        Session::flash('warning', 'Profile updated, but cup photo upload failed: ' . $e->getMessage());
                        Response::redirect('/settings?tab=profile');
                    }
                }

                Session::flash('success', 'Profile updated successfully.');
                Response::redirect('/settings?tab=profile');
            }

            foreach (['morning', 'afternoon'] as $slotKey) {
                $enabled  = Request::post("{$slotKey}_enabled") === '1';
                $beverage = Validator::enum(Request::post("{$slotKey}_beverage"), ['tea', 'coffee'], 'tea');
                $sugar    = Validator::enum(Request::post("{$slotKey}_sugar"), ['normal', 'less', 'none'], 'normal');
                $quantity = Validator::intRange(Request::post("{$slotKey}_quantity"), 1, 5, 1);
                $note     = Validator::str(Request::post("{$slotKey}_note"), 200);

                OrderService::saveDefaultPreference((int)$user['id'], $slotKey, $enabled, $beverage, $sugar, $quantity, $note);
            }

            Session::flash('success', 'Regular preferences saved for future sessions.');
            Response::redirect('/settings?tab=regular');
        }

        $prefs = OrderService::getDefaultPreferences((int)$user['id'], $user['role']);
        $slots = ScheduleService::getSlots();
        $departments = Database::fetchAll('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC');
        $rooms = Database::fetchAll("SELECT id, name, floor FROM rooms WHERE is_active = 1 AND room_type = 'office' ORDER BY name ASC");

        Response::html(View::render('employee/settings', [
            'user'            => $user,
            'prefs'           => $prefs,
            'slots'           => $slots,
            'departments'     => $departments,
            'rooms'           => $rooms,
            'canEditDeptRoom' => $canEditDeptRoom,
            'canGuestOrder'   => Auth::canOrderForGuests() && setting('guest_ordering_enabled', '1') === '1',
            'settingsTab'     => $settingsTab,
            'activeTab'       => 'settings',
        ], 'app'));
    }

    public function defaults(): void
    {
        $this->settings('defaults');
    }

    public function orderNow(): void
    {
        $user = Auth::requireRole(['employee', 'admin', 'staff']);

        if (Request::method() === 'POST') {
            $beverage = Validator::enum(Request::post('beverage'), ['tea', 'coffee'], 'tea');
            $sugar = Validator::enum(Request::post('sugar'), ['normal', 'less', 'none'], 'normal');
            $quantity = Validator::intRange(Request::post('quantity'), 1, 20, 1);
            $roomId = Validator::intRange(Request::post('room_id'), 1, 999999, (int)($user['room_id'] ?? 0));
            $note = Validator::str(Request::post('note'), 220);
            $idempotencyKey = Validator::str(Request::post('idempotency_key'), 80);
            if ($idempotencyKey === '') {
                $idempotencyKey = bin2hex(random_bytes(16));
            }

            try {
                OrderService::createOnDemandOrder($user, $beverage, $sugar, $quantity, $roomId ?: null, $note, $idempotencyKey);
                Session::flash('success', 'Order submitted! Tea staff have been notified.');
                Response::redirect('/today');
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
                Response::redirect('/order-now');
            }
        }

        $rooms = Database::fetchAll('SELECT id, name, floor, room_type FROM rooms WHERE is_active = 1 ORDER BY room_type ASC, name ASC');

        Response::html(View::render('employee/order_now', [
            'user'           => $user,
            'rooms'          => $rooms,
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'activeTab'      => 'today',
        ], 'app'));
    }

    public function cancelOrder(): void
    {
        $user = Auth::requireAuth();
        $orderId = (int)Request::post('order_id', 0);

        $order = Database::fetchOne('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$orderId, $user['id']]);
        if (!$order) {
            if (Request::isAjax()) {
                Response::json(['ok' => false, 'error' => 'Order not found.'], 404);
            }
            Session::flash('error', 'Order not found.');
            Response::redirect('/today');
        }

        try {
            if ($order['status'] !== 'pending') {
                throw new \RuntimeException('Order already accepted/preparing and cannot be cancelled.');
            }
            OrderService::transitionStatus($orderId, 'cancelled', $user, 'Cancelled by requester');
            if (Request::isAjax()) {
                Response::json(['ok' => true, 'status' => 'cancelled']);
            }
            Session::flash('success', 'Order cancelled.');
        } catch (\Throwable $e) {
            if (Request::isAjax()) {
                Response::json(['ok' => false, 'error' => $e->getMessage()], 409);
            }
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/today');
    }

    public function deliverOrder(): void
    {
        $user = Auth::requireAuth();
        $orderId = (int)Request::post('order_id', 0);

        $order = Database::fetchOne('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$orderId, $user['id']]);
        if (!$order) {
            if (Request::isAjax()) {
                Response::json(['ok' => false, 'error' => 'Order not found.'], 404);
            }
            Session::flash('error', 'Order not found.');
            Response::redirect('/today');
        }

        try {
            if (!in_array($order['status'], ['pending', 'accepted', 'preparing', 'almost_done'], true)) {
                throw new \RuntimeException('Order is already ' . $order['status'] . '.');
            }
            $updated = OrderService::transitionStatus($orderId, 'delivered', $user, 'Marked delivered by requester');
            if (Request::isAjax()) {
                Response::json(['ok' => true, 'order' => $updated]);
            }
            Session::flash('success', 'Order marked as Delivered. Enjoy your refreshment!');
        } catch (\Throwable $e) {
            if (Request::isAjax()) {
                Response::json(['ok' => false, 'error' => $e->getMessage()], 409);
            }
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/today');
    }

    public function guestOrder(): void
    {
        $user = Auth::requireGuestPermission();
        if (setting('guest_ordering_enabled', '1') !== '1' && $user['role'] !== 'admin') {
            Session::flash('error', 'Guest ordering is currently disabled by Admin.');
            Response::redirect('/today');
        }

        if (Request::method() === 'POST') {
            $roomId = Validator::intRange(Request::post('room_id'), 1, 999999, 0);
            $meetingTitle = Validator::str(Request::post('meeting_title'), 140);
            $guestCountRaw = Request::post('guest_count');
            $guestCount = is_numeric($guestCountRaw) && (int)$guestCountRaw > 0 ? (int)$guestCountRaw : null;
            $requestedWithin = self::parseRequestedWithin(
                Request::post('needed_mode'),
                Request::post('needed_minutes'),
                Request::post('requested_within')
            );
            $note = Validator::str(Request::post('note'), 220);
            $idempotencyKey = Validator::str(Request::post('idempotency_key'), 80) ?: bin2hex(random_bytes(16));

            $beverages = $_POST['item_beverage'] ?? [];
            $sugars = $_POST['item_sugar'] ?? [];
            $quantities = $_POST['item_quantity'] ?? [];

            $items = [];
            if (is_array($beverages)) {
                foreach ($beverages as $idx => $bev) {
                    $qty = (int)($quantities[$idx] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }
                    $items[] = [
                        'beverage' => Validator::enum($bev, ['tea', 'coffee'], 'tea'),
                        'sugar'    => Validator::enum($sugars[$idx] ?? 'normal', ['normal', 'less', 'none'], 'normal'),
                        'quantity' => min(100, $qty),
                    ];
                }
            }

            try {
                OrderService::createGuestOrder($user, $roomId, $meetingTitle, $guestCount, $items, $note, $idempotencyKey, $requestedWithin);
                Session::flash('success', 'Guest/Meeting order sent to tea staff!');
                Response::redirect('/today');
            } catch (\Throwable $e) {
                Session::flash('error', $e->getMessage());
                Response::redirect('/guest-order');
            }
        }

        $meetingRooms = Database::fetchAll("SELECT id, name, floor FROM rooms WHERE is_active = 1 ORDER BY CASE WHEN room_type='meeting' THEN 0 ELSE 1 END, name ASC");

        Response::html(View::render('employee/guest_order', [
            'user'           => $user,
            'rooms'          => $meetingRooms,
            'idempotencyKey' => bin2hex(random_bytes(16)),
            'activeTab'      => 'more',
        ], 'app'));
    }

    /** Parse and normalize optional guest timing ("Immediate", "After/Before/Within N min"). */
    private static function parseRequestedWithin(?string $modeRaw, ?string $minutesRaw, ?string $fallbackRaw): ?string
    {
        $mode = Validator::enum($modeRaw, ['Immediate', 'After', 'Before', 'Within'], '');
        $mins = Validator::intRange($minutesRaw, 1, 480, 0);

        if ($mode === 'Immediate') {
            return 'Immediate';
        }
        if ($mode !== '' && $mins > 0) {
            return "{$mode} {$mins} min";
        }

        $raw = trim((string)($fallbackRaw ?? ''));
        if (strcasecmp($raw, 'Immediate') === 0) {
            return 'Immediate';
        }
        if (preg_match('/^(After|Before|Within)\s+([1-9]\d{0,2})\s*(min|mins|minute|minutes|hr|hrs|hour|hours)$/i', $raw, $m)) {
            $unit = str_starts_with(strtolower($m[3]), 'h') ? 'hour' : 'min';
            $val = (int)$m[2];
            $maxVal = $unit === 'hour' ? 8 : 480;
            if ($val >= 1 && $val <= $maxVal) {
                return ucfirst(strtolower($m[1])) . " {$val} {$unit}";
            }
        }
        return null;
    }

    public function history(): void
    {
        $user = Auth::requireAuth();
        $filter = Validator::enum(Request::get('filter'), ['today', '7d', '30d', 'older'], '7d');
        $page = max(1, (int)Request::get('page', 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;
        $today = ScheduleService::today();

        $whereDate = match ($filter) {
            'today' => 'o.order_date = ?',
            '7d'    => 'o.order_date >= DATE_SUB(?, INTERVAL 6 DAY) AND o.order_date <= ?',
            '30d'   => 'o.order_date >= DATE_SUB(?, INTERVAL 29 DAY) AND o.order_date <= ?',
            'older' => 'o.order_date < DATE_SUB(?, INTERVAL 29 DAY)',
        };
        $params = match ($filter) {
            'today', 'older' => [$user['id'], $today],
            default          => [$user['id'], $today, $today],
        };

        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM orders o WHERE o.user_id = ? AND {$whereDate}",
            $params
        );
        $total = (int)($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $perPage));

        // Summary of requested vs delivered (excluding cancelled from consumed!)
        $summaryRow = Database::fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN o.status NOT IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS requested_cups,
                COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.quantity ELSE 0 END), 0) AS delivered_cups,
                COALESCE(SUM(CASE WHEN o.status IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS cancelled_cups
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = ? AND {$whereDate}",
            $params
        );

        $orders = Database::fetchAll(
            "SELECT o.* FROM orders o
             WHERE o.user_id = ? AND {$whereDate}
             ORDER BY o.order_date DESC, o.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $orders = OrderService::getOrdersWithItems($orders);

        Response::html(View::render('employee/history', [
            'user'       => $user,
            'filter'     => $filter,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
            'summary'    => $summaryRow,
            'orders'     => $orders,
            'activeTab'  => 'history',
        ], 'app'));
    }

    public function profile(): void
    {
        $this->settings('profile');
    }

    public function dismissMonkeyToday(): void
    {
        $user = Auth::requireAuth();
        $today = ScheduleService::today();
        Database::execute(
            'UPDATE users SET monkey_dismissed_date = ?, updated_at = NOW() WHERE id = ?',
            [$today, $user['id']]
        );
        Response::json(['ok' => true, 'dismissed_date' => $today]);
    }
}
