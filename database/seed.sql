INSERT IGNORE INTO service_slots (slot_key, label_en, label_bn, service_time, cutoff_time, is_active) VALUES
('morning', 'Morning Tea', 'সকালের চা', '09:45:00', '09:30:00', 1),
('afternoon', 'Afternoon Tea', 'বিকেলের চা', '15:30:00', '15:15:00', 1);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('office_name', 'TeaCare Office'),
('office_logo', ''),
('timezone', 'Asia/Dhaka'),
('working_days', '["sun","mon","tue","wed","thu"]'),
('allow_self_registration', '1'),
('require_admin_approval', '0'),
('allow_employee_dept_room_edit', '1'),
('push_enabled', '1'),
('guest_ordering_enabled', '1'),
('evo_enabled', '0'),
('evo_base_url', ''),
('evo_api_key', ''),
('evo_instance', ''),
('evo_group_jid', '');

INSERT IGNORE INTO departments (id, name, is_active) VALUES
(1, 'Web & Software Development', 1),
(2, 'Customer Service', 1),
(3, 'RDP', 1),
(4, 'Digital Marketing', 1),
(5, 'Support', 1),
(6, 'Hardware & Maintenance', 1),
(7, 'Training & Communication', 1),
(8, 'Human Resources (HR)', 1),
(9, 'Accounts & Finance', 1),
(10, 'Media & Graphics', 1),
(11, 'Data Center & Administration', 1),
(12, 'PBX & SMS', 1),
(13, 'Sales', 1),
(14, 'Marketing', 1),
(15, 'Office Assistant', 1),
(16, 'First Line Agents', 1);

INSERT IGNORE INTO rooms (id, name, floor, department_id, room_type, is_active) VALUES
(1, 'Room 101', '1st Floor', 11, 'office', 1),
(2, 'Room 201', '2nd Floor', 1, 'office', 1),
(3, 'Room 204', '2nd Floor', 9, 'office', 1),
(4, 'Room 301', '3rd Floor', 13, 'office', 1),
(5, 'Pantry / Kitchen', 'Ground Floor', 15, 'office', 1),
(6, 'Conference Room A', '2nd Floor', NULL, 'meeting', 1),
(7, 'Board Room', '3rd Floor', NULL, 'meeting', 1);
