<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

require_admin();

$selectedDate = $_GET['date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$records = attendance_for_date($selectedDate);
$dates = all_attendance_dates();

if (!in_array($selectedDate, $dates, true)) {
    array_unshift($dates, $selectedDate);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Records - <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <main class="page">
        <header class="topbar">
            <div class="brand"><?= h(APP_NAME) ?></div>
            <nav class="nav-links" aria-label="Main navigation">
                <a href="index.php">Clock Screen</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <section class="content">
            <div class="panel">
                <div class="toolbar">
                    <div>
                        <h1>Daily Attendance</h1>
                        <p class="muted">Records for <?= h($selectedDate) ?></p>
                    </div>

                    <form method="get">
                        <div>
                            <label for="date">Date</label>
                            <input id="date" name="date" type="date" value="<?= h($selectedDate) ?>">
                        </div>
                        <button class="button primary" type="submit">View</button>
                    </form>
                </div>

                <div class="toolbar">
                    <div class="muted"><?= count($records) ?> employee record<?= count($records) === 1 ? '' : 's' ?></div>
                    <div class="export-links">
                        <a class="link-button" href="export.php?format=csv&date=<?= h(urlencode($selectedDate)) ?>">CSV</a>
                        <a class="link-button" href="export.php?format=xls&date=<?= h(urlencode($selectedDate)) ?>">Excel</a>
                        <a class="link-button" href="export.php?format=pdf&date=<?= h(urlencode($selectedDate)) ?>">PDF</a>
                    </div>
                </div>

                <?php if (count($records) === 0): ?>
                    <div class="empty">No attendance records found for this date.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee Number</th>
                                    <th>Date</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>Worked Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td><?= h($record['employee_number']) ?></td>
                                        <td><?= h($record['date']) ?></td>
                                        <td><?= h($record['clock_in'] ?: '-') ?></td>
                                        <td><?= h($record['clock_out'] ?: '-') ?></td>
                                        <td><?= h(worked_hours($record) ?: '-') ?></td>
                                        <td>
                                            <span class="badge <?= $record['status'] === 'Complete' ? 'complete' : 'incomplete' ?>">
                                                <?= h($record['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
