-- MySQL 8+ database structure for the MYS Attendance System.
CREATE DATABASE IF NOT EXISTS mys_attendance
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mys_attendance;

CREATE TABLE IF NOT EXISTS departments (
    department_id VARCHAR(100) PRIMARY KEY,
    department_name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employees (
    employee_number VARCHAR(50) PRIMARY KEY,
    employee_name VARCHAR(150) NOT NULL,
    position VARCHAR(150) NOT NULL DEFAULT '',
    employee_type ENUM('Employee', 'Contractor', 'Volunteer') NOT NULL DEFAULT 'Employee',
    department_id VARCHAR(100) NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_employees_type (employee_type),
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id)
        REFERENCES departments(department_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance (
    attendance_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_employee_date (employee_number, attendance_date),
    KEY idx_attendance_date (attendance_date),
    KEY idx_attendance_department (department_id),
    KEY idx_attendance_status (status),
Longer-term / as data grows:
7. If attendance history grows over years, consider archiving old rows (e.g., move records older than 1–2 years to an attendance_archive table) to keep the hot table small.    KEY idx_attendance_updated_at (updated_at),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_number)
        REFERENCES employees(employee_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS excuses (
    excuse_id VARCHAR(40) PRIMARY KEY,
    employee_number VARCHAR(50) NOT NULL,
    employee_name VARCHAR(150) NOT NULL DEFAULT '',
    position VARCHAR(150) NOT NULL DEFAULT '',
    department_id VARCHAR(100) NULL,
    department_name VARCHAR(150) NOT NULL DEFAULT '',
    absence_start DATE NOT NULL,
    absence_end DATE NOT NULL,
    absence_time VARCHAR(100) NOT NULL DEFAULT '',
    reason ENUM('Medical Appointment', 'Illness', 'Family Emergency', 'Official Assignment', 'Other') NOT NULL,
    other_reason VARCHAR(255) NOT NULL DEFAULT '',
    supporting_documents JSON NULL,
    supervisor_name VARCHAR(150) NOT NULL DEFAULT '',
    supervisor_decision ENUM('Approved', 'Not Approved') NULL,
    supervisor_comments TEXT NULL,
    hr_reviewed_by VARCHAR(150) NOT NULL DEFAULT '',
    hr_approved ENUM('Yes', 'No') NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    KEY idx_excuses_dates (absence_start, absence_end),
    KEY idx_excuses_employee (employee_number),
    CONSTRAINT fk_excuses_employee FOREIGN KEY (employee_number)
        REFERENCES employees(employee_number) ON DELETE CASCADE,
    CONSTRAINT chk_excuse_dates CHECK (absence_end >= absence_start)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS letters (
    letter_id VARCHAR(60) PRIMARY KEY,
    employee_number VARCHAR(50) NOT NULL,
    employee_name VARCHAR(150) NOT NULL DEFAULT '',
    position VARCHAR(150) NOT NULL DEFAULT '',
    department_id VARCHAR(100) NULL,
    department_name VARCHAR(150) NOT NULL DEFAULT 'Unassigned',
    letter_type ENUM('Warning Letter', 'Transfer Letter') NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    details TEXT NULL,
    issued_by VARCHAR(150) NOT NULL DEFAULT '',
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) NOT NULL DEFAULT 'Issued',
    notes TEXT NULL,
    KEY idx_letters_employee (employee_number),
    CONSTRAINT fk_letters_employee FOREIGN KEY (employee_number)
        REFERENCES employees(employee_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS employee_documents (
    document_id VARCHAR(60) PRIMARY KEY,
    employee_number VARCHAR(50) NOT NULL,
    label VARCHAR(150) NOT NULL DEFAULT '',
    original_name VARCHAR(255) NOT NULL DEFAULT '',
    filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL DEFAULT '',
    size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_employee_documents_employee (employee_number),
    CONSTRAINT fk_employee_documents_employee FOREIGN KEY (employee_number)
        REFERENCES employees(employee_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS devices (
    device_id VARCHAR(120) PRIMARY KEY,
    device_name VARCHAR(150) NOT NULL DEFAULT '',
    location_name VARCHAR(150) NOT NULL DEFAULT '',
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    username VARCHAR(40) PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hr', 'supervisor', 'viewer') NOT NULL DEFAULT 'viewer',
    department_id VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_department FOREIGN KEY (department_id)
        REFERENCES departments(department_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Single-row table holding the legacy/primary geofence settings.
CREATE TABLE IF NOT EXISTS geofence_settings (
    id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    radius_meters INT UNSIGNED NOT NULL DEFAULT 150,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_geofence_singleton CHECK (id = 1)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS geofence_locations (
    location_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    radius_meters INT UNSIGNED NOT NULL DEFAULT 150,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO geofence_settings (id, enabled, radius_meters)
    VALUES (1, 0, 150)
    ON DUPLICATE KEY UPDATE id = id;

-- Default admin account: username "admin", password "admin123" (change this immediately after first login).
INSERT INTO users (username, password_hash, role)
    VALUES ('admin', '$2y$10$gLbOMK4pCc1ZQXWbQIIC1u1Xjv/hSJ8G3ANA77PPyDW6nYoX89daO', 'admin')
    ON DUPLICATE KEY UPDATE username = username;
