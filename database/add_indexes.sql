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
