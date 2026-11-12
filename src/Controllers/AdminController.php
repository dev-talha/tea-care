<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\AuditService;
use App\Services\ImageService;
use App\Services\OrderService;
use App\Services\PushService;
use App\Services\ScheduleService;
use App\Services\WhatsAppService;

class AdminController
{
    public function dashboard(): void
    {
        $user = Auth::requireRole(['admin']);
        $today = ScheduleService::today();

        $activeEmployees = (int)(Database::fetchOne("SELECT COUNT(*) AS cnt FROM users WHERE status = 'active' AND role = 'employee'")['cnt'] ?? 0);
        $pendingApprovals = (int)(Database::fetchOne("SELECT COUNT(*) AS cnt FROM users WHERE status = 'pending'")['cnt'] ?? 0);

        $morningEstimate = OrderService::getSlotDataForStaff($today, 'morning');
        $afternoonEstimate = OrderService::getSlotDataForStaff($today, 'afternoon');

        $pendingOnDemand = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM orders WHERE order_date = ? AND order_type = 'on_demand' AND status IN ('pending','accepted','preparing')",
            [$today]
        )['cnt'] ?? 0);

        $pendingGuest = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM orders WHERE order_date = ? AND order_type = 'guest' AND status IN ('pending','accepted','preparing')",
            [$today]
        )['cnt'] ?? 0);

        $deliveredToday = (int)(Database::fetchOne(
            "SELECT COALESCE(SUM(oi.quantity), 0) AS cups
             FROM orders o INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date = ? AND o.status = 'delivered'",
            [$today]
        )['cups'] ?? 0);

        $chartRows = Database::fetchAll(
            "SELECT o.order_date,
                    COALESCE(SUM(CASE WHEN oi.beverage = 'tea' AND o.status NOT IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS tea_cups,
                    COALESCE(SUM(CASE WHEN oi.beverage = 'coffee' AND o.status NOT IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS coffee_cups
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date >= DATE_SUB(?, INTERVAL 6 DAY) AND o.order_date <= ?
             GROUP BY o.order_date
             ORDER BY o.order_date ASC",
            [$today, $today]
        );

        $deptTotal = (int)(Database::fetchOne(
            "SELECT COUNT(DISTINCT COALESCE(NULLIF(o.department_name_snapshot, ''), 'Unassigned')) AS cnt
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date = ?",
            [$today]
        )['cnt'] ?? 0);
        $deptPg = Paginator::paginate($deptTotal, 'dept_page', 'dept_per_page');

        $deptSummary = Database::fetchAll(
            "SELECT COALESCE(NULLIF(o.department_name_snapshot, ''), 'Unassigned') AS dept_name,
                    COALESCE(SUM(CASE WHEN o.status NOT IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS total_cups,
                    COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.quantity ELSE 0 END), 0) AS delivered_cups
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date = ?
             GROUP BY dept_name
             ORDER BY total_cups DESC
             LIMIT {$deptPg['per_page']} OFFSET {$deptPg['offset']}",
            [$today]
        );

        $roomTotal = (int)(Database::fetchOne(
            "SELECT COUNT(DISTINCT COALESCE(NULLIF(o.room_name_snapshot, ''), 'Unassigned')) AS cnt
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date = ?",
            [$today]
        )['cnt'] ?? 0);
        $roomPg = Paginator::paginate($roomTotal, 'room_page', 'room_per_page');

        $roomSummary = Database::fetchAll(
            "SELECT COALESCE(NULLIF(o.room_name_snapshot, ''), 'Unassigned') AS room_name,
                    COALESCE(SUM(CASE WHEN o.status NOT IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS total_cups,
                    COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.quantity ELSE 0 END), 0) AS delivered_cups
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date = ?
             GROUP BY room_name
             ORDER BY total_cups DESC
             LIMIT {$roomPg['per_page']} OFFSET {$roomPg['offset']}",
            [$today]
        );

        $recentAuditLogs = Database::fetchAll(
            'SELECT actor_name, action, entity_type, entity_id, details, created_at
             FROM audit_logs
             ORDER BY created_at DESC
             LIMIT 8'
        );

        $recentUsers = Database::fetchAll(
            'SELECT id, employee_id, full_name, role, status, created_at
             FROM users
             ORDER BY created_at DESC
             LIMIT 6'
        );

        Response::html(View::render('admin/dashboard', [
            'user'              => $user,
            'today'             => $today,
            'activeEmployees'   => $activeEmployees,
            'pendingApprovals'  => $pendingApprovals,
            'morningSummary'    => $morningEstimate['summary'],
            'morningFinalized'  => $morningEstimate['is_finalized'],
            'afternoonSummary'  => $afternoonEstimate['summary'],
            'afternoonFinalized'=> $afternoonEstimate['is_finalized'],
            'pendingOnDemand'   => $pendingOnDemand,
            'pendingGuest'      => $pendingGuest,
            'deliveredToday'    => $deliveredToday,
            'chartRows'         => $chartRows,
            'deptSummary'       => $deptSummary,
            'deptPg'            => $deptPg,
            'roomSummary'       => $roomSummary,
            'roomPg'            => $roomPg,
            'recentAuditLogs'   => $recentAuditLogs,
            'recentUsers'       => $recentUsers,
            'activeNav'         => 'dashboard',
        ], 'admin'));
    }

    public function employees(): void
    {
        $admin = Auth::requireRole(['admin']);

        if (Request::method() === 'POST') {
            $action = Request::post('action', 'create');

            if ($action === 'create') {
                $fullName = Validator::str(Request::post('full_name'), 150);
                $empId = strtoupper(Validator::str(Request::post('employee_id'), 50));
                $role = Validator::enum(Request::post('role'), ['employee', 'staff', 'admin'], 'employee');
                $deptId = Validator::intRange(Request::post('department_id'), 1, 999999, 0) ?: null;
                $roomId = Validator::intRange(Request::post('room_id'), 1, 999999, 0) ?: null;
                $canGuest = Request::post('can_order_for_guests') === '1' ? 1 : 0;
                $password = (string)Request::post('password', '');

                if ($fullName === '' || $empId === '' || strlen($password) < 6) {
                    Session::flash('error', 'Full name, unique Employee ID, and password (min 6 chars) are required.');
                    Response::redirect('/admin/employees');
                }
                if (Database::fetchOne('SELECT id FROM users WHERE employee_id = ?', [$empId])) {
                    Session::flash('error', 'Employee ID already exists.');
                    Response::redirect('/admin/employees');
                }

                $newId = (int)Database::insert(
                    "INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status, can_order_for_guests, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())",
                    [$empId, $fullName, password_hash($password, PASSWORD_DEFAULT), $role, $deptId, $roomId, $canGuest]
                );
                if ($role === 'employee') {
                    OrderService::seedUserDefaultPreferences($newId);
                }
                AuditService::log((int)$admin['id'], $admin['full_name'], 'create_user', 'user', $newId, ['employee_id' => $empId, 'role' => $role]);
                Session::flash('success', "Account {$empId} created.");
            } elseif ($action === 'update') {
                $uid = (int)Request::post('user_id', 0);
                $target = Database::fetchOne('SELECT * FROM users WHERE id = ?', [$uid]);
                if (!$target) {
                    Session::flash('error', 'User not found.');
                    Response::redirect('/admin/employees');
                }

                $fullName = Validator::str(Request::post('full_name'), 150) ?: $target['full_name'];
                $role = Validator::enum(Request::post('role'), ['employee', 'staff', 'admin'], $target['role']);
                $status = Validator::enum(Request::post('status'), ['pending', 'active', 'disabled'], $target['status']);
                $deptId = Validator::intRange(Request::post('department_id'), 1, 999999, 0) ?: null;
                $roomId = Validator::intRange(Request::post('room_id'), 1, 999999, 0) ?: null;
                $canGuest = Request::post('can_order_for_guests') === '1' ? 1 : 0;

                // Prevent admin from accidentally disabling themselves
                if ($uid === (int)$admin['id'] && ($status !== 'active' || $role !== 'admin')) {
                    Session::flash('error', 'You cannot deactivate or demote your own active admin account.');
                    Response::redirect('/admin/employees');
                }

                Database::execute(
                    'UPDATE users SET full_name = ?, role = ?, status = ?, department_id = ?, room_id = ?, can_order_for_guests = ?, updated_at = NOW() WHERE id = ?',
                    [$fullName, $role, $status, $deptId, $roomId, $canGuest, $uid]
                );

                $newPwd = (string)Request::post('reset_password', '');
                if ($newPwd !== '' && strlen($newPwd) >= 6) {
                    Database::execute('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?', [password_hash($newPwd, PASSWORD_DEFAULT), $uid]);
                }

                AuditService::log((int)$admin['id'], $admin['full_name'], 'update_user', 'user', $uid, [
                    'role' => $role,
                    'status' => $status,
                    'can_order_for_guests' => $canGuest,
                    'pwd_reset' => $newPwd !== '',
                ]);
                Session::flash('success', "Updated {$target['employee_id']}.");
            } elseif ($action === 'approve') {
                $uid = (int)Request::post('user_id', 0);
                Database::execute("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ? AND status = 'pending'", [$uid]);
                AuditService::log((int)$admin['id'], $admin['full_name'], 'approve_user', 'user', $uid);
                Session::flash('success', 'User approved and activated.');
            }

            Response::redirect('/admin/employees');
        }

        $q = Validator::str(Request::get('q'), 80);
        $roleFilter = Validator::enum(Request::get('role'), ['all', 'employee', 'staff', 'admin'], 'all');
        $statusFilter = Validator::enum(Request::get('status'), ['all', 'pending', 'active', 'disabled'], 'all');

        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.full_name LIKE ? OR u.employee_id LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        if ($roleFilter !== 'all') {
            $where[] = 'u.role = ?';
            $params[] = $roleFilter;
        }
        if ($statusFilter !== 'all') {
            $where[] = 'u.status = ?';
            $params[] = $statusFilter;
        }

        $totalUsers = (int)(Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM users u WHERE ' . implode(' AND ', $where),
            $params
        )['cnt'] ?? 0);
        $usersPg = Paginator::paginate($totalUsers, 'page', 'per_page');
        $perPage = $usersPg['per_page'];
        $offset = $usersPg['offset'];

        $users = Database::fetchAll(
            'SELECT u.*, d.name AS department_name, r.name AS room_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN rooms r ON r.id = u.room_id
             WHERE ' . implode(' AND ', $where) . "
             ORDER BY FIELD(u.status, \"pending\", \"active\", \"disabled\"), u.full_name ASC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $departments = Database::fetchAll('SELECT * FROM departments ORDER BY is_active DESC, name ASC');
        $rooms = Database::fetchAll('SELECT * FROM rooms ORDER BY is_active DESC, name ASC');

        Response::html(View::render('admin/employees', [
            'user'         => $admin,
            'users'        => $users,
            'departments'  => $departments,
            'rooms'        => $rooms,
            'q'            => $q,
            'roleFilter'   => $roleFilter,
            'statusFilter' => $statusFilter,
            'usersPg'      => $usersPg,
            'activeNav'    => 'employees',
        ], 'admin'));
    }

    public function departments(): void
    {
        $admin = Auth::requireRole(['admin']);

        if (Request::method() === 'POST') {
            $action = Request::post('action', 'create');
            if ($action === 'create') {
                $name = Validator::str(Request::post('name'), 120);
                if ($name === '') {
                    Session::flash('error', 'Department name is required.');
                } elseif (Database::fetchOne('SELECT id FROM departments WHERE name = ?', [$name])) {
                    Session::flash('error', 'Department already exists.');
                } else {
                    $id = (int)Database::insert('INSERT INTO departments (name, is_active) VALUES (?, 1)', [$name]);
                    AuditService::log((int)$admin['id'], $admin['full_name'], 'create_department', 'department', $id, ['name' => $name]);
                    Session::flash('success', 'Department added.');
                }
            } elseif ($action === 'update') {
                $id = (int)Request::post('id', 0);
                $name = Validator::str(Request::post('name'), 120);
                $active = Request::post('is_active') === '1' ? 1 : 0;
                if ($id > 0 && $name !== '') {
                    Database::execute('UPDATE departments SET name = ?, is_active = ? WHERE id = ?', [$name, $active, $id]);
                    AuditService::log((int)$admin['id'], $admin['full_name'], 'update_department', 'department', $id, ['name' => $name, 'is_active' => $active]);
                    Session::flash('success', 'Department updated.');
                }
            } elseif ($action === 'delete') {
                $id = (int)Request::post('id', 0);
                $refUsers = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM users WHERE department_id = ?', [$id])['cnt'] ?? 0);
                $refRooms = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM rooms WHERE department_id = ?', [$id])['cnt'] ?? 0);
                if ($refUsers > 0 || $refRooms > 0) {
                    Session::flash('error', 'Cannot delete department referenced by users or rooms. Deactivate it instead.');
                } else {
                    Database::execute('DELETE FROM departments WHERE id = ?', [$id]);
                    AuditService::log((int)$admin['id'], $admin['full_name'], 'delete_department', 'department', $id);
                    Session::flash('success', 'Department deleted.');
                }
            }
            Response::redirect('/admin/departments');
        }

        $totalDepts = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM departments')['cnt'] ?? 0);
        $deptPg = Paginator::paginate($totalDepts, 'page', 'per_page');
        $departments = Database::fetchAll(
            "SELECT d.*, (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id) AS user_count
             FROM departments d ORDER BY d.is_active DESC, d.name ASC
             LIMIT {$deptPg['per_page']} OFFSET {$deptPg['offset']}"
        );

        Response::html(View::render('admin/departments', [
            'user'        => $admin,
            'departments' => $departments,
            'deptPg'      => $deptPg,
            'activeNav'   => 'departments',
        ], 'admin'));
    }

    public function rooms(): void
    {
        $admin = Auth::requireRole(['admin']);

        if (Request::method() === 'POST') {
            $action = Request::post('action', 'create');
            if ($action === 'create') {
                $name = Validator::str(Request::post('name'), 120);
                $floor = Validator::str(Request::post('floor'), 60);
                $deptId = Validator::intRange(Request::post('department_id'), 1, 999999, 0) ?: null;
                $roomType = Validator::enum(Request::post('room_type'), ['office', 'meeting'], 'office');
                if ($name === '') {
                    Session::flash('error', 'Room name/number is required.');
                } elseif (Database::fetchOne('SELECT id FROM rooms WHERE name = ?', [$name])) {
                    Session::flash('error', 'Room already exists.');
                } else {
                    $id = (int)Database::insert(
                        'INSERT INTO rooms (name, floor, department_id, room_type, is_active) VALUES (?, ?, ?, ?, 1)',
                        [$name, $floor ?: null, $deptId, $roomType]
                    );
                    AuditService::log((int)$admin['id'], $admin['full_name'], 'create_room', 'room', $id, ['name' => $name, 'type' => $roomType]);
                    Session::flash('success', 'Room added.');
                }
            } elseif ($action === 'update') {
                $id = (int)Request::post('id', 0);
                $name = Validator::str(Request::post('name'), 120);
                $floor = Validator::str(Request::post('floor'), 60);
                $deptId = Validator::intRange(Request::post('department_id'), 1, 999999, 0) ?: null;
                $roomType = Validator::enum(Request::post('room_type'), ['office', 'meeting'], 'office');
                $active = Request::post('is_active') === '1' ? 1 : 0;
                if ($id > 0 && $name !== '') {
                    Database::execute(
                        'UPDATE rooms SET name = ?, floor = ?, department_id = ?, room_type = ?, is_active = ? WHERE id = ?',
                        [$name, $floor ?: null, $deptId, $roomType, $active, $id]
                    );
                    AuditService::log((int)$admin['id'], $admin['full_name'], 'update_room', 'room', $id, ['name' => $name, 'is_active' => $active]);
                    Session::flash('success', 'Room updated.');
                }
            } elseif ($action === 'delete') {
                $id = (int)Request::post('id', 0);
                $refUsers = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM users WHERE room_id = ?', [$id])['cnt'] ?? 0);
                $refOrders = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM orders WHERE room_id = ?', [$id])['cnt'] ?? 0);
                if ($refUsers > 0 || $refOrders > 0) {
                    Session::flash('error', 'Cannot delete room referenced by users or historical orders. Deactivate it instead.');
                } else {
                    Database::execute('DELETE FROM rooms WHERE id = ?', [$id]);
                    AuditService::log((int)$admin['id'], $admin['full_name'], 'delete_room', 'room', $id);
                    Session::flash('success', 'Room deleted.');
                }
            }
            Response::redirect('/admin/rooms');
        }

        $totalRooms = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM rooms')['cnt'] ?? 0);
        $roomPg = Paginator::paginate($totalRooms, 'page', 'per_page');
        $rooms = Database::fetchAll(
            "SELECT r.*, d.name AS department_name, (SELECT COUNT(*) FROM users u WHERE u.room_id = r.id) AS user_count
             FROM rooms r LEFT JOIN departments d ON d.id = r.department_id
             ORDER BY r.is_active DESC, r.room_type ASC, r.name ASC
             LIMIT {$roomPg['per_page']} OFFSET {$roomPg['offset']}"
        );
        $departments = Database::fetchAll('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC');

        Response::html(View::render('admin/rooms', [
            'user'        => $admin,
            'rooms'       => $rooms,
            'roomPg'      => $roomPg,
            'departments' => $departments,
            'activeNav'   => 'rooms',
        ], 'admin'));
    }

    public function orders(): void
    {
        $admin = Auth::requireRole(['admin']);

        if (Request::method() === 'POST') {
            $orderId = (int)Request::post('order_id', 0);
            $newStatus = Validator::enum(Request::post('status'), ['pending', 'accepted', 'preparing', 'almost_done', 'delivered', 'cancelled', 'system_cancelled'], 'pending');
            $reason = Validator::str(Request::post('reason'), 220);

            if ($reason === '') {
                Session::flash('error', 'Admin correction requires an audit reason.');
                Response::redirect('/admin/orders');
            }

            $order = Database::fetchOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
            if ($order) {
                $oldStatus = $order['status'];
                Database::transaction(function () use ($orderId, $newStatus, $oldStatus, $reason, $admin): void {
                    Database::execute('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $orderId]);
                    Database::insert(
                        'INSERT INTO order_events (order_id, actor_id, actor_name, from_status, to_status, note, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())',
                        [$orderId, $admin['id'], $admin['full_name'], $oldStatus, $newStatus, 'Admin correction: ' . $reason]
                    );
                    AuditService::log((int)$admin['id'], $admin['full_name'], 'order_correction', 'order', $orderId, [
                        'from'   => $oldStatus,
                        'to'     => $newStatus,
                        'reason' => $reason,
                    ]);
                });
                Session::flash('success', "Order #{$orderId} updated with audit log.");
            }
            Response::redirect('/admin/orders');
        }

        $date = Validator::str(Request::get('date'), 20) ?: ScheduleService::today();
        $type = Validator::enum(Request::get('type'), ['all', 'regular', 'on_demand', 'guest'], 'all');
        $status = Validator::enum(Request::get('status'), ['all', 'pending', 'accepted', 'preparing', 'almost_done', 'delivered', 'cancelled', 'system_cancelled'], 'all');
        $room = Validator::str(Request::get('room'), 120);
        $dept = Validator::str(Request::get('dept'), 120);

        $where = ['o.order_date = ?'];
        $params = [$date];
        if ($type !== 'all') {
            $where[] = 'o.order_type = ?';
            $params[] = $type;
        }
        if ($status !== 'all') {
            $where[] = 'o.status = ?';
            $params[] = $status;
        }
        if ($room !== '') {
            $where[] = 'o.room_name_snapshot = ?';
            $params[] = $room;
        }
        if ($dept !== '') {
            $where[] = 'o.department_name_snapshot = ?';
            $params[] = $dept;
        }

        $totalOrders = (int)(Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM orders o WHERE ' . implode(' AND ', $where),
            $params
        )['cnt'] ?? 0);
        $ordersPg = Paginator::paginate($totalOrders, 'page', 'per_page');
        $perPage = $ordersPg['per_page'];
        $offset = $ordersPg['offset'];

        $orders = Database::fetchAll(
            'SELECT o.* FROM orders o WHERE ' . implode(' AND ', $where) . " ORDER BY o.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $orders = OrderService::getOrdersWithItems($orders);

        // Fetch order events for display
        if (!empty($orders)) {
            $ids = array_map(fn($o) => (int)$o['id'], $orders);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $events = Database::fetchAll("SELECT * FROM order_events WHERE order_id IN ({$ph}) ORDER BY created_at ASC", $ids);
            $evMap = [];
            foreach ($events as $ev) {
                $evMap[(int)$ev['order_id']][] = $ev;
            }
            foreach ($orders as &$o) {
                $o['events'] = $evMap[(int)$o['id']] ?? [];
            }
        }

        $rooms = Database::fetchAll('SELECT name FROM rooms ORDER BY name ASC');
        $depts = Database::fetchAll('SELECT name FROM departments ORDER BY name ASC');

        Response::html(View::render('admin/orders', [
            'user'        => $admin,
            'orders'      => $orders,
            'date'        => $date,
            'type'        => $type,
            'status'      => $status,
            'room'        => $room,
            'dept'        => $dept,
            'rooms'       => $rooms,
            'depts'       => $depts,
            'ordersPg'    => $ordersPg,
            'activeNav'   => 'orders',
        ], 'admin'));
    }

    public function reports(): void
    {
        $admin = Auth::requireRole(['admin']);
        $today = ScheduleService::today();
        $from = Validator::str(Request::get('from'), 20) ?: date('Y-m-01', strtotime($today));
        $to = Validator::str(Request::get('to'), 20) ?: $today;

        if (Request::get('export') === 'csv') {
            $rows = Database::fetchAll(
                "SELECT o.id, o.order_date, o.order_type, o.slot_key, o.employee_id_snapshot,
                        o.employee_name_snapshot, o.department_name_snapshot, o.room_name_snapshot,
                        o.status, oi.beverage, oi.sugar, oi.quantity, o.note, o.created_at, o.delivered_at
                 FROM orders o
                 INNER JOIN order_items oi ON oi.order_id = o.id
                 WHERE o.order_date BETWEEN ? AND ?
                 ORDER BY o.order_date DESC, o.id DESC",
                [$from, $to]
            );
            $csvRows = array_map(fn($r) => array_values($r), $rows);
            Response::csv(
                "teacare-report-{$from}-to-{$to}.csv",
                ['Order ID', 'Date', 'Type', 'Session', 'Emp ID', 'Employee', 'Department', 'Room', 'Status', 'Beverage', 'Sugar', 'Qty', 'Note', 'Created', 'Delivered'],
                $csvRows
            );
        }

        $dailyTotal = (int)(Database::fetchOne(
            "SELECT COUNT(DISTINCT o.order_date) AS cnt
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date BETWEEN ? AND ?",
            [$from, $to]
        )['cnt'] ?? 0);
        $dailyPg = Paginator::paginate($dailyTotal, 'daily_page', 'daily_per_page');

        $dailyRows = Database::fetchAll(
            "SELECT o.order_date,
                    COALESCE(SUM(CASE WHEN oi.beverage='tea' AND o.status='delivered' THEN oi.quantity ELSE 0 END), 0) AS tea_delivered,
                    COALESCE(SUM(CASE WHEN oi.beverage='coffee' AND o.status='delivered' THEN oi.quantity ELSE 0 END), 0) AS coffee_delivered,
                    COALESCE(SUM(CASE WHEN o.status IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS cancelled_cups,
                    COALESCE(SUM(CASE WHEN o.status NOT IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS requested_cups
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date BETWEEN ? AND ?
             GROUP BY o.order_date
             ORDER BY o.order_date DESC
             LIMIT {$dailyPg['per_page']} OFFSET {$dailyPg['offset']}",
            [$from, $to]
        );

        $deptTotal = (int)(Database::fetchOne(
            "SELECT COUNT(DISTINCT COALESCE(NULLIF(o.department_name_snapshot, ''), 'Unassigned')) AS cnt
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date BETWEEN ? AND ?",
            [$from, $to]
        )['cnt'] ?? 0);
        $deptPg = Paginator::paginate($deptTotal, 'dept_page', 'dept_per_page');

        $deptRows = Database::fetchAll(
            "SELECT COALESCE(NULLIF(o.department_name_snapshot, ''), 'Unassigned') AS department_name,
                    COALESCE(SUM(CASE WHEN oi.beverage='tea' AND o.status='delivered' THEN oi.quantity ELSE 0 END), 0) AS tea_cups,
                    COALESCE(SUM(CASE WHEN oi.beverage='coffee' AND o.status='delivered' THEN oi.quantity ELSE 0 END), 0) AS coffee_cups,
                    COALESCE(SUM(CASE WHEN o.status='delivered' THEN oi.quantity ELSE 0 END), 0) AS delivered_cups,
                    COALESCE(SUM(CASE WHEN o.status IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS cancelled_cups
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date BETWEEN ? AND ?
             GROUP BY department_name
             ORDER BY delivered_cups DESC
             LIMIT {$deptPg['per_page']} OFFSET {$deptPg['offset']}",
            [$from, $to]
        );

        $totals = Database::fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN o.status='delivered' THEN oi.quantity ELSE 0 END), 0) AS delivered,
                COALESCE(SUM(CASE WHEN o.status IN ('cancelled', 'system_cancelled') THEN oi.quantity ELSE 0 END), 0) AS cancelled,
                COALESCE(SUM(CASE WHEN oi.beverage='tea' AND o.status='delivered' THEN oi.quantity ELSE 0 END), 0) AS tea,
                COALESCE(SUM(CASE WHEN oi.beverage='coffee' AND o.status='delivered' THEN oi.quantity ELSE 0 END), 0) AS coffee
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.order_date BETWEEN ? AND ?",
            [$from, $to]
        );

        Response::html(View::render('admin/reports', [
            'user'      => $admin,
            'from'      => $from,
            'to'        => $to,
            'dailyRows' => $dailyRows,
            'dailyPg'   => $dailyPg,
            'deptRows'  => $deptRows,
            'deptPg'    => $deptPg,
            'totals'    => $totals,
            'activeNav' => 'reports',
        ], 'admin'));
    }

    public function schedule(): void
    {
        $admin = Auth::requireRole(['admin']);

        if (Request::method() === 'POST') {
            $action = Request::post('action', 'update_slots');

            if ($action === 'update_slots') {
                foreach (['morning', 'afternoon'] as $key) {
                    $serviceTime = Validator::str(Request::post("{$key}_service_time"), 10);
                    $cutoffTime = Validator::str(Request::post("{$key}_cutoff_time"), 10);
                    $isActive = Request::post("{$key}_is_active") === '1' ? 1 : 0;

                    if (strlen($serviceTime) === 5) {
                        $serviceTime .= ':00';
                    }
                    if (strlen($cutoffTime) === 5) {
                        $cutoffTime .= ':00';
                    }

                    Database::execute(
                        'UPDATE service_slots SET service_time = ?, cutoff_time = ?, is_active = ? WHERE slot_key = ?',
                        [$serviceTime, $cutoffTime, $isActive, $key]
                    );
                }

                $days = $_POST['working_days'] ?? [];
                $allowedDays = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
                $cleanDays = array_values(array_intersect($allowedDays, is_array($days) ? $days : []));
                Database::execute(
                    "INSERT INTO settings (setting_key, setting_value) VALUES ('working_days', ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [json_encode($cleanDays)]
                );

                AuditService::log((int)$admin['id'], $admin['full_name'], 'update_schedule', 'service_slots', null, ['working_days' => $cleanDays]);
                Session::flash('success', 'Service schedule and working days updated.');
            } elseif ($action === 'add_holiday') {
                $date = Validator::str(Request::post('holiday_date'), 20);
                $title = Validator::str(Request::post('title'), 150);
                if ($date !== '' && $title !== '') {
                    Database::execute(
                        'INSERT INTO holidays (holiday_date, title, is_closed) VALUES (?, ?, 1)
                         ON DUPLICATE KEY UPDATE title = VALUES(title), is_closed = 1',
                        [$date, $title]
                    );
                    AuditService::log((int)$admin['id'], $admin['full_name'], 'add_holiday', 'holiday', null, ['date' => $date, 'title' => $title]);
                    Session::flash('success', "Holiday added for {$date}.");
                }
            } elseif ($action === 'delete_holiday') {
                $id = (int)Request::post('holiday_id', 0);
                Database::execute('DELETE FROM holidays WHERE id = ?', [$id]);
                AuditService::log((int)$admin['id'], $admin['full_name'], 'delete_holiday', 'holiday', $id);
                Session::flash('success', 'Holiday removed.');
            } elseif ($action === 'run_finalizer') {
                $today = ScheduleService::today();
                $m = OrderService::finalizeRegularSlot($today, 'morning');
                $a = OrderService::finalizeRegularSlot($today, 'afternoon');
                Session::flash('success', "Cutoff finalizer ran: {$m} morning and {$a} afternoon order(s) generated.");
            }

            Response::redirect('/admin/schedule');
        }

        $slots = ScheduleService::getSlots();
        $workingDays = ScheduleService::getWorkingDays();
        $holidays = Database::fetchAll('SELECT * FROM holidays ORDER BY holiday_date DESC LIMIT 50');

        Response::html(View::render('admin/schedule', [
            'user'        => $admin,
            'slots'       => $slots,
            'workingDays' => $workingDays,
            'holidays'    => $holidays,
            'activeNav'   => 'schedule',
        ], 'admin'));
    }

    public function notifications(): void
    {
        $admin = Auth::requireRole(['admin']);

        if (Request::method() === 'POST') {
            $action = Request::post('action', '');
            if ($action === 'retry_failed') {
                Database::execute("UPDATE notification_jobs SET status = 'pending', attempts = 0, next_retry_at = NULL WHERE status = 'failed'");
                PushService::processPendingJobs(20);
                Session::flash('success', 'Failed push jobs re-queued.');
            } elseif ($action === 'retry_failed_wa') {
                Database::execute("UPDATE whatsapp_jobs SET status = 'pending', attempts = 0, next_retry_at = NULL WHERE status = 'failed'");
                WhatsAppService::processPendingJobs(20);
                Session::flash('success', 'Failed WhatsApp jobs re-queued.');
            }
            Response::redirect('/admin/notifications');
        }

        $subTotal = (int)(Database::fetchOne(
            'SELECT COUNT(*) AS cnt FROM push_subscriptions ps INNER JOIN users u ON u.id = ps.user_id'
        )['cnt'] ?? 0);
        $subPg = Paginator::paginate($subTotal, 'sub_page', 'sub_per_page');
        $subscriptions = Database::fetchAll(
            "SELECT ps.*, u.full_name, u.employee_id, u.role
             FROM push_subscriptions ps
             INNER JOIN users u ON u.id = ps.user_id
             ORDER BY ps.is_active DESC, ps.updated_at DESC
             LIMIT {$subPg['per_page']} OFFSET {$subPg['offset']}"
        );

        $logTotal = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM notification_logs')['cnt'] ?? 0);
        $logPg = Paginator::paginate($logTotal, 'log_page', 'log_per_page');
        $logs = Database::fetchAll(
            "SELECT nl.*, u.full_name
             FROM notification_logs nl
             LEFT JOIN users u ON u.id = nl.user_id
             ORDER BY nl.created_at DESC
             LIMIT {$logPg['per_page']} OFFSET {$logPg['offset']}"
        );

        $jobTotal = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM notification_jobs')['cnt'] ?? 0);
        $jobPg = Paginator::paginate($jobTotal, 'job_page', 'job_per_page');
        $jobs = Database::fetchAll(
            "SELECT * FROM notification_jobs ORDER BY created_at DESC LIMIT {$jobPg['per_page']} OFFSET {$jobPg['offset']}"
        );

        $waTotal = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM whatsapp_jobs')['cnt'] ?? 0);
        $waPg = Paginator::paginate($waTotal, 'wa_page', 'wa_per_page');
        $waJobs = Database::fetchAll(
            "SELECT * FROM whatsapp_jobs ORDER BY created_at DESC LIMIT {$waPg['per_page']} OFFSET {$waPg['offset']}"
        );

        Response::html(View::render('admin/notifications', [
            'user'          => $admin,
            'subscriptions' => $subscriptions,
            'subPg'         => $subPg,
            'logs'          => $logs,
            'logPg'         => $logPg,
            'jobs'          => $jobs,
            'jobPg'         => $jobPg,
            'waJobs'        => $waJobs,
            'waPg'          => $waPg,
            'activeNav'     => 'notifications',
        ], 'admin'));
    }

    public function settings(): void
    {
        $admin = Auth::requireRole(['admin']);

        if (Request::method() === 'POST') {
            $officeName = Validator::str(Request::post('office_name'), 120) ?: 'TeaCare Office';
            $timezone = Validator::str(Request::post('timezone'), 60) ?: 'Asia/Dhaka';
            if (!in_array($timezone, timezone_identifiers_list(), true)) {
                $timezone = 'Asia/Dhaka';
            }

            $evoUrl = rtrim(Validator::str(Request::post('evo_base_url'), 250), '/');
            $evoInstance = Validator::str(Request::post('evo_instance'), 120);
            $evoGroup = Validator::str(Request::post('evo_group_jid'), 120);
            $evoKeyInput = trim((string)Request::post('evo_api_key', ''));

            $updates = [
                'office_name'                  => $officeName,
                'timezone'                     => $timezone,
                'allow_self_registration'      => Request::post('allow_self_registration') === '1' ? '1' : '0',
                'require_admin_approval'       => Request::post('require_admin_approval') === '1' ? '1' : '0',
                'allow_employee_dept_room_edit'=> Request::post('allow_employee_dept_room_edit') === '1' ? '1' : '0',
                'push_enabled'                 => Request::post('push_enabled') === '1' ? '1' : '0',
                'guest_ordering_enabled'       => Request::post('guest_ordering_enabled') === '1' ? '1' : '0',
                'evo_enabled'                  => Request::post('evo_enabled') === '1' ? '1' : '0',
                'evo_base_url'                 => $evoUrl,
                'evo_instance'                 => $evoInstance,
                'evo_group_jid'                => $evoGroup,
            ];
            if ($evoKeyInput !== '') {
                $updates['evo_api_key'] = $evoKeyInput;
            } elseif (Request::post('clear_evo_key') === '1') {
                $updates['evo_api_key'] = '';
            }

            if (!empty($_FILES['office_logo']) && ($_FILES['office_logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                try {
                    $old = setting('office_logo', '');
                    $newLogo = ImageService::saveLogo($_FILES['office_logo']);
                    if ($newLogo) {
                        $updates['office_logo'] = $newLogo;
                        ImageService::deleteStoredFile($old);
                    }
                } catch (\Throwable $e) {
                    Session::flash('warning', 'Settings saved, logo failed: ' . $e->getMessage());
                }
            }

            foreach ($updates as $k => $v) {
                Database::execute(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                    [$k, $v]
                );
            }

            $auditCopy = $updates;
            if (isset($auditCopy['evo_api_key']) && $auditCopy['evo_api_key'] !== '') {
                $auditCopy['evo_api_key'] = '***REDACTED***';
            }
            AuditService::log((int)$admin['id'], $admin['full_name'], 'update_settings', 'settings', null, $auditCopy);
            Session::flash('success', 'System & Evolution API settings saved.');
            Response::redirect('/admin/settings');
        }

        $auditTotal = (int)(Database::fetchOne('SELECT COUNT(*) AS cnt FROM audit_logs')['cnt'] ?? 0);
        $auditPg = Paginator::paginate($auditTotal, 'page', 'per_page');
        $auditLogs = Database::fetchAll(
            "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT {$auditPg['per_page']} OFFSET {$auditPg['offset']}"
        );
        $vapid = PushService::getVapidConfig();
        $evo = WhatsAppService::getConfig();
        $schedulerLastRun = setting('scheduler_last_run_at', 'Never');

        Response::html(View::render('admin/settings', [
            'user'             => $admin,
            'auditLogs'        => $auditLogs,
            'auditPg'          => $auditPg,
            'vapid'            => $vapid,
            'evo'              => $evo,
            'schedulerLastRun' => $schedulerLastRun,
            'activeNav'        => 'settings',
        ], 'admin'));
    }

    public function testWhatsapp(): void
    {
        $admin = Auth::requireRole(['admin']);
        $cfg = WhatsAppService::getConfig();
        $msg = "*TeaCare Test Alert*\nEvolution API connection verified from " . setting('office_name', 'TeaCare Office') . " by {$admin['full_name']}.";
        $res = WhatsAppService::sendText($msg, $cfg);
        if ($res['ok']) {
            Session::flash('success', 'Test WhatsApp message sent to Staff Group (HTTP ' . $res['status'] . ').');
        } else {
            Session::flash('error', 'Evolution API test failed: ' . ($res['error'] ?? 'Unknown error'));
        }
        Response::redirect('/admin/settings');
    }
}
