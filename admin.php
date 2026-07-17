<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$error = '';

if (admin_logged_in()) {
    redirect('dashboard.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (attempt_admin_login($username, $password)) {
        redirect('dashboard.php');
    }

    $error = 'Invalid admin username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <main class="page">
        <header class="topbar">
            <div class="brand"><?= h(APP_NAME) ?></div>
            <nav class="nav-links" aria-label="Main navigation">
                <a href="index.php">Clock Screen</a>
            </nav>
        </header>

        <section class="hero">
            <div class="panel">
                <h1>Admin Login</h1>
                <p class="muted">Sign in to view and export daily attendance records.</p>

                <?php if ($error !== ''): ?>
                    <div class="alert error"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" required autofocus>

                    <div style="height: 14px;"></div>

                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>

                    <div class="actions">
                        <button class="button primary" type="submit">Login</button>
                        <a class="button secondary" href="index.php">Back</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
