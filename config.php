<?php
declare(strict_types=1);

date_default_timezone_set('Africa/Monrovia');

const APP_NAME = 'Ministry of Youth & Sports Attendance System';
const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'admin123';
const STORAGE_DIR = __DIR__ . '/storage';
const SESSION_DIR = STORAGE_DIR . '/sessions';
const PHOTOS_DIR = STORAGE_DIR . '/photos';
const EMPLOYEE_DOCUMENTS_DIR = STORAGE_DIR . '/employee_documents';
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

$mysqlConfigFile = __DIR__ . '/database/config.mysql.php';

if (!file_exists($mysqlConfigFile)) {
    throw new RuntimeException(
        'MySQL configuration missing. Copy database/config.mysql.php.example to ' .
        'database/config.mysql.php, set your credentials, and import database/schema.sql.'
    );
}

require_once $mysqlConfigFile;

function db(): PDO
{
    return mysql_connection();
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
