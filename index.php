<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

$result = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = employee_attendance_action($_POST['employee_number'] ?? '', $_POST['action'] ?? '');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <main class="page">
        <header class="topbar">
            <div class="brand"><?= h(APP_NAME) ?></div>
            <nav class="nav-links" aria-label="Main navigation">
                <a href="admin.php">Admin Login</a>
            </nav>
        </header>

        <section class="hero">
            <div class="clock-panel">
                <div class="time-area">
                    <div class="date-label" id="currentDate"><?= h(date('l, F j, Y')) ?></div>
                    <div class="live-time" id="currentTime"><?= h(date('H:i:s')) ?></div>
                    <div class="time-note">Employee attendance clock</div>
                </div>

                <div class="form-area">
                    <?php if ($result !== null && $result['ok']): ?>
                        <div class="confirmation-screen">
                            <div class="confirmation-label"><?= h($result['action'] === 'clock_in' ? 'Clock In' : 'Clock Out') ?></div>
                            <h1><?= h($result['title']) ?></h1>
                            <p><?= h($result['body']) ?></p>
                            <dl>
                                <div>
                                    <dt>Employee</dt>
                                    <dd><?= h($result['employee_number']) ?></dd>
                                </div>
                                <div>
                                    <dt>Time</dt>
                                    <dd><?= h($result['time']) ?></dd>
                                </div>
                            </dl>
                            <a class="button primary full-button" href="index.php">Done</a>
                        </div>
                    <?php else: ?>
                        <h1>Clock In / Out</h1>
                        <p class="muted">Enter your employee number, then choose the correct action.</p>

                        <?php if ($result !== null): ?>
                            <div class="alert error">
                                <?= h($result['message']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" autocomplete="off">
                            <label for="employee_number">Employee Number</label>
                            <input id="employee_number" name="employee_number" type="text" inputmode="text" placeholder="Example: EMP001" required autofocus>

                            <div class="actions">
                                <button class="button primary" type="submit" name="action" value="clock_in">Clock In</button>
                                <button class="button secondary" type="submit" name="action" value="clock_out">Clock Out</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script>
        const dateNode = document.getElementById('currentDate');
        const timeNode = document.getElementById('currentTime');

        function refreshClock() {
            const now = new Date();
            dateNode.textContent = now.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            timeNode.textContent = now.toLocaleTimeString(undefined, { hour12: false });
        }

        refreshClock();
        setInterval(refreshClock, 1000);
    </script>
</body>
</html>
