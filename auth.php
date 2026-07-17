<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_save_path(SESSION_DIR);
session_start();

function admin_logged_in(): bool
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect('admin.php');
    }
}

function attempt_admin_login(string $username, string $password): bool
{
    if (hash_equals(ADMIN_USERNAME, $username) && hash_equals(ADMIN_PASSWORD, $password)) {
        $_SESSION['admin_logged_in'] = true;
        return true;
    }

    return false;
}
