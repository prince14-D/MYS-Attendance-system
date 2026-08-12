<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$error = '';

if (admin_logged_in()) {
    redirect('overview.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (attempt_admin_login($username, $password)) {
        redirect('overview.php');
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <main class="page">
        <header class="topbar">
            <div class="brand-lockup">
                <img class="brand-mark" src="assets/app-icon.svg" alt="" aria-hidden="true">
                <div>
                    <div class="brand-kicker">Ministry of Youth & Sports</div>
                    <div class="brand"><?= h(APP_NAME) ?></div>
                </div>
            </div>
            <nav class="nav-links" aria-label="Main navigation">
                <a href="index.php">Clock Screen</a>
            </nav>
        </header>

        <section class="hero admin-login-hero">
            <div class="panel admin-login-panel">
                <div class="admin-login-head">
                    <span class="eyebrow">Secure Access</span>
                    <h1>Admin Login</h1>
                    <p class="muted">Sign in to manage attendance records, reports, and exports.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert error"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="post" class="stacked-form admin-login-form">
                    <label for="username">Username</label>
                    <input class="form-control" id="username" name="username" type="text" required autofocus>

                    <label for="password">Password</label>
                    <input class="form-control" id="password" name="password" type="password" required>

                    <div class="actions admin-login-actions">
                        <button class="button primary" type="submit">Login</button>
                        <a class="button secondary" href="index.php">Back</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
