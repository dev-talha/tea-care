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
use App\Services\OrderService;
use App\Services\PushService;
use App\Services\ScheduleService;

class StaffController
{
    public function dashboard(): void
    {
        $user = Auth::requireRole(['staff', 'admin']);
        $today = ScheduleService::today();
        OrderService::autoCancelExpiredOrders();

        // Mobile opens on 'home' (summary only); Desktop shows counters + Skip List on 'home'/'skipped'
        $tab = Validator::enum(Request::get('tab'), ['home', 'skipped', 'morning', 'afternoon', 'urgent', 'completed'], 'home');
        $highlight = (int)Request::get('highlight', 0);

        $filterRoomId = Validator::intRange(Request::get('room_id'), 0, 999999, 0) ?: null;
        $filterDeptId = Validator::intRange(Request::get('dept_id'), 0, 999999, 0) ?: null;
        $page = max(1, (int)Request::get('page', 1));
        $perPage = 18;

        $counters = Database::fetchOne(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
                SUM(CASE WHEN status IN ('accepted', 'preparing', 'almost_done') THEN 1 ELSE 0 END) AS preparing_cnt,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_cnt
             FROM orders
             WHERE order_date = ?",
            [$today]
        );

        $allSkipped = OrderService::getSkippedListForStaff($today, $filterRoomId, $filterDeptId);
        $skippedFilteredCount = count($allSkipped);
        $skipTotalPages = max(1, (int)ceil($skippedFilteredCount / $perPage));
        $skipPage = in_array($tab, ['home', 'skipped'], true) ? min($page, $skipTotalPages) : 1;
        $skippedList = array_slice($allSkipped, ($skipPage - 1) * $perPage, $perPage);
        $totalSkippedAll = count(OrderService::getSkippedListForStaff($today, null, null));

        $whereExtra = '';
        $extraParams = [];
        if ($filterRoomId) {
            $whereExtra .= ' AND o.room_id = ?';
            $extraParams[] = $filterRoomId;
        }
        if ($filterDeptId) {
            $whereExtra .= ' AND u.department_id = ?';
            $extraParams[] = $filterDeptId;
        }

        $urgentOrders = Database::fetchAll(
            "SELECT o.* FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             WHERE o.order_date = ? AND o.order_type = 'on_demand' AND o.status IN ('pending', 'accepted', 'preparing', 'almost_done') {$whereExtra}
             ORDER BY FIELD(o.status, 'pending', 'accepted', 'preparing', 'almost_done'), o.created_at ASC",
            array_merge([$today], $extraParams)
        );
        $urgentOrders = OrderService::getOrdersWithItems($urgentOrders);

        $guestOrders = Database::fetchAll(
            "SELECT o.* FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             WHERE o.order_date = ? AND o.order_type = 'guest' AND o.status IN ('pending', 'accepted', 'preparing', 'almost_done') {$whereExtra}
             ORDER BY FIELD(o.status, 'pending', 'accepted', 'preparing', 'almost_done'), o.created_at ASC",
            array_merge([$today], $extraParams)
        );
        $guestOrders = OrderService::getOrdersWithItems($guestOrders);

        $urgentTotalCount = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM orders WHERE order_date = ? AND order_type IN ('on_demand', 'guest') AND status IN ('pending', 'accepted', 'preparing', 'almost_done')",
            [$today]
        )['cnt'] ?? 0);

        $morningData = OrderService::getSlotDataForStaff($today, 'morning', $filterRoomId, $filterDeptId);
        $afternoonData = OrderService::getSlotDataForStaff($today, 'afternoon', $filterRoomId, $filterDeptId);

        $completedTotalCount = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             WHERE o.order_date = ? AND o.status IN ('delivered', 'cancelled', 'system_cancelled') {$whereExtra}",
            array_merge([$today], $extraParams)
        )['cnt'] ?? 0);
        $completedTotalPages = max(1, (int)ceil($completedTotalCount / $perPage));
        $completedPage = $tab === 'completed' ? min($page, $completedTotalPages) : 1;
        $completedOffset = ($completedPage - 1) * $perPage;

        $completedOrders = Database::fetchAll(
            "SELECT o.* FROM orders o
             LEFT JOIN users u ON u.id = o.user_id
             WHERE o.order_date = ? AND o.status IN ('delivered', 'cancelled', 'system_cancelled') {$whereExtra}
             ORDER BY o.updated_at DESC
             LIMIT {$perPage} OFFSET {$completedOffset}",
            array_merge([$today], $extraParams)
        );
        $completedOrders = OrderService::getOrdersWithItems($completedOrders);

        $unreadNotifs = Database::fetchAll(
            'SELECT * FROM staff_inapp_notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10'
        );

        $roomsList = Database::fetchAll('SELECT id, name, room_type FROM rooms WHERE is_active = 1 ORDER BY name ASC');
        $deptsList = Database::fetchAll('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC');

        Response::html(View::render('staff/dashboard', [
            'user'                 => $user,
            'today'                => $today,
            'tab'                  => $tab,
            'highlight'            => $highlight,
            'filterRoomId'         => $filterRoomId,
            'filterDeptId'         => $filterDeptId,
            'roomsList'            => $roomsList,
            'deptsList'            => $deptsList,
            'counters'             => $counters,
            'totalSkippedAll'      => $totalSkippedAll,
            'skippedFilteredCount' => $skippedFilteredCount,
            'skippedList'          => $skippedList,
            'skipPage'             => $skipPage,
            'skipTotalPages'       => $skipTotalPages,
            'urgentTotalCount'     => $urgentTotalCount,
            'urgentOrders'         => $urgentOrders,
            'guestOrders'          => $guestOrders,
            'morningData'          => $morningData,
            'afternoonData'        => $afternoonData,
            'completedOrders'      => $completedOrders,
            'completedTotalCount'  => $completedTotalCount,
            'completedPage'        => $completedPage,
            'completedTotalPages'  => $completedTotalPages,
            'unreadNotifs'         => $unreadNotifs,
            'activeTab'            => $tab,
        ], 'app'));
    }

    public function updateStatus(): void
    {
        $user = Auth::requireRole(['staff', 'admin']);
        $orderId = (int)Request::post('order_id', 0);
        $status = Validator::enum(Request::post('status'), ['accepted', 'preparing', 'almost_done', 'delivered', 'cancelled'], 'preparing');
        $note = Validator::str(Request::post('note'), 200);
        $estimatedMinutes = $status === 'almost_done'
            ? Validator::intRange(Request::post('estimated_minutes'), 1, 30, 5)
            : null;

        try {
            $updated = OrderService::transitionStatus($orderId, $status, $user, $note !== '' ? $note : null, $estimatedMinutes);
            if (Request::isAjax()) {
                Response::json(['ok' => true, 'order' => $updated]);
            }
            $label = match ($status) {
                'preparing'   => 'Preparing...',
                'almost_done' => 'Almost Done (~' . ($estimatedMinutes ?? 5) . ' min)',
                default       => ucfirst($status),
            };
            Session::flash('success', 'Order #' . $orderId . ' marked as ' . $label . '.');
        } catch (\Throwable $e) {
            if (Request::isAjax()) {
                Response::json(['ok' => false, 'error' => $e->getMessage()], 409);
            }
            Session::flash('error', $e->getMessage());
        }

        $tab = Request::post('tab', 'urgent');
        $roomId = Request::post('filter_room_id', '');
        $deptId = Request::post('filter_dept_id', '');
        $qs = http_build_query(array_filter(['tab' => $tab, 'room_id' => $roomId, 'dept_id' => $deptId]));
        Response::redirect('/staff?' . $qs);
    }

    public function deliverRoom(): void
    {
        $user = Auth::requireRole(['staff', 'admin']);
        $today = ScheduleService::today();
        $roomId = (int)Request::post('room_id', 0);
        $slotKey = Validator::enum(Request::post('slot_key'), ['morning', 'afternoon'], 'morning');
        $orderIds = $_POST['order_ids'] ?? [];
        if (!is_array($orderIds)) {
            $orderIds = [];
        }

        try {
            $count = OrderService::deliverRoomOrders($roomId, $today, $slotKey, $orderIds, $user);
            if (Request::isAjax()) {
                Response::json(['ok' => true, 'delivered_count' => $count]);
            }
            Session::flash('success', "{$count} order(s) in room marked delivered.");
        } catch (\Throwable $e) {
            if (Request::isAjax()) {
                Response::json(['ok' => false, 'error' => $e->getMessage()], 409);
            }
            Session::flash('error', $e->getMessage());
        }

        $roomFilter = Request::post('filter_room_id', '');
        $deptFilter = Request::post('filter_dept_id', '');
        $qs = http_build_query(array_filter(['tab' => $slotKey, 'room_id' => $roomFilter, 'dept_id' => $deptFilter]));
        Response::redirect('/staff?' . $qs);
    }

    public function settings(): void
    {
        $user = Auth::requireRole(['staff', 'admin']);
        $today = ScheduleService::today();
        $subs = Database::fetchAll(
            'SELECT * FROM push_subscriptions WHERE user_id = ? ORDER BY updated_at DESC',
            [$user['id']]
        );
        $vapid = PushService::getVapidConfig();
        $announcements = AnnouncementService::getActiveForDate($today);
        $morningSlot = ScheduleService::getSlot('morning');
        $afternoonSlot = ScheduleService::getSlot('afternoon');

        Response::html(View::render('staff/settings', [
            'user'          => $user,
            'today'         => $today,
            'subs'          => $subs,
            'vapid'         => $vapid,
            'announcements' => $announcements,
            'morningSlot'   => $morningSlot,
            'afternoonSlot' => $afternoonSlot,
            'activeTab'     => 'staff_settings',
        ], 'app'));
    }

    public function notifications(): void
    {
        $this->settings();
    }

    public function markNotificationsRead(): void
    {
        Auth::requireRole(['staff', 'admin']);
        Database::execute('UPDATE staff_inapp_notifications SET is_read = 1 WHERE is_read = 0');
        if (Request::isAjax()) {
            Response::json(['ok' => true]);
        }
        Response::redirect('/staff');
    }

    public function postAnnouncement(): void
    {
        $user = Auth::requireRole(['staff', 'admin']);
        $today = ScheduleService::today();
        $scope = Validator::enum(Request::post('scope'), ['all', 'morning', 'afternoon'], 'all');
        $type = Validator::enum(Request::post('announce_type'), ['unavailable', 'time_change', 'info'], 'unavailable');
        $adjustedTime = Validator::str(Request::post('adjusted_time'), 10);
        $reason = Validator::str(Request::post('reason'), 290);

        try {
            AnnouncementService::create((int)$user['id'], $today, $scope, $type, $adjustedTime !== '' ? $adjustedTime : null, $reason);
            Session::flash('success', 'Announcement published to Employee Dashboards.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/staff/settings');
    }

    public function clearAnnouncement(): void
    {
        $user = Auth::requireRole(['staff', 'admin']);
        $id = (int)Request::post('id', 0);
        if ($id > 0) {
            AnnouncementService::deactivate($id, (int)$user['id']);
            Session::flash('success', 'Announcement cleared.');
        }
        Response::redirect('/staff/settings');
    }

    public function poll(): void
    {
        Auth::requireRole(['staff', 'admin']);
        $today = ScheduleService::today();
        OrderService::autoCancelExpiredOrders();
        $clientFp = Request::get('fp');

        $stats = Database::fetchOne(
            "SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
                SUM(CASE WHEN status IN ('accepted', 'preparing', 'almost_done') THEN 1 ELSE 0 END) AS preparing_cnt,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered_cnt,
                SUM(CASE WHEN order_type IN ('on_demand', 'guest') AND status IN ('pending', 'accepted', 'preparing', 'almost_done') THEN 1 ELSE 0 END) AS urgent_cnt,
                MAX(updated_at) AS last_updated,
                COUNT(*) AS total_cnt,
                GROUP_CONCAT(CONCAT(id, ':', status, ':', COALESCE(estimated_minutes, 0)) ORDER BY id ASC) AS sig
             FROM orders
             WHERE order_date = ?",
            [$today]
        );

        $unread = Database::fetchOne('SELECT COUNT(*) AS cnt FROM staff_inapp_notifications WHERE is_read = 0');
        $skippedCnt = count(OrderService::getSkippedListForStaff($today, null, null));
        $fingerprint = md5(json_encode([$stats, $unread, $skippedCnt]));

        Response::json([
            'ok'            => true,
            'pending'       => (int)($stats['pending_cnt'] ?? 0),
            'preparing'     => (int)($stats['preparing_cnt'] ?? 0),
            'delivered'     => (int)($stats['delivered_cnt'] ?? 0),
            'urgent'        => (int)($stats['urgent_cnt'] ?? 0),
            'skipped'       => $skippedCnt,
            'unread_notifs' => (int)($unread['cnt'] ?? 0),
            'fingerprint'   => $fingerprint,
            'changed'       => $clientFp === null || $clientFp !== $fingerprint,
        ]);
    }
}
