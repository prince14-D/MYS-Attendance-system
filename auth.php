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

function roles(): array
{
    return ['admin', 'hr', 'supervisor', 'viewer'];
}

function read_users(): array
{
    return db()->query('SELECT username, password_hash, role, department_id, created_at FROM users ORDER BY username')->fetchAll();
}

function write_users(array $users): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pdo->exec('DELETE FROM users');

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, role, department_id, created_at)
             VALUES (:username, :password_hash, :role, :department_id, :created_at)'
        );

        foreach ($users as $user) {
            $stmt->execute([
                'username' => (string) $user['username'],
                'password_hash' => (string) $user['password_hash'],
                'role' => (string) ($user['role'] ?? 'viewer'),
                'department_id' => ($user['department_id'] ?? '') !== '' ? $user['department_id'] : null,
                'created_at' => (string) ($user['created_at'] ?? date('Y-m-d H:i:s')),
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function current_user_role(): string
{
    $role = (string) ($_SESSION['user_role'] ?? '');
    return in_array($role, roles(), true) ? $role : 'viewer';
}

function current_username(): string
{
    return (string) ($_SESSION['username'] ?? '');
}

function current_user_department_id(): string
{
    return (string) ($_SESSION['department_id'] ?? '');
}

// Roles whose dashboard/data views are locked to their assigned department.
function department_scoped_roles(): array
{
    return ['supervisor'];
}

function current_user_is_department_scoped(): bool
{
    return in_array(current_user_role(), department_scoped_roles(), true);
}

function require_roles(array $allowedRoles): void
{
    require_admin();
    if (!in_array(current_user_role(), $allowedRoles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function attempt_admin_login(string $username, string $password): bool
{
    foreach (read_users() as $user) {
        if (($user['username'] ?? '') === $username && password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['user_role'] = $user['role'] ?? 'viewer';
            $_SESSION['department_id'] = (string) ($user['department_id'] ?? '');
            return true;
        }
    }

    return false;
}

function create_system_user(string $username, string $password, string $role, string $departmentId = ''): array
{
    $username = trim($username);
    if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username) || strlen($password) < 8 || !in_array($role, roles(), true)) return ['ok' => false, 'message' => 'Use a 3–40 character username, an 8+ character password, and a valid role.'];
    $departmentId = normalize_department_id($departmentId);
    if ($departmentId !== '' && find_department($departmentId) === null) return ['ok' => false, 'message' => 'Select a registered department.'];
    $users = read_users(); foreach ($users as $user) { if (strcasecmp((string) ($user['username'] ?? ''), $username) === 0) return ['ok' => false, 'message' => 'That username already exists.']; }
    $users[] = ['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role, 'department_id' => $departmentId, 'created_at' => date('Y-m-d H:i:s')]; write_users($users); return ['ok' => true, 'message' => 'User created.'];
}

function update_system_user(string $username, string $role, string $newPassword = '', string $departmentId = ''): array
{
    $departmentId = normalize_department_id($departmentId);
    if ($departmentId !== '' && find_department($departmentId) === null) return ['ok' => false, 'message' => 'Select a registered department.'];
    $users = read_users(); foreach ($users as &$user) { if (($user['username'] ?? '') === $username) { if (!in_array($role, roles(), true)) return ['ok' => false, 'message' => 'Invalid role.']; if ($username === current_username() && $role !== 'admin') return ['ok' => false, 'message' => 'You cannot remove your own Admin role.']; $user['role'] = $role; $user['department_id'] = $departmentId; if ($newPassword !== '') { if (strlen($newPassword) < 8) return ['ok' => false, 'message' => 'New passwords must be at least 8 characters.' ]; $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT); } write_users($users); return ['ok' => true, 'message' => 'User updated.']; } } return ['ok' => false, 'message' => 'User not found.'];
}

function delete_system_user(string $username): array
{
    if ($username === current_username()) return ['ok' => false, 'message' => 'You cannot delete your own account.'];
    $users = read_users(); $remaining = array_values(array_filter($users, static fn (array $user): bool => ($user['username'] ?? '') !== $username)); if (count($remaining) === count($users)) return ['ok' => false, 'message' => 'User not found.']; if (count(array_filter($remaining, static fn (array $user): bool => ($user['role'] ?? '') === 'admin')) === 0) return ['ok' => false, 'message' => 'At least one Admin account is required.']; write_users($remaining); return ['ok' => true, 'message' => 'User deleted.'];
}
