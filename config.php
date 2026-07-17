<?php
declare(strict_types=1);

date_default_timezone_set('Africa/Monrovia');

const APP_NAME = 'Ministry of Youth & Sports Attendance System';
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'admin123';
const STORAGE_DIR = __DIR__ . '/storage';
const SESSION_DIR = STORAGE_DIR . '/sessions';
const ATTENDANCE_FILE = STORAGE_DIR . '/attendance.json';

if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0775, true);
}

if (!is_dir(SESSION_DIR)) {
    mkdir(SESSION_DIR, 0775, true);
}

if (!file_exists(ATTENDANCE_FILE)) {
    file_put_contents(ATTENDANCE_FILE, json_encode([], JSON_PRETTY_PRINT));
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
