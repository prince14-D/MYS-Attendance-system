-- Adds performance indexes to an existing mys_attendance database.
-- Safe to run multiple times: each statement is guarded against duplicate keys.
USE mys_attendance;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'attendance' AND index_name = 'idx_attendance_department'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE attendance ADD KEY idx_attendance_department (department_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'attendance' AND index_name = 'idx_attendance_status'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE attendance ADD KEY idx_attendance_status (status)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'employees' AND index_name = 'idx_employees_type'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE employees ADD KEY idx_employees_type (employee_type)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'attendance' AND index_name = 'idx_attendance_updated_at'
);
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE attendance ADD KEY idx_attendance_updated_at (updated_at)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Cold storage table for old attendance rows; see database/archive_old_attendance.php.
CREATE TABLE IF NOT EXISTS attendance_archive (
    attendance_id BIGINT UNSIGNED PRIMARY KEY,
    employee_number VARCHAR(50) NOT NULL,
    employee_name VARCHAR(150) NOT NULL DEFAULT '',
    position VARCHAR(150) NOT NULL DEFAULT '',
    department_id VARCHAR(100) NULL,
    department_name VARCHAR(150) NOT NULL DEFAULT 'Unassigned',
    attendance_date DATE NOT NULL,
    clock_in TIME NULL,
    clock_out TIME NULL,
    clock_in_photo VARCHAR(255) NOT NULL DEFAULT '',
    clock_in_latitude DECIMAL(10,7) NULL,
    clock_in_longitude DECIMAL(10,7) NULL,
    clock_in_accuracy_m DECIMAL(10,2) NULL,
    status ENUM('Complete', 'Incomplete') NOT NULL DEFAULT 'Incomplete',
    late TINYINT(1) NOT NULL DEFAULT 0,
    late_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    early_out TINYINT(1) NOT NULL DEFAULT 0,
    early_out_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attendance_archive_date (attendance_date),
    KEY idx_attendance_archive_employee (employee_number)
) ENGINE=InnoDB;

