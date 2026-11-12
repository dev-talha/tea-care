<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDOException;

class OrderService
{
    public static function seedUserDefaultPreferences(int $userId): void
    {
        $stmt = Database::pdo()->prepare(
            "INSERT IGNORE INTO default_preferences (user_id, slot_key, enabled, beverage, sugar, quantity, note, updated_at)
             VALUES (?, ?, 1, 'tea', 'normal', 1, '', NOW())"
        );
        $stmt->execute([$userId, 'morning']);
        $stmt->execute([$userId, 'afternoon']);
    }

    public static function getDefaultPreferences(int $userId, string $role = 'employee'): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM default_preferences WHERE user_id = ?',
            [$userId]
        );
        $defaultEnabled = $role === 'employee' ? 1 : 0;
        $prefs = [
            'morning'   => ['enabled' => $defaultEnabled, 'beverage' => 'tea', 'sugar' => 'normal', 'quantity' => 1, 'note' => ''],
            'afternoon' => ['enabled' => $defaultEnabled, 'beverage' => 'tea', 'sugar' => 'normal', 'quantity' => 1, 'note' => ''],
        ];
        foreach ($rows as $row) {
            $prefs[$row['slot_key']] = $row;
        }
        return $prefs;
    }

    public static function saveDefaultPreference(
        int $userId,
        string $slotKey,
        bool $enabled,
        string $beverage,
        string $sugar,
        int $quantity,
        string $note
    ): void {
        $existing = Database::fetchOne(
            'SELECT id FROM default_preferences WHERE user_id = ? AND slot_key = ?',
            [$userId, $slotKey]
        );

        if ($existing) {
            Database::execute(
                'UPDATE default_preferences
                 SET enabled = ?, beverage = ?, sugar = ?, quantity = ?, note = ?, updated_at = NOW()
                 WHERE id = ?',
                [$enabled ? 1 : 0, $beverage, $sugar, $quantity, $note, $existing['id']]
            );
        } else {
            Database::insert(
                'INSERT INTO default_preferences (user_id, slot_key, enabled, beverage, sugar, quantity, note, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [$userId, $slotKey, $enabled ? 1 : 0, $beverage, $sugar, $quantity, $note]
            );
        }
    }

    public static function getTodaySlotState(array $user, string $slotKey): array
    {
        $today = ScheduleService::today();
        $slot = ScheduleService::getSlot($slotKey);
        $dayStatus = ScheduleService::getDayStatus($today);
        $cutoffPassed = $slot ? ScheduleService::isCutoffPassed($slot) : true;

        $uniqueKey = "{$today}:{$slotKey}:{$user['id']}";
        $finalizedOrder = Database::fetchOne(
            'SELECT o.*, oi.beverage, oi.sugar, oi.quantity, oi.note AS item_note
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             WHERE o.regular_unique_key = ?
             LIMIT 1',
            [$uniqueKey]
        );

        $override = Database::fetchOne(
            'SELECT * FROM daily_overrides WHERE user_id = ? AND service_date = ? AND slot_key = ?',
            [$user['id'], $today, $slotKey]
        );

        $defaultPref = Database::fetchOne(
            'SELECT * FROM default_preferences WHERE user_id = ? AND slot_key = ?',
            [$user['id'], $slotKey]
        );

        // Employees automatically get 2-session Tea by default unless explicitly disabled
        if ($defaultPref === null && ($user['role'] ?? 'employee') === 'employee') {
            $defaultPref = [
                'enabled'  => 1,
                'beverage' => 'tea',
                'sugar'    => 'normal',
                'quantity' => 1,
                'note'     => '',
            ];
        }

        $isPaused = (int)($user['is_paused'] ?? 0) === 1;
        $isLocked = $cutoffPassed || $finalizedOrder !== null || !$dayStatus['is_open'] || !($slot && (int)$slot['is_active'] === 1);

        $state = 'not_configured';
        $effective = null;

        if ($finalizedOrder) {
            $state = 'finalized';
            $effective = [
                'beverage' => $finalizedOrder['beverage'] ?? 'tea',
                'sugar'    => $finalizedOrder['sugar'] ?? 'normal',
                'quantity' => (int)($finalizedOrder['quantity'] ?? 1),
                'note'     => $finalizedOrder['note'] ?? '',
            ];
        } elseif (!$dayStatus['is_open']) {
            $state = $dayStatus['reason'] === 'holiday' ? 'holiday' : 'non_working';
        } elseif ($isPaused) {
            $state = 'paused';
        } elseif ($override) {
            if ($override['override_type'] === 'skip') {
                $state = 'skipped';
            } else {
                $state = 'override';
                $effective = [
                    'beverage' => $override['beverage'],
                    'sugar'    => $override['sugar'],
                    'quantity' => (int)$override['quantity'],
                    'note'     => $override['note'],
                ];
            }
        } elseif ($defaultPref && (int)$defaultPref['enabled'] === 1) {
            $state = 'default';
            $effective = [
                'beverage' => $defaultPref['beverage'],
                'sugar'    => $defaultPref['sugar'],
                'quantity' => (int)$defaultPref['quantity'],
                'note'     => $defaultPref['note'],
            ];
        }

        return [
            'slot'            => $slot,
            'today'           => $today,
            'day_status'      => $dayStatus,
            'cutoff_passed'   => $cutoffPassed,
            'is_locked'       => $isLocked,
            'is_paused'       => $isPaused,
            'state'           => $state,
            'effective'       => $effective,
            'override'        => $override,
            'default'         => $defaultPref,
            'finalized_order' => $finalizedOrder,
        ];
    }

    public static function saveTodayOverride(
        array $user,
        string $slotKey,
        string $beverage,
        string $sugar,
        int $quantity,
        string $note
    ): void {
        $today = ScheduleService::today();
        $slot = ScheduleService::getSlot($slotKey);
        if (!$slot || (int)$slot['is_active'] !== 1) {
            throw new \RuntimeException('This service session is inactive.');
        }
        $dayStatus = ScheduleService::getDayStatus($today);
        if (!$dayStatus['is_open']) {
            throw new \RuntimeException('Office is closed today.');
        }
        if (ScheduleService::isCutoffPassed($slot)) {
            throw new \RuntimeException('Cutoff time has passed. Please place an On-Demand order instead.');
        }

        $uniqueKey = "{$today}:{$slotKey}:{$user['id']}";
        $finalized = Database::fetchOne('SELECT id FROM orders WHERE regular_unique_key = ?', [$uniqueKey]);
        if ($finalized) {
            throw new \RuntimeException('This session is already finalized. Please place an On-Demand order instead.');
        }

        $existing = Database::fetchOne(
            'SELECT id FROM daily_overrides WHERE user_id = ? AND service_date = ? AND slot_key = ?',
            [$user['id'], $today, $slotKey]
        );

        if ($existing) {
            Database::execute(
                "UPDATE daily_overrides
                 SET override_type = 'custom', beverage = ?, sugar = ?, quantity = ?, note = ?, updated_at = NOW()
                 WHERE id = ?",
                [$beverage, $sugar, $quantity, $note, $existing['id']]
            );
        } else {
            Database::insert(
                "INSERT INTO daily_overrides (user_id, service_date, slot_key, override_type, beverage, sugar, quantity, note, created_at, updated_at)
                 VALUES (?, ?, ?, 'custom', ?, ?, ?, ?, NOW(), NOW())",
                [$user['id'], $today, $slotKey, $beverage, $sugar, $quantity, $note]
            );
        }
    }

    public static function skipTodaySlot(array $user, string $slotKey): void
    {
        $today = ScheduleService::today();
        $slot = ScheduleService::getSlot($slotKey);
        if (!$slot) {
            throw new \RuntimeException('Invalid service session.');
        }
        if (ScheduleService::isCutoffPassed($slot)) {
            throw new \RuntimeException('Cutoff time has passed; this session cannot be skipped.');
        }
        $uniqueKey = "{$today}:{$slotKey}:{$user['id']}";
        $finalized = Database::fetchOne('SELECT id FROM orders WHERE regular_unique_key = ?', [$uniqueKey]);
        if ($finalized) {
            throw new \RuntimeException('Order already finalized for this session.');
        }

        $existing = Database::fetchOne(
            'SELECT id FROM daily_overrides WHERE user_id = ? AND service_date = ? AND slot_key = ?',
            [$user['id'], $today, $slotKey]
        );

        if ($existing) {
            Database::execute(
                "UPDATE daily_overrides
                 SET override_type = 'skip', beverage = NULL, sugar = NULL, quantity = 1, note = '', updated_at = NOW()
                 WHERE id = ?",
                [$existing['id']]
            );
        } else {
            Database::insert(
                "INSERT INTO daily_overrides (user_id, service_date, slot_key, override_type, beverage, sugar, quantity, note, created_at, updated_at)
                 VALUES (?, ?, ?, 'skip', NULL, NULL, 1, '', NOW(), NOW())",
                [$user['id'], $today, $slotKey]
            );
        }
    }

    public static function resetTodayOverride(array $user, string $slotKey): void
    {
        $today = ScheduleService::today();
        $slot = ScheduleService::getSlot($slotKey);
        if (!$slot || ScheduleService::isCutoffPassed($slot)) {
            throw new \RuntimeException('Cutoff time has passed; cannot reset today override.');
        }
        $uniqueKey = "{$today}:{$slotKey}:{$user['id']}";
        $finalized = Database::fetchOne('SELECT id FROM orders WHERE regular_unique_key = ?', [$uniqueKey]);
        if ($finalized) {
            throw new \RuntimeException('Order already finalized for this session.');
        }

        Database::execute(
            'DELETE FROM daily_overrides WHERE user_id = ? AND service_date = ? AND slot_key = ?',
            [$user['id'], $today, $slotKey]
        );
    }

    public static function finalizeRegularSlot(string $date, string $slotKey, ?DateTimeImmutable $now = null): int
    {
        $skipRow = Database::fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'skip_finalize_slots'");
        $skipSlots = array_filter(array_map('trim', explode(',', (string)($skipRow['setting_value'] ?? ''))));
        if (in_array("{$date}:{$slotKey}", $skipSlots, true)) {
            return 0;
        }

        $now ??= ScheduleService::now();
        $slot = ScheduleService::getSlot($slotKey);
        if (!$slot || (int)$slot['is_active'] !== 1) {
            return 0;
        }

        $dayStatus = ScheduleService::getDayStatus($date);
        if (!$dayStatus['is_open']) {
            return 0;
        }

        if ($date === $now->format('Y-m-d') && !ScheduleService::isCutoffPassed($slot, $now)) {
            return 0;
        }

        $candidates = Database::fetchAll(
            "SELECT u.id AS user_id, u.employee_id, u.full_name, u.role, u.room_id, u.cup_image,
                    d.name AS department_name, r.name AS room_name,
                    ov.override_type, ov.beverage AS ov_beverage, ov.sugar AS ov_sugar, ov.quantity AS ov_quantity, ov.note AS ov_note,
                    dp.id AS dp_id, dp.enabled AS dp_enabled, dp.beverage AS dp_beverage, dp.sugar AS dp_sugar, dp.quantity AS dp_quantity, dp.note AS dp_note
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN rooms r ON r.id = u.room_id
             LEFT JOIN daily_overrides ov ON ov.user_id = u.id AND ov.service_date = ? AND ov.slot_key = ?
             LEFT JOIN default_preferences dp ON dp.user_id = u.id AND dp.slot_key = ?
             WHERE u.status = 'active' AND u.is_paused = 0 AND u.role IN ('employee', 'admin')",
            [$date, $slotKey, $slotKey]
        );

        $existingRows = Database::fetchAll(
            "SELECT regular_unique_key FROM orders WHERE order_date = ? AND order_type = 'regular' AND slot_key = ?",
            [$date, $slotKey]
        );
        $existingKeys = array_flip(array_column($existingRows, 'regular_unique_key'));

        $createdCount = 0;
        $teaTotal = 0;
        $coffeeTotal = 0;

        foreach ($candidates as $c) {
            if ($c['override_type'] === 'skip') {
                continue;
            }

            $beverage = null;
            $sugar = 'normal';
            $quantity = 1;
            $note = '';

            if ($c['override_type'] === 'custom' && !empty($c['ov_beverage'])) {
                $beverage = $c['ov_beverage'];
                $sugar = $c['ov_sugar'] ?: 'normal';
                $quantity = max(1, (int)$c['ov_quantity']);
                $note = (string)$c['ov_note'];
            } elseif ($c['dp_id'] !== null) {
                if ((int)$c['dp_enabled'] !== 1 || empty($c['dp_beverage'])) {
                    continue;
                }
                $beverage = $c['dp_beverage'];
                $sugar = $c['dp_sugar'] ?: 'normal';
                $quantity = max(1, (int)$c['dp_quantity']);
                $note = (string)$c['dp_note'];
            } elseif ($c['role'] === 'employee') {
                // Default automatic 2-session Tea for employees without explicit row
                $beverage = 'tea';
                $sugar = 'normal';
                $quantity = 1;
                $note = '';
            } else {
                continue;
            }

            $regularUniqueKey = "{$date}:{$slotKey}:{$c['user_id']}";
            if (isset($existingKeys[$regularUniqueKey])) {
                continue;
            }

            try {
                $inserted = Database::transaction(function () use ($date, $slotKey, $c, $beverage, $sugar, $quantity, $note, $regularUniqueKey): bool {
                    $orderId = (int)Database::insert(
                        "INSERT INTO orders (
                            order_date, order_type, slot_key, user_id,
                            employee_name_snapshot, employee_id_snapshot, department_name_snapshot,
                            room_id, room_name_snapshot, cup_image_snapshot,
                            note, status, regular_unique_key, created_at, updated_at
                         ) VALUES (?, 'regular', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())",
                        [
                            $date,
                            $slotKey,
                            $c['user_id'],
                            $c['full_name'],
                            $c['employee_id'],
                            $c['department_name'] ?? '',
                            $c['room_id'],
                            $c['room_name'] ?? 'Unassigned',
                            $c['cup_image'],
                            $note,
                            $regularUniqueKey,
                        ]
                    );

                    Database::insert(
                        'INSERT INTO order_items (order_id, beverage, sugar, quantity, note) VALUES (?, ?, ?, ?, ?)',
                        [$orderId, $beverage, $sugar, $quantity, $note]
                    );

                    Database::insert(
                        "INSERT INTO order_events (order_id, actor_id, actor_name, from_status, to_status, note, created_at)
                         VALUES (?, NULL, 'Scheduler', NULL, 'pending', 'Finalized at cutoff', NOW())",
                        [$orderId]
                    );

                    return true;
                });

                if ($inserted) {
                    $createdCount++;
                    if ($beverage === 'coffee') {
                        $coffeeTotal += $quantity;
                    } else {
                        $teaTotal += $quantity;
                    }
                }
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
            }
        }

        if ($createdCount > 0) {
            $slotLabel = $slot['label_en'] ?? ucfirst($slotKey);
            $summaryTitle = "{$slotLabel} Ready ({$createdCount} orders)";
            $summaryBody = "Total Tea: {$teaTotal} cups, Coffee: {$coffeeTotal} cups.";
            $jobKey = "regular_summary:{$date}:{$slotKey}";
            PushService::createStaffInAppNotification(null, $summaryTitle, $summaryBody, '/staff?tab=' . $slotKey);
            PushService::queueStaffPush(null, $summaryTitle, $summaryBody, '/staff?tab=' . $slotKey, $jobKey);
        }

        return $createdCount;
    }

    public static function createOnDemandOrder(
        array $user,
        string $beverage,
        string $sugar,
        int $quantity,
        ?int $roomId,
        string $note,
        string $idempotencyKey
    ): array {
        $existing = Database::fetchOne(
            'SELECT * FROM orders WHERE idempotency_key = ? AND user_id = ?',
            [$idempotencyKey, $user['id']]
        );
        if ($existing) {
            return $existing;
        }

        $today = ScheduleService::today();
        $room = $roomId ? Database::fetchOne('SELECT id, name FROM rooms WHERE id = ?', [$roomId]) : null;
        $roomName = $room ? $room['name'] : ($user['room_name'] ?? 'Unassigned');
        $effectiveRoomId = $room ? (int)$room['id'] : ($user['room_id'] ? (int)$user['room_id'] : null);

        $orderId = Database::transaction(function () use ($today, $user, $effectiveRoomId, $roomName, $beverage, $sugar, $quantity, $note, $idempotencyKey): int {
            $id = (int)Database::insert(
                "INSERT INTO orders (
                    order_date, order_type, slot_key, user_id,
                    employee_name_snapshot, employee_id_snapshot, department_name_snapshot,
                    room_id, room_name_snapshot, cup_image_snapshot,
                    note, status, idempotency_key, created_at, updated_at
                 ) VALUES (?, 'on_demand', NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())",
                [
                    $today,
                    $user['id'],
                    $user['full_name'],
                    $user['employee_id'],
                    $user['department_name'] ?? '',
                    $effectiveRoomId,
                    $roomName,
                    $user['cup_image'],
                    $note,
                    $idempotencyKey,
                ]
            );

            Database::insert(
                'INSERT INTO order_items (order_id, beverage, sugar, quantity, note) VALUES (?, ?, ?, ?, ?)',
                [$id, $beverage, $sugar, $quantity, $note]
            );

            Database::insert(
                "INSERT INTO order_events (order_id, actor_id, actor_name, from_status, to_status, note, created_at)
                 VALUES (?, ?, ?, NULL, 'pending', 'On-demand request submitted', NOW())",
                [$id, $user['id'], $user['full_name']]
            );

            $bevLabel = $beverage === 'coffee' ? 'Coffee' : 'Tea';
            $sugarLabel = badge_sugar($sugar);
            $deptPart = !empty($user['department_name']) ? ", {$user['department_name']}" : '';
            $notePart = $note !== '' ? ". {$note}" : '';

            $title = "New {$bevLabel} Request — {$user['full_name']}";
            $body = "{$user['full_name']}{$deptPart}, {$roomName}. {$quantity} {$bevLabel}, {$sugarLabel}{$notePart}";

            PushService::createStaffInAppNotification($id, $title, $body, '/staff?tab=urgent&highlight=' . $id);
            PushService::queueStaffPush($id, $title, $body, '/staff?tab=urgent&highlight=' . $id, 'order_ondemand_' . $id);

            $waLines = [
                "*TeaCare Urgent Request (#{$id})*",
                "Employee: {$user['full_name']}" . (!empty($user['department_name']) ? " ({$user['department_name']})" : ''),
                "Room: {$roomName}",
                "Order: {$quantity}x {$bevLabel} ({$sugarLabel})",
            ];
            if ($note !== '') {
                $waLines[] = "Note: {$note}";
            }
            WhatsAppService::queueGroupMessage($id, implode("\n", $waLines), 'wa_ondemand_' . $id);

            return $id;
        });

        return Database::fetchOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
    }

    public static function createGuestOrder(
        array $user,
        int $roomId,
        string $meetingTitle,
        ?int $guestCount,
        array $items,
        string $note,
        string $idempotencyKey,
        ?string $requestedWithin = 'Immediate'
    ): array {
        $existing = Database::fetchOne(
            'SELECT * FROM orders WHERE idempotency_key = ? AND user_id = ?',
            [$idempotencyKey, $user['id']]
        );
        if ($existing) {
            return $existing;
        }

        if (empty($items)) {
            throw new \RuntimeException('Please add at least one beverage line.');
        }

        $neededWithin = null;
        if ($requestedWithin !== null) {
            $rw = trim($requestedWithin);
            if ($rw === 'Immediate' || preg_match('/^(After|Before|Within) ([1-9]\d{0,2}) (min|hour)$/', $rw)) {
                $neededWithin = $rw;
            }
        }

        $today = ScheduleService::today();
        $room = Database::fetchOne('SELECT id, name FROM rooms WHERE id = ?', [$roomId]);
        if (!$room) {
            throw new \RuntimeException('Please select a valid meeting room.');
        }

        $orderId = Database::transaction(function () use ($today, $user, $room, $meetingTitle, $guestCount, $neededWithin, $items, $note, $idempotencyKey): int {
            $id = (int)Database::insert(
                "INSERT INTO orders (
                    order_date, order_type, slot_key, user_id,
                    employee_name_snapshot, employee_id_snapshot, department_name_snapshot,
                    room_id, room_name_snapshot, cup_image_snapshot,
                    meeting_title, guest_count, requested_within, note, status, idempotency_key, created_at, updated_at
                 ) VALUES (?, 'guest', NULL, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())",
                [
                    $today,
                    $user['id'],
                    $user['full_name'],
                    $user['employee_id'],
                    $user['department_name'] ?? '',
                    $room['id'],
                    $room['name'],
                    $meetingTitle !== '' ? $meetingTitle : null,
                    $guestCount,
                    $neededWithin,
                    $note,
                    $idempotencyKey,
                ]
            );

            $teaTotal = 0;
            $coffeeTotal = 0;
            foreach ($items as $item) {
                $bev = ($item['beverage'] ?? 'tea') === 'coffee' ? 'coffee' : 'tea';
                $sug = in_array($item['sugar'] ?? 'normal', ['normal', 'less', 'extra', 'none'], true) ? $item['sugar'] : 'normal';
                $qty = max(1, min(100, (int)($item['quantity'] ?? 1)));

                Database::insert(
                    'INSERT INTO order_items (order_id, beverage, sugar, quantity, note) VALUES (?, ?, ?, ?, ?)',
                    [$id, $bev, $sug, $qty, '']
                );
                if ($bev === 'coffee') {
                    $coffeeTotal += $qty;
                } else {
                    $teaTotal += $qty;
                }
            }

            $withinSuffix = $neededWithin !== null ? " ({$neededWithin})" : '';
            Database::insert(
                "INSERT INTO order_events (order_id, actor_id, actor_name, from_status, to_status, note, created_at)
                 VALUES (?, ?, ?, NULL, 'pending', ?, NOW())",
                [$id, $user['id'], $user['full_name'], 'Guest/Meeting order submitted' . $withinSuffix]
            );

            $title = "Guest Order — {$room['name']}{$withinSuffix}";
            $meetingStr = $meetingTitle !== '' ? " ({$meetingTitle})" : '';
            $body = "By {$user['full_name']}{$meetingStr}: {$teaTotal} Tea, {$coffeeTotal} Coffee" . ($note !== '' ? ". {$note}" : '');

            PushService::createStaffInAppNotification($id, $title, $body, '/staff?tab=urgent&highlight=' . $id);
            PushService::queueStaffPush($id, $title, $body, '/staff?tab=urgent&highlight=' . $id, 'order_guest_' . $id);

            $waItems = [];
            foreach ($items as $it) {
                $b = ($it['beverage'] ?? 'tea') === 'coffee' ? 'Coffee' : 'Tea';
                $s = badge_sugar((string)($it['sugar'] ?? 'normal'));
                $waItems[] = "- " . (int)($it['quantity'] ?? 1) . "x {$b} ({$s})";
            }
            $waLines = [
                "*TeaCare Guest / Meeting Order (#{$id})*",
                "Room: {$room['name']}",
            ];
            if ($neededWithin !== null) {
                $waLines[] = "When Needed: {$neededWithin}";
            }
            $waLines[] = "Requested by: {$user['full_name']}" . ($meetingTitle !== '' ? " | {$meetingTitle}" : '') . ($guestCount ? " ({$guestCount} guests)" : '');
            $waLines[] = implode("\n", $waItems);
            if ($note !== '') {
                $waLines[] = "Note: {$note}";
            }
            WhatsAppService::queueGroupMessage($id, implode("\n", $waLines), 'wa_guest_' . $id);

            return $id;
        });

        return Database::fetchOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
    }

    public static function transitionStatus(
        int $orderId,
        string $targetStatus,
        array $actor,
        ?string $note = null,
        ?int $estimatedMinutes = null
    ): array {
        $allowedMap = [
            'pending'          => ['accepted', 'preparing', 'almost_done', 'delivered', 'cancelled'],
            'accepted'         => ['preparing', 'almost_done', 'delivered', 'cancelled'],
            'preparing'        => ['almost_done', 'delivered', 'cancelled'],
            'almost_done'      => ['delivered', 'cancelled'],
            'delivered'        => [],
            'cancelled'        => [],
            'system_cancelled' => [],
        ];

        return Database::transaction(function () use ($orderId, $targetStatus, $actor, $note, $estimatedMinutes, $allowedMap): array {
            $order = Database::fetchOne('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$order) {
                throw new \RuntimeException('Order not found.');
            }

            $current = $order['status'];
            if ($current === $targetStatus) {
                throw new \RuntimeException('Order is already ' . $targetStatus . '.');
            }
            if (!in_array($targetStatus, $allowedMap[$current] ?? [], true)) {
                throw new \RuntimeException("Cannot change order from {$current} to {$targetStatus}.");
            }

            $sets = ['status = ?', 'updated_at = NOW()'];
            $params = [$targetStatus];
            $eventNote = $note;

            if ($targetStatus === 'accepted') {
                $sets[] = 'accepted_by = ?';
                $sets[] = 'accepted_at = NOW()';
                $params[] = $actor['id'];
            } elseif ($targetStatus === 'preparing') {
                if (empty($order['accepted_by'])) {
                    $sets[] = 'accepted_by = ?';
                    $sets[] = 'accepted_at = NOW()';
                    $params[] = $actor['id'];
                }
                if (empty($order['prepared_at'])) {
                    $sets[] = 'prepared_at = NOW()';
                }
            } elseif ($targetStatus === 'almost_done') {
                $mins = $estimatedMinutes !== null ? max(1, min(30, $estimatedMinutes)) : 5;
                if (empty($order['accepted_by'])) {
                    $sets[] = 'accepted_by = ?';
                    $sets[] = 'accepted_at = NOW()';
                    $params[] = $actor['id'];
                }
                if (empty($order['prepared_at'])) {
                    $sets[] = 'prepared_at = NOW()';
                }
                $sets[] = 'estimated_minutes = ?';
                $sets[] = 'almost_done_at = NOW()';
                $params[] = $mins;
                if ($eventNote === null || $eventNote === '') {
                    $eventNote = "Almost done (~{$mins} min)";
                }
            } elseif ($targetStatus === 'delivered') {
                if (empty($order['accepted_by'])) {
                    $sets[] = 'accepted_by = ?';
                    $sets[] = 'accepted_at = NOW()';
                    $params[] = $actor['id'];
                }
                if (empty($order['prepared_at'])) {
                    $sets[] = 'prepared_at = NOW()';
                }
                $sets[] = 'delivered_by = ?';
                $sets[] = 'delivered_at = NOW()';
                $params[] = $actor['id'];
            } elseif ($targetStatus === 'cancelled') {
                $sets[] = 'cancelled_at = NOW()';
                $sets[] = 'cancel_reason = ?';
                $params[] = $note;
            }

            $params[] = $orderId;
            $params[] = $current;

            $affected = Database::execute(
                'UPDATE orders SET ' . implode(', ', $sets) . ' WHERE id = ? AND status = ?',
                $params
            );
            if ($affected === 0) {
                throw new \RuntimeException('Order status was already updated by another user.');
            }

            Database::insert(
                'INSERT INTO order_events (order_id, actor_id, actor_name, from_status, to_status, note, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$orderId, $actor['id'], $actor['full_name'], $current, $targetStatus, $eventNote]
            );

            return Database::fetchOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
        });
    }

    public static function maybeAutoCancelExpiredOrders(int $cooldownSeconds = 60): int
    {
        $stampFile = rtrim(sys_get_temp_dir(), '/\\') . '/teacare_autocancel.stamp';
        $last = @filemtime($stampFile);
        if ($last !== false && (time() - $last) < $cooldownSeconds) {
            return 0;
        }
        @touch($stampFile);
        return self::autoCancelExpiredOrders();
    }

    public static function autoCancelExpiredOrders(?\DateTimeImmutable $now = null): int
    {
        $refNow = $now ?? ScheduleService::now();
        $cutoffStr = $refNow->modify('-3 hours')->format('Y-m-d H:i:s');
        $totalCancelled = 0;

        // Rule A: Pending order not accepted within 3 hours -> system_cancelled
        $pendingExpired = Database::fetchAll(
            "SELECT id FROM orders WHERE status = 'pending' AND created_at <= ?",
            [$cutoffStr]
        );
        foreach ($pendingExpired as $row) {
            $id = (int)$row['id'];
            $done = Database::transaction(function () use ($id): bool {
                $ord = Database::fetchOne('SELECT id, status FROM orders WHERE id = ? FOR UPDATE', [$id]);
                if (!$ord || $ord['status'] !== 'pending') {
                    return false;
                }
                $reason = 'System auto-cancelled (not accepted within 3 hours)';
                $affected = Database::execute(
                    "UPDATE orders
                     SET status = 'system_cancelled', cancelled_at = NOW(), cancel_reason = ?, updated_at = NOW()
                     WHERE id = ? AND status = 'pending'",
                    [$reason, $id]
                );
                if ($affected === 0) {
                    return false;
                }
                Database::insert(
                    "INSERT INTO order_events (order_id, actor_id, actor_name, from_status, to_status, note, created_at)
                     VALUES (?, NULL, 'System', 'pending', 'system_cancelled', ?, NOW())",
                    [$id, $reason]
                );
                return true;
            });
            if ($done) {
                $totalCancelled++;
            }
        }

        // Rule B: Accepted/Preparing/Almost Done order not delivered within 3 hours of acceptance -> system_cancelled
        $activeExpired = Database::fetchAll(
            "SELECT id FROM orders
             WHERE status IN ('accepted', 'preparing', 'almost_done')
               AND (
                   (accepted_at IS NOT NULL AND accepted_at <= ?)
                   OR (accepted_at IS NULL AND prepared_at IS NOT NULL AND prepared_at <= ?)
                   OR (accepted_at IS NULL AND prepared_at IS NULL AND created_at <= ?)
               )",
            [$cutoffStr, $cutoffStr, $cutoffStr]
        );
        foreach ($activeExpired as $row) {
            $id = (int)$row['id'];
            $done = Database::transaction(function () use ($id): bool {
                $ord = Database::fetchOne('SELECT id, status FROM orders WHERE id = ? FOR UPDATE', [$id]);
                if (!$ord || !in_array($ord['status'], ['accepted', 'preparing', 'almost_done'], true)) {
                    return false;
                }
                $fromStatus = (string)$ord['status'];
                $reason = 'System auto-cancelled (not delivered within 3 hours)';
                $affected = Database::execute(
                    "UPDATE orders
                     SET status = 'system_cancelled', cancelled_at = NOW(), cancel_reason = ?, updated_at = NOW()
                     WHERE id = ? AND status = ?",
                    [$reason, $id, $fromStatus]
                );
                if ($affected === 0) {
                    return false;
                }
                Database::insert(
                    "INSERT INTO order_events (order_id, actor_id, actor_name, from_status, to_status, note, created_at)
                     VALUES (?, NULL, 'System', ?, 'system_cancelled', ?, NOW())",
                    [$id, $fromStatus, $reason]
                );
                return true;
            });
            if ($done) {
                $totalCancelled++;
            }
        }

        return $totalCancelled;
    }

    public static function deliverRoomOrders(int $roomId, string $date, string $slotKey, array $orderIds, array $actor): int
    {
        if ($roomId <= 0 || empty($orderIds)) {
            return 0;
        }
        $cleanIds = array_values(array_filter(array_map('intval', $orderIds), fn($v) => $v > 0));
        if (empty($cleanIds)) {
            return 0;
        }

        $count = 0;
        Database::transaction(function () use ($cleanIds, $roomId, $date, $slotKey, $actor, &$count): void {
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $params = array_merge($cleanIds, [$date, $slotKey]);
            $sql = "SELECT * FROM orders
                    WHERE id IN ({$placeholders})
                      AND order_date = ?
                      AND order_type = 'regular'
                      AND slot_key = ?
                      AND status IN ('pending', 'accepted', 'preparing', 'almost_done')
                    FOR UPDATE";
            $orders = Database::fetchAll($sql, $params);

            foreach ($orders as $order) {
                if ((int)($order['room_id'] ?? 0) !== $roomId) {
                    continue;
                }
                $affected = Database::execute(
                    "UPDATE orders
                     SET status = 'delivered',
                         accepted_by = COALESCE(accepted_by, ?),
                         accepted_at = COALESCE(accepted_at, NOW()),
                         prepared_at = COALESCE(prepared_at, NOW()),
                         delivered_by = ?,
                         delivered_at = NOW(),
                         updated_at = NOW()
                     WHERE id = ? AND status IN ('pending', 'accepted', 'preparing', 'almost_done')",
                    [$actor['id'], $actor['id'], $order['id']]
                );
                if ($affected > 0) {
                    Database::insert(
                        "INSERT INTO order_events (order_id, actor_id, actor_name, from_status, to_status, note, created_at)
                         VALUES (?, ?, ?, ?, 'delivered', 'Delivered in room batch', NOW())",
                        [$order['id'], $actor['id'], $actor['full_name'], $order['status']]
                    );
                    $count++;
                }
            }
        });

        return $count;
    }

    public static function getSkippedListForStaff(string $date, ?int $roomId = null, ?int $deptId = null): array
    {
        $where = ["u.status = 'active'", "u.role IN ('employee', 'admin')"];
        $params = [$date];
        if ($roomId) {
            $where[] = 'u.room_id = ?';
            $params[] = $roomId;
        }
        if ($deptId) {
            $where[] = 'u.department_id = ?';
            $params[] = $deptId;
        }

        $rows = Database::fetchAll(
            "SELECT u.id AS user_id, u.employee_id, u.full_name, u.role, u.is_paused,
                    u.department_id, u.room_id, u.cup_image,
                    d.name AS department_name, r.name AS room_name,
                    ovm.override_type AS ov_m_type, ova.override_type AS ov_a_type,
                    dpm.enabled AS dp_m_enabled, dpa.enabled AS dp_a_enabled
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN rooms r ON r.id = u.room_id
             LEFT JOIN daily_overrides ovm ON ovm.user_id = u.id AND ovm.service_date = ? AND ovm.slot_key = 'morning'
             LEFT JOIN daily_overrides ova ON ova.user_id = u.id AND ova.service_date = ? AND ova.slot_key = 'afternoon'
             LEFT JOIN default_preferences dpm ON dpm.user_id = u.id AND dpm.slot_key = 'morning'
             LEFT JOIN default_preferences dpa ON dpa.user_id = u.id AND dpa.slot_key = 'afternoon'
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.name ASC, u.full_name ASC",
            array_merge([$date], $params)
        );

        $skipped = [];
        foreach ($rows as $row) {
            if ((int)$row['is_paused'] === 1) {
                $skipped[] = [
                    'user_id'         => (int)$row['user_id'],
                    'employee_id'     => $row['employee_id'],
                    'full_name'       => $row['full_name'],
                    'department_name' => $row['department_name'] ?? 'Unassigned',
                    'room_id'         => (int)($row['room_id'] ?? 0),
                    'room_name'       => $row['room_name'] ?? 'Unassigned',
                    'cup_image'       => $row['cup_image'],
                    'session'         => 'all',
                    'reason'          => 'paused',
                ];
                continue;
            }

            foreach (['morning' => ['ov' => 'ov_m_type', 'dp' => 'dp_m_enabled'], 'afternoon' => ['ov' => 'ov_a_type', 'dp' => 'dp_a_enabled']] as $slotKey => $cols) {
                $ovType = $row[$cols['ov']];
                $dpEnabled = $row[$cols['dp']];

                if ($ovType === 'skip') {
                    $skipped[] = [
                        'user_id'         => (int)$row['user_id'],
                        'employee_id'     => $row['employee_id'],
                        'full_name'       => $row['full_name'],
                        'department_name' => $row['department_name'] ?? 'Unassigned',
                        'room_id'         => (int)($row['room_id'] ?? 0),
                        'room_name'       => $row['room_name'] ?? 'Unassigned',
                        'cup_image'       => $row['cup_image'],
                        'session'         => $slotKey,
                        'reason'          => 'skipped_today',
                    ];
                } elseif ($ovType !== 'custom' && $dpEnabled !== null && (int)$dpEnabled === 0) {
                    $skipped[] = [
                        'user_id'         => (int)$row['user_id'],
                        'employee_id'     => $row['employee_id'],
                        'full_name'       => $row['full_name'],
                        'department_name' => $row['department_name'] ?? 'Unassigned',
                        'room_id'         => (int)($row['room_id'] ?? 0),
                        'room_name'       => $row['room_name'] ?? 'Unassigned',
                        'cup_image'       => $row['cup_image'],
                        'session'         => $slotKey,
                        'reason'          => 'disabled_default',
                    ];
                }
            }
        }

        return $skipped;
    }

    public static function getSkippedCountForStaff(string $date): int
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN u.is_paused = 1 THEN 1
                    ELSE
                        (CASE WHEN ovm.override_type = 'skip' OR (ovm.override_type IS NULL AND dpm.enabled = 0) THEN 1 ELSE 0 END)
                        + (CASE WHEN ova.override_type = 'skip' OR (ova.override_type IS NULL AND dpa.enabled = 0) THEN 1 ELSE 0 END)
                END
            ), 0) AS cnt
             FROM users u
             LEFT JOIN daily_overrides ovm ON ovm.user_id = u.id AND ovm.service_date = ? AND ovm.slot_key = 'morning'
             LEFT JOIN daily_overrides ova ON ova.user_id = u.id AND ova.service_date = ? AND ova.slot_key = 'afternoon'
             LEFT JOIN default_preferences dpm ON dpm.user_id = u.id AND dpm.slot_key = 'morning'
             LEFT JOIN default_preferences dpa ON dpa.user_id = u.id AND dpa.slot_key = 'afternoon'
             WHERE u.status = 'active' AND u.role IN ('employee', 'admin')",
            [$date, $date]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public static function getSlotDataForStaff(string $date, string $slotKey, ?int $roomId = null, ?int $deptId = null, ?array $preloadedSkipped = null): array
    {
        $slot = ScheduleService::getSlot($slotKey);
        $dayStatus = ScheduleService::getDayStatus($date);
        $cutoffPassed = $slot ? ScheduleService::isCutoffPassed($slot) : false;

        $whereFinal = ["o.order_date = ?", "o.order_type = 'regular'", "o.slot_key = ?"];
        $paramsFinal = [$date, $slotKey];
        if ($roomId) {
            $whereFinal[] = 'o.room_id = ?';
            $paramsFinal[] = $roomId;
        }
        if ($deptId) {
            $whereFinal[] = 'u.department_id = ?';
            $paramsFinal[] = $deptId;
        }

        $finalizedOrders = Database::fetchAll(
            "SELECT o.*, oi.beverage, oi.sugar, oi.quantity, oi.note AS item_note
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             LEFT JOIN users u ON u.id = o.user_id
             WHERE " . implode(' AND ', $whereFinal) . "
             ORDER BY o.room_name_snapshot ASC, o.employee_name_snapshot ASC",
            $paramsFinal
        );

        // Also check unfiltered count to know whether session itself has finalized
        $unfilteredCount = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM orders WHERE order_date = ? AND order_type = 'regular' AND slot_key = ?",
            [$date, $slotKey]
        )['cnt'] ?? 0);

        $skippedSource = $preloadedSkipped ?? self::getSkippedListForStaff($date, $roomId, $deptId);
        $slotSkipped = array_values(array_filter(
            $skippedSource,
            static fn(array $sk): bool => ($sk['session'] ?? '') === 'all' || ($sk['session'] ?? '') === $slotKey
        ));

        $summary = [
            'tea'          => 0,
            'coffee'       => 0,
            'sugar_normal' => 0,
            'sugar_less'   => 0,
            'sugar_extra'  => 0,
            'sugar_none'   => 0,
            'pending'      => 0,
            'delivered'    => 0,
            'skipped'      => count($slotSkipped),
        ];

        $rooms = [];
        $ensureRoom = static function (int $rid, string $rName) use (&$rooms): string {
            $cleanName = $rName !== '' ? $rName : 'Unassigned';
            $roomKey = ($rid > 0 ? (string)$rid : '0') . '|' . $cleanName;
            if (!isset($rooms[$roomKey])) {
                $rooms[$roomKey] = [
                    'room_id'       => $rid,
                    'room_name'     => $cleanName,
                    'summary'       => [
                        'total_cups'    => 0,
                        'tea'           => 0,
                        'coffee'        => 0,
                        'sugar_normal'  => 0,
                        'sugar_less'    => 0,
                        'sugar_extra'   => 0,
                        'sugar_none'    => 0,
                        'pending'       => 0,
                        'delivered'     => 0,
                        'cancelled'     => 0,
                        'skipped_count' => 0,
                    ],
                    'orders'        => [],
                    'skipped_users' => [],
                ];
            }
            return $roomKey;
        };

        $addOrderToRoom = static function (string $roomKey, string $bev, string $sug, int $qty, string $status, bool $isCancelled) use (&$rooms, &$summary): void {
            if (!$isCancelled) {
                $bevCol = $bev === 'coffee' ? 'coffee' : 'tea';
                $sugCol = 'sugar_' . ($sug !== '' ? $sug : 'normal');
                $summary[$bevCol] += $qty;
                $summary[$sugCol] = ($summary[$sugCol] ?? 0) + $qty;
                $rooms[$roomKey]['summary']['total_cups'] += $qty;
                $rooms[$roomKey]['summary'][$bevCol] += $qty;
                $rooms[$roomKey]['summary'][$sugCol] = ($rooms[$roomKey]['summary'][$sugCol] ?? 0) + $qty;
            }
            if ($status === 'delivered') {
                $summary['delivered']++;
                $rooms[$roomKey]['summary']['delivered']++;
            } elseif ($isCancelled) {
                $rooms[$roomKey]['summary']['cancelled']++;
            } else {
                $summary['pending']++;
                $rooms[$roomKey]['summary']['pending']++;
            }
        };

        $isActiveSessionWindow = $unfilteredCount > 0 || ($dayStatus['is_open'] && $slot && (int)$slot['is_active'] === 1 && !$cutoffPassed);

        if ($unfilteredCount > 0) {
            foreach ($finalizedOrders as $o) {
                $qty = (int)$o['quantity'];
                $isCancelled = in_array($o['status'], ['cancelled', 'system_cancelled'], true);
                $roomKey = $ensureRoom((int)($o['room_id'] ?? 0), (string)($o['room_name_snapshot'] ?? ''));
                $addOrderToRoom($roomKey, (string)$o['beverage'], (string)$o['sugar'], $qty, (string)$o['status'], $isCancelled);
                $rooms[$roomKey]['orders'][] = $o;
            }
        } elseif ($isActiveSessionWindow) {
            $whereEst = ["u.status = 'active'", "u.is_paused = 0", "u.role IN ('employee', 'admin')"];
            $paramsEst = [$date, $slotKey, $slotKey];
            if ($roomId) {
                $whereEst[] = 'u.room_id = ?';
                $paramsEst[] = $roomId;
            }
            if ($deptId) {
                $whereEst[] = 'u.department_id = ?';
                $paramsEst[] = $deptId;
            }

            $candidates = Database::fetchAll(
                "SELECT u.id AS user_id, u.employee_id, u.full_name, u.role, u.room_id, u.cup_image,
                        d.name AS department_name, r.name AS room_name,
                        ov.override_type, ov.beverage AS ov_beverage, ov.sugar AS ov_sugar, ov.quantity AS ov_quantity, ov.note AS ov_note,
                        dp.id AS dp_id, dp.enabled AS dp_enabled, dp.beverage AS dp_beverage, dp.sugar AS dp_sugar, dp.quantity AS dp_quantity, dp.note AS dp_note
                 FROM users u
                 LEFT JOIN departments d ON d.id = u.department_id
                 LEFT JOIN rooms r ON r.id = u.room_id
                 LEFT JOIN daily_overrides ov ON ov.user_id = u.id AND ov.service_date = ? AND ov.slot_key = ?
                 LEFT JOIN default_preferences dp ON dp.user_id = u.id AND dp.slot_key = ?
                 WHERE " . implode(' AND ', $whereEst) . "
                 ORDER BY r.name ASC, u.full_name ASC",
                $paramsEst
            );

            foreach ($candidates as $c) {
                if ($c['override_type'] === 'skip') {
                    continue;
                }
                if ($c['override_type'] === 'custom' && !empty($c['ov_beverage'])) {
                    $bev = $c['ov_beverage'];
                    $sug = $c['ov_sugar'] ?: 'normal';
                    $qty = max(1, (int)$c['ov_quantity']);
                    $note = (string)$c['ov_note'];
                } elseif ($c['dp_id'] !== null) {
                    if ((int)$c['dp_enabled'] !== 1 || empty($c['dp_beverage'])) {
                        continue;
                    }
                    $bev = $c['dp_beverage'];
                    $sug = $c['dp_sugar'] ?: 'normal';
                    $qty = max(1, (int)$c['dp_quantity']);
                    $note = (string)$c['dp_note'];
                } elseif ($c['role'] === 'employee') {
                    $bev = 'tea';
                    $sug = 'normal';
                    $qty = 1;
                    $note = '';
                } else {
                    continue;
                }

                $roomKey = $ensureRoom((int)($c['room_id'] ?? 0), (string)($c['room_name'] ?? ''));
                $addOrderToRoom($roomKey, (string)$bev, (string)$sug, $qty, 'estimated', false);
                $rooms[$roomKey]['orders'][] = [
                    'id'                       => null,
                    'user_id'                  => $c['user_id'],
                    'employee_name_snapshot'   => $c['full_name'],
                    'employee_id_snapshot'     => $c['employee_id'],
                    'department_name_snapshot' => $c['department_name'] ?? '',
                    'room_id'                  => $c['room_id'],
                    'room_name_snapshot'       => $c['room_name'] ?: 'Unassigned',
                    'cup_image_snapshot'       => $c['cup_image'],
                    'beverage'                 => $bev,
                    'sugar'                    => $sug,
                    'quantity'                 => $qty,
                    'note'                     => $note,
                    'status'                   => 'estimated',
                ];
            }
        }

        if ($isActiveSessionWindow && !empty($slotSkipped)) {
            foreach ($slotSkipped as $sk) {
                $roomKey = $ensureRoom((int)($sk['room_id'] ?? 0), (string)($sk['room_name'] ?? ''));
                $rooms[$roomKey]['skipped_users'][] = $sk;
                $rooms[$roomKey]['summary']['skipped_count']++;
            }
        }

        if (count($rooms) > 1) {
            uasort($rooms, static function (array $a, array $b): int {
                if ($a['room_name'] === 'Unassigned' && $b['room_name'] !== 'Unassigned') {
                    return 1;
                }
                if ($b['room_name'] === 'Unassigned' && $a['room_name'] !== 'Unassigned') {
                    return -1;
                }
                return strnatcasecmp($a['room_name'], $b['room_name']);
            });
        }

        return [
            'slot'          => $slot,
            'day_status'    => $dayStatus,
            'is_finalized'  => $unfilteredCount > 0,
            'cutoff_passed' => $cutoffPassed,
            'summary'       => $summary,
            'rooms'         => array_values($rooms),
        ];
    }

    public static function getOrdersWithItems(array $orders): array
    {
        if (empty($orders)) {
            return [];
        }
        $ids = array_map(fn($o) => (int)$o['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $items = Database::fetchAll(
            "SELECT * FROM order_items WHERE order_id IN ({$placeholders}) ORDER BY id ASC",
            $ids
        );
        $grouped = [];
        foreach ($items as $item) {
            $grouped[(int)$item['order_id']][] = $item;
        }
        foreach ($orders as &$o) {
            $o['items'] = $grouped[(int)$o['id']] ?? [];
        }
        return $orders;
    }

    public static function searchEmployeesForStaff(string $query, int $limit = 15): array
    {
        $limit = max(1, min(30, $limit));
        $q = trim($query);
        $where = ["u.status = 'active'", "u.role IN ('employee', 'admin')"];
        $params = [];

        if ($q !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $like = '%' . $escaped . '%';
            $where[] = "(u.full_name LIKE ? OR u.employee_id LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        $rows = Database::fetchAll(
            "SELECT u.id, u.employee_id, u.full_name, u.role, u.is_paused, u.cup_image,
                    d.name AS department_name, r.name AS room_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN rooms r ON r.id = u.room_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY u.full_name ASC
             LIMIT {$limit}",
            $params
        );

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['is_paused'] = (int)$row['is_paused'];
            $row['department_name'] = $row['department_name'] ?: 'Unassigned';
            $row['room_name'] = $row['room_name'] ?: 'Unassigned';
            $row['cup_url'] = cup_image_url($row['cup_image'] ?? null, $row['id']);
        }
        return $rows;
    }

    public static function getEmployeeTodayOverviewForStaff(int $userId, string $date): ?array
    {
        $user = Database::fetchOne(
            "SELECT u.id, u.employee_id, u.full_name, u.role, u.is_paused, u.cup_image,
                    d.name AS department_name, r.name AS room_name
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN rooms r ON r.id = u.room_id
             WHERE u.id = ? AND u.status = 'active' AND u.role IN ('employee', 'admin')
             LIMIT 1",
            [$userId]
        );
        if (!$user) {
            return null;
        }

        $defaults = Database::fetchAll(
            "SELECT slot_key, enabled, beverage, sugar, quantity, note FROM default_preferences WHERE user_id = ?",
            [$userId]
        );
        $defaultsBySlot = [];
        foreach ($defaults as $dp) {
            $defaultsBySlot[$dp['slot_key']] = $dp;
        }

        $overrides = Database::fetchAll(
            "SELECT slot_key, override_type, beverage, sugar, quantity, note FROM daily_overrides WHERE user_id = ? AND service_date = ?",
            [$userId, $date]
        );
        $overridesBySlot = [];
        foreach ($overrides as $ov) {
            $overridesBySlot[$ov['slot_key']] = $ov;
        }

        $regularOrders = Database::fetchAll(
            "SELECT o.id, o.slot_key, o.status, oi.beverage, oi.sugar, oi.quantity, oi.note
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = ? AND o.order_date = ? AND o.order_type = 'regular'
             ORDER BY o.id DESC",
            [$userId, $date]
        );
        $ordersBySlot = [];
        foreach ($regularOrders as $ord) {
            if (!isset($ordersBySlot[$ord['slot_key']])) {
                $ordersBySlot[$ord['slot_key']] = $ord;
            }
        }

        $dayStatus = ScheduleService::getDayStatus($date);
        $isPaused = (int)$user['is_paused'] === 1;

        $resolveSlot = static function (string $slotKey) use ($user, $defaultsBySlot, $overridesBySlot, $ordersBySlot, $dayStatus, $isPaused): array {
            $slot = ScheduleService::getSlot($slotKey);
            $dp = $defaultsBySlot[$slotKey] ?? null;
            $ov = $overridesBySlot[$slotKey] ?? null;
            $ord = $ordersBySlot[$slotKey] ?? null;

            $savedDefault = null;
            if ($dp !== null) {
                $savedDefault = [
                    'enabled'        => (int)$dp['enabled'] === 1,
                    'beverage_label' => !empty($dp['beverage']) ? badge_beverage((string)$dp['beverage']) : null,
                    'sugar_label'    => !empty($dp['sugar']) ? badge_sugar((string)$dp['sugar']) : null,
                    'quantity'       => max(1, (int)$dp['quantity']),
                    'note'           => (string)($dp['note'] ?? ''),
                ];
            }

            $base = [
                'slot_key'      => $slotKey,
                'slot_label'    => $slot['label_en'] ?? ucfirst($slotKey),
                'service_time'  => $slot && !empty($slot['service_time']) ? format_time_12($slot['service_time']) : '',
                'day_open'      => (bool)($dayStatus['is_open'] ?? true),
                'slot_active'   => $slot ? ((int)$slot['is_active'] === 1) : true,
                'beverage'      => null,
                'beverage_label'=> null,
                'sugar_label'   => null,
                'quantity'      => null,
                'note'          => '',
                'saved_default' => $savedDefault,
            ];

            if ($ord !== null) {
                $st = (string)$ord['status'];
                $statusLabel = match ($st) {
                    'delivered'        => 'Delivered',
                    'cancelled'        => 'Cancelled',
                    'system_cancelled' => 'Auto Cancelled',
                    'preparing'        => 'Preparing',
                    'almost_done'      => 'Almost Done',
                    'accepted'         => 'Accepted',
                    default            => 'Ordered (Pending)',
                };
                return array_merge($base, [
                    'status_key'     => $st === 'delivered' ? 'delivered' : (in_array($st, ['cancelled', 'system_cancelled'], true) ? $st : 'ordered'),
                    'status_label'   => $statusLabel,
                    'beverage'       => $ord['beverage'],
                    'beverage_label' => badge_beverage((string)$ord['beverage']),
                    'sugar_label'    => badge_sugar((string)$ord['sugar']),
                    'quantity'       => max(1, (int)$ord['quantity']),
                    'note'           => (string)($ord['note'] ?? ''),
                ]);
            }

            if ($isPaused) {
                return array_merge($base, [
                    'status_key'   => 'paused',
                    'status_label' => 'Paused / Away',
                ]);
            }

            if ($ov !== null && $ov['override_type'] === 'skip') {
                return array_merge($base, [
                    'status_key'   => 'skipped',
                    'status_label' => 'Skipped Today',
                ]);
            }

            if ($ov !== null && $ov['override_type'] === 'custom' && !empty($ov['beverage'])) {
                return array_merge($base, [
                    'status_key'     => 'custom',
                    'status_label'   => 'Today Override',
                    'beverage'       => $ov['beverage'],
                    'beverage_label' => badge_beverage((string)$ov['beverage']),
                    'sugar_label'    => badge_sugar((string)($ov['sugar'] ?: 'normal')),
                    'quantity'       => max(1, (int)$ov['quantity']),
                    'note'           => (string)($ov['note'] ?? ''),
                ]);
            }

            if ($dp !== null) {
                if ((int)$dp['enabled'] !== 1 || empty($dp['beverage'])) {
                    return array_merge($base, [
                        'status_key'   => 'disabled',
                        'status_label' => 'Disabled in Defaults',
                    ]);
                }
                return array_merge($base, [
                    'status_key'     => 'default',
                    'status_label'   => 'Default Preference',
                    'beverage'       => $dp['beverage'],
                    'beverage_label' => badge_beverage((string)$dp['beverage']),
                    'sugar_label'    => badge_sugar((string)($dp['sugar'] ?: 'normal')),
                    'quantity'       => max(1, (int)$dp['quantity']),
                    'note'           => (string)($dp['note'] ?? ''),
                ]);
            }

            if ($user['role'] === 'employee') {
                return array_merge($base, [
                    'status_key'     => 'auto_default',
                    'status_label'   => 'Auto-Default',
                    'beverage'       => 'tea',
                    'beverage_label' => badge_beverage('tea'),
                    'sugar_label'    => badge_sugar('normal'),
                    'quantity'       => 1,
                    'note'           => '',
                ]);
            }

            return array_merge($base, [
                'status_key'   => 'disabled',
                'status_label' => 'Not Configured',
            ]);
        };

        return [
            'id'              => (int)$user['id'],
            'employee_id'     => (string)$user['employee_id'],
            'full_name'       => (string)$user['full_name'],
            'role'            => (string)$user['role'],
            'is_paused'       => $isPaused,
            'department_name' => $user['department_name'] ?: 'Unassigned',
            'room_name'       => $user['room_name'] ?: 'Unassigned',
            'cup_url'         => cup_image_url($user['cup_image'] ?? null, (int)$user['id']),
            'today_date'      => $date,
            'morning'         => $resolveSlot('morning'),
            'afternoon'       => $resolveSlot('afternoon'),
        ];
    }
}
