-- MySQL 8+ database structure for the MYS Attendance System.
CREATE DATABASE IF NOT EXISTS mys_attendance
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mys_attendance;

CREATE TABLE departments (
    department_id VARCHAR(100) PRIMARY KEY,
    department_name VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE employees (
    employee_number VARCHAR(50) PRIMARY KEY,
    employee_name VARCHAR(150) NOT NULL,
    position VARCHAR(150) NOT NULL DEFAULT '',
    department_id VARCHAR(100) NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_employees_department FOREIGN KEY (department_id)
        REFERENCES departments(department_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE attendance (
    attendance_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_number VARCHAR(50) NOT NULL,
    attendance_date DATE NOT NULL,
    clock_in TIME NULL,
    clock_out TIME NULL,
    clock_in_photo VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('Complete', 'Incomplete') NOT NULL DEFAULT 'Incomplete',
    late TINYINT(1) NOT NULL DEFAULT 0,
    early_out TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_employee_date (employee_number, attendance_date),
    KEY idx_attendance_date (attendance_date),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_number)
        REFERENCES employees(employee_number) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE excuses (
    excuse_id VARCHAR(40) PRIMARY KEY,
    employee_number VARCHAR(50) NOT NULL,
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
    KEY idx_excuses_dates (absence_start, absence_end),
    KEY idx_excuses_employee (employee_number),
    CONSTRAINT fk_excuses_employee FOREIGN KEY (employee_number)
        REFERENCES employees(employee_number) ON DELETE CASCADE,
    CONSTRAINT chk_excuse_dates CHECK (absence_end >= absence_start)
) ENGINE=InnoDB;
