SET NAMES utf8mb4;
SET time_zone = '+06:00';

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    floor VARCHAR(60) NULL,
    department_id INT UNSIGNED NULL,
    room_type ENUM('office', 'meeting') NOT NULL DEFAULT 'office',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rooms_type_active (room_type, is_active),
    CONSTRAINT fk_rooms_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('employee', 'staff', 'admin') NOT NULL DEFAULT 'employee',
    department_id INT UNSIGNED NULL,
    room_id INT UNSIGNED NULL,
    cup_image VARCHAR(255) NULL,
    status ENUM('pending', 'active', 'disabled') NOT NULL DEFAULT 'active',
    can_order_for_guests TINYINT(1) NOT NULL DEFAULT 1,
    is_paused TINYINT(1) NOT NULL DEFAULT 0,
    show_funny_monkey TINYINT(1) NOT NULL DEFAULT 1,
    monkey_dismissed_date DATE NULL,
    preferred_lang VARCHAR(5) NOT NULL DEFAULT 'en',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_status (role, status),
    INDEX idx_users_dept_room (department_id, room_id),
    INDEX idx_users_room_id (room_id),
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_key ENUM('morning', 'afternoon') NOT NULL UNIQUE,
    label_en VARCHAR(80) NOT NULL,
    label_bn VARCHAR(80) NOT NULL,
    service_time TIME NOT NULL,
    cutoff_time TIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS default_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    slot_key ENUM('morning', 'afternoon') NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    beverage ENUM('tea', 'coffee') NOT NULL DEFAULT 'tea',
    sugar ENUM('normal', 'less', 'extra', 'none') NOT NULL DEFAULT 'normal',
    quantity TINYINT UNSIGNED NOT NULL DEFAULT 1,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_default_pref_user_slot (user_id, slot_key),
    CONSTRAINT fk_default_pref_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_overrides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    service_date DATE NOT NULL,
    slot_key ENUM('morning', 'afternoon') NOT NULL,
    override_type ENUM('custom', 'skip') NOT NULL DEFAULT 'custom',
    beverage ENUM('tea', 'coffee') NULL,
    sugar ENUM('normal', 'less', 'extra', 'none') NULL,
    quantity TINYINT UNSIGNED NOT NULL DEFAULT 1,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_daily_override_user_date_slot (user_id, service_date, slot_key),
    INDEX idx_daily_override_date_slot (service_date, slot_key),
    CONSTRAINT fk_daily_override_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_date DATE NOT NULL,
    order_type ENUM('regular', 'on_demand', 'guest') NOT NULL,
    slot_key ENUM('morning', 'afternoon') NULL,
    user_id INT UNSIGNED NULL,
    employee_name_snapshot VARCHAR(150) NOT NULL,
    employee_id_snapshot VARCHAR(50) NOT NULL,
    department_name_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    room_id INT UNSIGNED NULL,
    room_name_snapshot VARCHAR(120) NOT NULL DEFAULT '',
    cup_image_snapshot VARCHAR(255) NULL,
    meeting_title VARCHAR(150) NULL,
    guest_count INT UNSIGNED NULL,
    requested_within VARCHAR(40) NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('pending', 'accepted', 'preparing', 'almost_done', 'delivered', 'cancelled', 'system_cancelled') NOT NULL DEFAULT 'pending',
    idempotency_key VARCHAR(100) NULL UNIQUE,
    regular_unique_key VARCHAR(100) NULL UNIQUE,
    accepted_by INT UNSIGNED NULL,
    accepted_at DATETIME NULL,
    prepared_at DATETIME NULL,
    estimated_minutes TINYINT UNSIGNED NULL,
    almost_done_at DATETIME NULL,
    delivered_by INT UNSIGNED NULL,
    delivered_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_date_type_status (order_date, order_type, status),
    INDEX idx_orders_date_created (order_date, created_at),
    INDEX idx_orders_user_date (user_id, order_date),
    INDEX idx_orders_room_date (room_id, order_date),
    INDEX idx_orders_status_created (status, created_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    beverage ENUM('tea', 'coffee') NOT NULL,
    sugar ENUM('normal', 'less', 'none') NOT NULL DEFAULT 'normal',
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    note VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_items_order (order_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NULL,
    actor_name VARCHAR(150) NOT NULL DEFAULT 'System',
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NOT NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_events_order (order_id),
    CONSTRAINT fk_order_events_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL UNIQUE,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_success_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_push_subs_user_active (user_id, is_active),
    INDEX idx_push_subs_active_updated (is_active, updated_at),
    CONSTRAINT fk_push_subs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NULL,
    target_role ENUM('staff', 'user') NOT NULL DEFAULT 'staff',
    target_user_id INT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    body VARCHAR(500) NOT NULL,
    url VARCHAR(255) NOT NULL DEFAULT '/staff',
    status ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    next_retry_at DATETIME NULL,
    last_error TEXT NULL,
    unique_job_key VARCHAR(120) NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_notif_jobs_status_retry (status, next_retry_at),
    INDEX idx_notif_jobs_created (created_at),
    CONSTRAINT fk_notif_jobs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NULL,
    subscription_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    status ENUM('sent', 'failed', 'expired') NOT NULL,
    http_status INT NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notif_logs_created (created_at),
    INDEX idx_notif_logs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_inapp_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NULL,
    title VARCHAR(200) NOT NULL,
    body VARCHAR(500) NOT NULL,
    url VARCHAR(255) NOT NULL DEFAULT '/staff',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_staff_inapp_read_created (is_read, created_at),
    CONSTRAINT fk_staff_inapp_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS holidays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    employee_id VARCHAR(50) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_ip_time (ip_address, attempted_at),
    INDEX idx_login_attempts_emp_time (employee_id, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    actor_name VARCHAR(150) NOT NULL DEFAULT 'System',
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_logs_created (created_at),
    INDEX idx_audit_logs_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announce_date DATE NOT NULL,
    scope ENUM('all', 'morning', 'afternoon') NOT NULL DEFAULT 'all',
    announce_type ENUM('unavailable', 'time_change', 'info') NOT NULL DEFAULT 'unavailable',
    adjusted_time TIME NULL,
    reason VARCHAR(300) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_ann_date_active (announce_date, is_active),
    CONSTRAINT fk_staff_ann_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    next_retry_at DATETIME NULL,
    last_error TEXT NULL,
    unique_job_key VARCHAR(120) NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_wa_jobs_status_retry (status, next_retry_at),
    INDEX idx_wa_jobs_created (created_at),
    CONSTRAINT fk_wa_jobs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
