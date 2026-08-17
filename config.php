<?php
declare(strict_types=1);

date_default_timezone_set('Africa/Monrovia');

const APP_NAME = 'Ministry of Youth & Sports Attendance System';
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'admin123';
const STORAGE_DIR = __DIR__ . '/storage';
const SESSION_DIR = STORAGE_DIR . '/sessions';
const PHOTOS_DIR = STORAGE_DIR . '/photos';
const ATTENDANCE_FILE = STORAGE_DIR . '/attendance.json';
const EMPLOYEES_FILE = STORAGE_DIR . '/employees.json';
const DEPARTMENTS_FILE = STORAGE_DIR . '/departments.json';
const GEOFENCE_FILE = STORAGE_DIR . '/geofence.json';
const EXCUSES_FILE = STORAGE_DIR . '/excuses.json';
const EMPLOYEE_DOCUMENTS_FILE = STORAGE_DIR . '/employee_documents.json';
const EMPLOYEE_DOCUMENTS_DIR = STORAGE_DIR . '/employee_documents';
const USERS_FILE = STORAGE_DIR . '/users.json';
const SHIFT_START_TIME = '09:00:00';
const SHIFT_END_TIME = '17:00:00';
const LATE_GRACE_MINUTES = 10;
const EARLY_OUT_GRACE_MINUTES = 0;

if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0775, true);
}

if (!is_dir(SESSION_DIR)) {
    mkdir(SESSION_DIR, 0775, true);
}

if (!is_dir(PHOTOS_DIR)) {
    mkdir(PHOTOS_DIR, 0775, true);
}

if (!is_dir(EMPLOYEE_DOCUMENTS_DIR)) {
    mkdir(EMPLOYEE_DOCUMENTS_DIR, 0775, true);
}

if (!file_exists(ATTENDANCE_FILE)) {
    file_put_contents(ATTENDANCE_FILE, json_encode([], JSON_PRETTY_PRINT));
}

if (!file_exists(EMPLOYEES_FILE)) {
    file_put_contents(EMPLOYEES_FILE, json_encode([], JSON_PRETTY_PRINT));
}

if (!file_exists(DEPARTMENTS_FILE)) {
    file_put_contents(DEPARTMENTS_FILE, json_encode([], JSON_PRETTY_PRINT));
}

if (!file_exists(GEOFENCE_FILE)) {
    file_put_contents(GEOFENCE_FILE, json_encode([
        'enabled' => false,
        'latitude' => null,
        'longitude' => null,
        'radius_meters' => 150,
        'updated_at' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT));
}

if (!file_exists(EXCUSES_FILE)) {
    file_put_contents(EXCUSES_FILE, json_encode([], JSON_PRETTY_PRINT));
}

if (!file_exists(EMPLOYEE_DOCUMENTS_FILE)) {
    file_put_contents(EMPLOYEE_DOCUMENTS_FILE, json_encode([], JSON_PRETTY_PRINT));
}

if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode([[
        'username' => ADMIN_USERNAME,
        'password_hash' => password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT),
        'role' => 'admin',
        'created_at' => date('Y-m-d H:i:s'),
    ]], JSON_PRETTY_PRINT));
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}
