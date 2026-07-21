<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

require_admin();

$registrationResult = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $adminAction = $_POST['admin_action'] ?? '';

    if ($adminAction === 'create_department') {
        $registrationResult = register_department($_POST['department_name'] ?? '');
    } elseif ($adminAction === 'delete_employee') {
        $registrationResult = delete_employee($_POST['employee_number'] ?? '');
    } else {
        $registrationResult = register_employee(
            $_POST['employee_number'] ?? '',
            $_POST['employee_name'] ?? '',
            $_POST['department_id'] ?? ''
        );
    }
}

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedDepartment = normalize_department_id($_GET['department'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$selectedDepartmentData = $selectedDepartment !== '' ? find_department($selectedDepartment) : null;

if ($selectedDepartment !== '' && $selectedDepartmentData === null) {
    $selectedDepartment = '';
}

$records = attendance_for_date($selectedDate, $selectedDepartment);
$dates = all_attendance_dates();
$employees = all_employees();
$departments = all_departments();

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
                        <p class="muted">
                            Records for <?= h($selectedDate) ?>
                            <?= $selectedDepartmentData ? ' - ' . h($selectedDepartmentData['department_name']) : '' ?>
                        </p>
                    </div>

                    <form method="get">
                        <div>
                            <label for="date">Date</label>
                            <input id="date" name="date" type="date" value="<?= h($selectedDate) ?>">
                        </div>
                        <div>
                            <label for="department">Department</label>
                            <select id="department" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= h($department['department_id']) ?>" <?= $selectedDepartment === $department['department_id'] ? 'selected' : '' ?>>
                                        <?= h($department['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="button primary" type="submit">View</button>
                        <button class="button secondary" type="button" onclick="window.print()">Print</button>
                    </form>
                </div>

                <div class="admin-grid">
                    <section class="admin-box">
                        <h2>Create Department</h2>
                        <p class="muted">Create departments before assigning employees.</p>

                        <?php if ($registrationResult !== null): ?>
                            <div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>">
                                <?= h($registrationResult['message']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="stacked-form">
                            <input type="hidden" name="admin_action" value="create_department">
                            <label for="department_name">Department Name</label>
                            <input id="department_name" name="department_name" type="text" placeholder="Example: Administration" required>
                            <button class="button secondary full-button" type="submit">Save Department</button>
                        </form>
                    </section>

                    <section class="admin-box">
                        <h2>Register Employee</h2>
                        <p class="muted">Add the employee name and number before they use the clock screen.</p>

                        <form method="post" class="stacked-form">
                            <input type="hidden" name="admin_action" value="register_employee">
                            <label for="register_employee_number">Employee Number</label>
                            <input id="register_employee_number" name="employee_number" type="text" placeholder="Example: EMP001" required>

                            <label for="register_employee_name">Employee Name</label>
                            <input id="register_employee_name" name="employee_name" type="text" placeholder="Example: Mary Johnson" required>

                            <label for="register_department_id">Department</label>
                            <select id="register_department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= h($department['department_id']) ?>"><?= h($department['department_name']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <button class="button primary full-button" type="submit" <?= count($departments) === 0 ? 'disabled' : '' ?>>Save Employee</button>
                        </form>
                    </section>

                    <section class="admin-box">
                        <div class="toolbar compact-toolbar">
                            <div>
                                <h2>Registered Employees</h2>
                                <p class="muted"><?= count($employees) ?> employee<?= count($employees) === 1 ? '' : 's' ?></p>
                            </div>
                        </div>

                        <?php if (count($employees) === 0): ?>
                            <div class="empty small-empty">No employees registered yet.</div>
                        <?php else: ?>
                            <div class="mini-table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Employee Number</th>
                                            <th>Name</th>
                                            <th>Department</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $employee): ?>
                                            <tr>
                                                <td><?= h($employee['employee_number']) ?></td>
                                                <td><?= h($employee['employee_name']) ?></td>
                                                <td><?= h($employee['department_name'] ?? 'Unassigned') ?></td>
                                                <td>
                                                    <form method="post" class="inline-form" onsubmit="return confirm('Delete this employee? Attendance history will remain.');">
                                                        <input type="hidden" name="admin_action" value="delete_employee">
                                                        <input type="hidden" name="employee_number" value="<?= h($employee['employee_number']) ?>">
                                                        <button class="link-button danger-link" type="submit">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <div class="toolbar">
                    <div class="muted"><?= count($records) ?> employee record<?= count($records) === 1 ? '' : 's' ?></div>
                    <div class="export-links">
                        <a class="link-button" href="export.php?format=csv&date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">CSV</a>
                        <a class="link-button" href="export.php?format=xls&date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">Excel</a>
                        <a class="link-button" href="export.php?format=pdf&date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">PDF</a>
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
                                    <th>Employee Name</th>
                                    <th>Department</th>
                                    <th>Date</th>
                                    <th>Clock In</th>
                                    <th>Photo</th>
                                    <th>Clock Out</th>
                                    <th>Worked Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td><?= h($record['employee_number']) ?></td>
                                        <td><?= h($record['employee_name'] ?? '-') ?></td>
                                        <td><?= h($record['department_name'] ?? 'Unassigned') ?></td>
                                        <td><?= h($record['date']) ?></td>
                                        <td><?= h($record['clock_in'] ?: '-') ?></td>
                                        <td>
                                            <?php if (($record['clock_in_photo'] ?? '') !== ''): ?>
                                                <a class="photo-link" href="<?= h($record['clock_in_photo']) ?>" target="_blank" rel="noopener">
                                                    <img src="<?= h($record['clock_in_photo']) ?>" alt="Clock-in photo for <?= h($record['employee_number']) ?>">
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
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
