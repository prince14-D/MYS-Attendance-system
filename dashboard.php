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
    } elseif ($adminAction === 'update_department') {
        $registrationResult = update_department($_POST['department_id'] ?? '', $_POST['department_name'] ?? '');
    } elseif ($adminAction === 'delete_department') {
        $registrationResult = delete_department($_POST['department_id'] ?? '');
    } elseif ($adminAction === 'delete_employee') {
        $registrationResult = delete_employee($_POST['employee_number'] ?? '');
    } elseif ($adminAction === 'update_employee') {
        $registrationResult = update_employee_record(
            $_POST['original_employee_number'] ?? '',
            $_POST['employee_number'] ?? '',
            $_POST['employee_name'] ?? '',
            $_POST['department_id'] ?? '',
            $_POST['position'] ?? ''
        );
    } elseif ($adminAction === 'import_employees') {
        $registrationResult = import_employees_from_upload($_FILES['employee_file'] ?? []);
    } elseif ($adminAction === 'import_attendance') {
        $registrationResult = import_attendance_from_upload($_FILES['attendance_file'] ?? []);
    } elseif ($adminAction === 'update_attendance') {
        $registrationResult = update_attendance_record(
            $_POST['original_date'] ?? '',
            $_POST['original_employee_number'] ?? '',
            $_POST['employee_number'] ?? '',
            $_POST['employee_name'] ?? '',
            $_POST['position'] ?? '',
            $_POST['department_id'] ?? '',
            $_POST['date'] ?? '',
            $_POST['clock_in'] ?? '',
            $_POST['clock_out'] ?? ''
        );
    } elseif ($adminAction === 'delete_attendance') {
        $registrationResult = delete_attendance_record($_POST['date'] ?? '', $_POST['employee_number'] ?? '');
    } elseif ($adminAction === 'register_employee') {
        $registrationResult = register_employee(
            $_POST['employee_number'] ?? '',
            $_POST['employee_name'] ?? '',
            $_POST['department_id'] ?? '',
            $_POST['position'] ?? ''
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
$totalWorkedMinutes = 0;
$completeWorkedMinutes = 0;

foreach ($records as $record) {
    $worked = worked_hours($record);

    if ($worked === '') {
        continue;
    }

    [$hours, $minutes] = array_map('intval', explode(':', $worked));
    $minutesWorked = ($hours * 60) + $minutes;
    $totalWorkedMinutes += $minutesWorked;

    if (($record['status'] ?? '') === 'Complete') {
        $completeWorkedMinutes += $minutesWorked;
    }
}

$completeRecords = count(array_filter($records, static fn (array $record): bool => ($record['status'] ?? '') === 'Complete'));
$incompleteRecords = count($records) - $completeRecords;
$clockedInRecords = count(array_filter($records, static fn (array $record): bool => ($record['clock_in'] ?? '') !== ''));
$activeFilterLabel = $selectedDepartmentData['department_name'] ?? 'All Departments';
$employeesByNumber = [];

foreach ($employees as $employee) {
    $employeeNumber = normalize_employee_number((string) ($employee['employee_number'] ?? ''));

    if ($employeeNumber !== '') {
        $employeesByNumber[$employeeNumber] = $employee;
    }
}

$safe_form_id = static function (string $value): string {
    $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value) ?? '';
    $safe = trim($safe, '_');

    return $safe !== '' ? $safe : 'row';
};

$printRecordGroups = [];

foreach ($records as $record) {
    $employeeNumber = normalize_employee_number((string) ($record['employee_number'] ?? ''));
    $employee = $employeeNumber !== '' ? ($employeesByNumber[$employeeNumber] ?? null) : null;
    $departmentName = trim((string) ($record['department_name'] ?? ($employee['department_name'] ?? 'Unassigned')));

    if ($departmentName === '') {
        $departmentName = 'Unassigned';
    }

    $printRecordGroups[$departmentName][] = [
        'employee_number' => $employeeNumber !== '' ? $employeeNumber : '-',
        'employee_name' => trim((string) ($record['employee_name'] ?? ($employee['employee_name'] ?? ''))),
        'clock_in' => (string) ($record['clock_in'] ?? ''),
        'clock_out' => (string) ($record['clock_out'] ?? ''),
        'worked_hours' => worked_hours($record),
        'status' => (string) ($record['status'] ?? 'Pending'),
        'department_name' => $departmentName,
    ];
}

$averageWorkedMinutes = $completeRecords > 0 ? (int) floor($completeWorkedMinutes / $completeRecords) : 0;

$formatWorkedMinutes = static function (int $minutes): string {
    if ($minutes <= 0) {
        return '00:00';
    }

    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    return sprintf('%02d:%02d', $hours, $remainingMinutes);
};

$profileReviews = array_slice($records, 0, 12);
$profileReviewData = [];

foreach ($profileReviews as $record) {
    $employeeName = trim((string) ($record['employee_name'] ?? 'Unknown Employee'));
    $employeeNumber = trim((string) ($record['employee_number'] ?? '-'));
    $departmentName = trim((string) ($record['department_name'] ?? 'Unassigned'));
    $clockIn = trim((string) ($record['clock_in'] ?? ''));
    $clockOut = trim((string) ($record['clock_out'] ?? ''));
    $status = trim((string) ($record['status'] ?? ''));

    if ($status === '') {
        $status = $clockIn !== '' && $clockOut !== '' ? 'Complete' : 'Incomplete';
    }

    $profileReviewData[] = [
        'employee_name' => $employeeName !== '' ? $employeeName : 'Unknown Employee',
        'employee_number' => $employeeNumber !== '' ? $employeeNumber : '-',
        'department_name' => $departmentName !== '' ? $departmentName : 'Unassigned',
        'position' => trim((string) ($record['position'] ?? '')),
        'clock_in' => $clockIn,
        'clock_out' => $clockOut,
        'worked_hours' => worked_hours($record),
        'status' => $status,
        'clock_in_photo' => trim((string) ($record['clock_in_photo'] ?? '')),
    ];
}

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
            <div class="brand-lockup">
                <img class="brand-mark" src="assets/app-icon.svg" alt="" aria-hidden="true">
                <div>
                    <div class="brand-kicker">Ministry of Youth & Sports</div>
                    <div class="brand"><?= h(APP_NAME) ?></div>
                </div>
            </div>
            <nav class="nav-links" aria-label="Main navigation">
                <a href="index.php">Clock Screen</a>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <section class="content admin-dashboard">
            <div class="dashboard-hero panel">
                <div class="dashboard-title">
                    <span class="eyebrow">Admin Dashboard</span>
                    <h1>Daily Attendance</h1>
                    <p class="muted">
                        Viewing <?= h($activeFilterLabel) ?> for <?= h($selectedDate) ?>.
                    </p>
                </div>

                <form method="get" class="filter-panel">
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
                    <button class="button secondary" type="button" data-print-mode="attendance">Print</button>
                </form>
            </div>

            <div class="stats-grid" aria-label="Attendance summary">
                <div class="stat-card">
                    <span>Today Records</span>
                    <strong><?= count($records) ?></strong>
                    <small><?= h($activeFilterLabel) ?></small>
                </div>
                <div class="stat-card">
                    <span>Clocked In</span>
                    <strong><?= $clockedInRecords ?></strong>
                    <small><?= count($employees) ?> registered employees</small>
                </div>
                <div class="stat-card">
                    <span>Complete</span>
                    <strong><?= $completeRecords ?></strong>
                    <small><?= $incompleteRecords ?> incomplete</small>
                </div>
                <div class="stat-card">
                    <span>Total Worked</span>
                    <strong><?= h($formatWorkedMinutes($totalWorkedMinutes)) ?></strong>
                    <small>Across today's records</small>
                </div>
                <div class="stat-card">
                    <span>Average Time</span>
                    <strong><?= h($formatWorkedMinutes($averageWorkedMinutes)) ?></strong>
                    <small>Per completed shift</small>
                </div>
                <div class="stat-card">
                    <span>Departments</span>
                    <strong><?= count($departments) ?></strong>
                    <small>Available filters</small>
                </div>
            </div>

            <?php if ($registrationResult !== null): ?>
                <div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>">
                    <?= h($registrationResult['message']) ?>
                </div>
            <?php endif; ?>

            <section class="print-report" aria-label="Printable attendance report">
                <div class="print-report-header">
                    <div>
                        <span class="eyebrow">Printable Report</span>
                        <h2><?= h(APP_NAME) ?></h2>
                        <p>Attendance for <?= h($selectedDate) ?> - <?= h($activeFilterLabel) ?></p>
                    </div>
                    <div class="print-report-meta">
                        <div>
                            <span>Date</span>
                            <strong><?= h($selectedDate) ?></strong>
                        </div>
                        <div>
                            <span>Department</span>
                            <strong><?= h($activeFilterLabel) ?></strong>
                        </div>
                        <div>
                            <span>Total Records</span>
                            <strong><?= count($records) ?></strong>
                        </div>
                    </div>
                </div>

                <?php if (count($records) === 0): ?>
                    <div class="empty">No attendance records found for this date.</div>
                <?php else: ?>
                    <?php foreach ($printRecordGroups as $departmentName => $departmentRecords): ?>
                        <section class="print-department-block">
                            <div class="print-department-heading">
                                <h3><?= h($departmentName) ?></h3>
                                <span><?= count($departmentRecords) ?> record<?= count($departmentRecords) === 1 ? '' : 's' ?></span>
                            </div>

                            <table class="print-table">
                                <thead>
                                    <tr>
                                        <th>Employee Number</th>
                                        <th>Employee Name</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Worked Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departmentRecords as $record): ?>
                                        <tr>
                                            <td><?= h($record['employee_number']) ?></td>
                                            <td><?= h($record['employee_name'] !== '' ? $record['employee_name'] : '-') ?></td>
                                            <td><?= h($record['clock_in'] !== '' ? $record['clock_in'] : '-') ?></td>
                                            <td><?= h($record['clock_out'] !== '' ? $record['clock_out'] : '-') ?></td>
                                            <td><?= h($record['worked_hours'] !== '' ? $record['worked_hours'] : '-') ?></td>
                                            <td><?= h($record['status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="employee-print-report" aria-label="Printable employee directory">
                <div class="print-report-header">
                    <div>
                        <span class="eyebrow">Employee Directory</span>
                        <h2><?= h(APP_NAME) ?></h2>
                        <p>Total Employees: <?= count($employees) ?></p>
                    </div>
                    <div class="print-report-meta">
                        <div>
                            <span>Total Employees</span>
                            <strong><?= count($employees) ?></strong>
                        </div>
                        <div>
                            <span>Departments</span>
                            <strong><?= count($departments) ?></strong>
                        </div>
                        <div>
                            <span>Printed</span>
                            <strong><?= h(date('Y-m-d')) ?></strong>
                        </div>
                    </div>
                </div>

                <?php if (count($employees) === 0): ?>
                    <div class="empty">No employees registered yet.</div>
                <?php else: ?>
                    <table class="print-table employee-directory-print-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee Number</th>
                                <th>Employee Name</th>
                                <th>Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $index => $employee): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= h($employee['employee_number']) ?></td>
                                    <td><?= h($employee['employee_name']) ?></td>
                                    <td><?= h($employee['position'] !== '' ? $employee['position'] : '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <div class="dashboard-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Setup</span>
                        <h2>Directory Management</h2>
                    </div>
                    <p class="muted">Create departments, register employees, and import lists.</p>
                </div>

                <div class="admin-grid">
                    <section class="admin-box">
                        <h2>Create Department</h2>
                        <p class="muted">Create departments before assigning employees.</p>

                        <form method="post" class="stacked-form">
                            <input type="hidden" name="admin_action" value="create_department">
                            <label for="department_name">Department Name</label>
                            <input id="department_name" name="department_name" type="text" placeholder="Example: Administration" required>
                            <button class="button secondary full-button" type="submit">Save Department</button>
                        </form>
                    </section>

                    <section class="admin-box primary-admin-box">
                        <h2>Register Employee</h2>
                        <p class="muted">Add the employee name and number before they use the clock screen.</p>

                        <form method="post" class="stacked-form">
                            <input type="hidden" name="admin_action" value="register_employee">
                            <label for="register_employee_number">Employee Number</label>
                            <input id="register_employee_number" name="employee_number" type="text" placeholder="Example: EMP001" required>

                            <label for="register_employee_name">Employee Name</label>
                            <input id="register_employee_name" name="employee_name" type="text" placeholder="Example: Mary Johnson" required>

                            <label for="register_position">Position</label>
                            <input id="register_position" name="position" type="text" placeholder="Example: Program Officer">

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

                    <section class="admin-box import-box">
                        <h2>Import Employees</h2>
                        <p class="muted">Upload Employee Number, Employee Name, Position, and Department columns.</p>

                        <form method="post" enctype="multipart/form-data" class="stacked-form">
                            <input type="hidden" name="admin_action" value="import_employees">
                            <label for="employee_file">Employee Register</label>
                            <input id="employee_file" name="employee_file" type="file" accept=".xlsx,.xls,.csv" required>
                            <button class="button secondary full-button" type="submit">Import Employees</button>
                        </form>
                    </section>

                    <section class="admin-box import-box">
                        <h2>Import Attendance</h2>
                        <p class="muted">Upload Employee Number, Date, Position, Department, Clock In, and Clock Out columns.</p>

                        <form method="post" enctype="multipart/form-data" class="stacked-form">
                            <input type="hidden" name="admin_action" value="import_attendance">
                            <label for="attendance_file">Excel Sheet</label>
                            <input id="attendance_file" name="attendance_file" type="file" accept=".xlsx,.xls,.csv" required>
                            <button class="button secondary full-button" type="submit">Import Records</button>
                        </form>
                    </section>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="admin-grid directory-grid">
                    <section class="admin-box wide-admin-box">
                        <div class="toolbar compact-toolbar">
                            <div>
                                <span class="eyebrow">Departments</span>
                                <h2>Department List</h2>
                                <p class="muted"><?= count($departments) ?> department<?= count($departments) === 1 ? '' : 's' ?></p>
                            </div>
                        </div>

                        <?php if (count($departments) === 0): ?>
                            <div class="empty small-empty">No departments created yet.</div>
                        <?php else: ?>
                            <div class="mini-table-wrap">
                                <table class="editable-table">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departments as $department): ?>
                                            <tr>
                                                <td>
                                                    <form method="post" class="row-form">
                                                        <input type="hidden" name="admin_action" value="update_department">
                                                        <input type="hidden" name="department_id" value="<?= h($department['department_id']) ?>">
                                                        <input name="department_name" type="text" value="<?= h($department['department_name']) ?>" required>
                                                        <button class="link-button small-button" type="submit">Save</button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <form method="post" class="inline-form" onsubmit="return confirm('Delete this department? Assigned employees will become unassigned.');">
                                                        <input type="hidden" name="admin_action" value="delete_department">
                                                        <input type="hidden" name="department_id" value="<?= h($department['department_id']) ?>">
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

                    <section class="admin-box wide-admin-box employees-box">
                        <div class="toolbar compact-toolbar">
                            <div>
                                <span class="eyebrow">Employees</span>
                                <h2>Registered Employees</h2>
                                <p class="muted"><?= count($employees) ?> employee<?= count($employees) === 1 ? '' : 's' ?></p>
                            </div>
                            <button class="button secondary" type="button" data-print-mode="employees">Print Employees</button>
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
                                            <th>Position</th>
                                            <th>Department</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $employeeIndex => $employee): ?>
                                            <tr>
                                                <?php $employeeFormId = 'employee_' . $safe_form_id((string) $employee['employee_number']) . '_' . $employeeIndex . '_edit'; ?>
                                                <td>
                                                    <form id="<?= h($employeeFormId) ?>" method="post" class="inline-form">
                                                        <input type="hidden" name="admin_action" value="update_employee">
                                                        <input type="hidden" name="original_employee_number" value="<?= h($employee['employee_number']) ?>">
                                                    </form>
                                                    <input form="<?= h($employeeFormId) ?>" name="employee_number" type="text" value="<?= h($employee['employee_number']) ?>" required>
                                                </td>
                                                <td><input form="<?= h($employeeFormId) ?>" name="employee_name" type="text" value="<?= h($employee['employee_name']) ?>" required></td>
                                                <td><input form="<?= h($employeeFormId) ?>" name="position" type="text" value="<?= h($employee['position'] ?? '') ?>"></td>
                                                <td>
                                                    <select form="<?= h($employeeFormId) ?>" name="department_id">
                                                        <option value="">Unassigned</option>
                                                        <?php foreach ($departments as $department): ?>
                                                            <option value="<?= h($department['department_id']) ?>" <?= ($employee['department_id'] ?? '') === $department['department_id'] ? 'selected' : '' ?>>
                                                                <?= h($department['department_name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td class="action-cell">
                                                    <button form="<?= h($employeeFormId) ?>" class="link-button small-button" type="submit">Save</button>
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
            </div>

            <div class="dashboard-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Review</span>
                        <h2>Clock-In Profiles</h2>
                    </div>
                    <p class="muted">Select an employee name to preview the full attendance profile in an overlay.</p>
                </div>

                <?php if (count($profileReviews) === 0): ?>
                    <div class="empty">No attendance records found for this date.</div>
                <?php else: ?>
                    <div class="profile-review-shell">
                        <div class="profile-name-list" role="list" aria-label="Profile review list">
                            <?php foreach ($profileReviewData as $index => $profile): ?>
                                <button
                                    class="profile-name-button"
                                    type="button"
                                    data-profile-index="<?= $index ?>"
                                    role="listitem"
                                >
                                    <?= h($profile['employee_name']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="muted profile-review-note">Showing <?= count($profileReviewData) ?> recent record<?= count($profileReviewData) === 1 ? '' : 's' ?> for this date.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="records-panel panel">
                <div class="toolbar records-toolbar">
                    <div>
                        <span class="eyebrow">Records</span>
                        <h2>Attendance Log</h2>
                        <p class="muted"><?= count($records) ?> employee record<?= count($records) === 1 ? '' : 's' ?> for <?= h($selectedDate) ?></p>
                    </div>
                    <div class="records-filter-bar" aria-label="Attendance filters">
                        <div class="records-filter-item search-item">
                            <label for="recordSearch">Quick Search</label>
                            <input id="recordSearch" type="search" placeholder="Search number, name, position, department">
                        </div>
                        <div class="records-filter-item status-item">
                            <label for="recordStatusFilter">Status</label>
                            <select id="recordStatusFilter">
                                <option value="all">All</option>
                                <option value="complete">Complete</option>
                                <option value="incomplete">Incomplete</option>
                            </select>
                        </div>
                    </div>
                    <div class="export-links">
                        <a class="link-button" href="export.php?format=csv&date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">CSV</a>
                        <a class="link-button" href="export.php?format=xls&date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">Excel</a>
                        <a class="link-button" href="export.php?format=pdf&date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">PDF</a>
                    </div>
                </div>

                <?php if (count($records) === 0): ?>
                    <div class="empty">No attendance records found for this date.</div>
                <?php else: ?>
                    <div class="records-filter-summary" id="recordsFilterSummary" role="status" aria-live="polite"></div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee Number</th>
                                    <th>Employee Name</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Date</th>
                                    <th>Clock In</th>
                                    <th>Photo</th>
                                    <th>Clock Out</th>
                                    <th>Worked Hours</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $recordIndex => $record): ?>
                                    <?php
                                        $rowSearch = implode(' ', [
                                            (string) ($record['employee_number'] ?? ''),
                                            (string) ($record['employee_name'] ?? ''),
                                            (string) ($record['position'] ?? ''),
                                            (string) ($record['department_name'] ?? ''),
                                            (string) ($record['date'] ?? ''),
                                            (string) ($record['clock_in'] ?? ''),
                                            (string) ($record['clock_out'] ?? ''),
                                            (string) ($record['status'] ?? ''),
                                        ]);
                                    ?>
                                    <tr data-record-row data-record-search="<?= h(strtolower($rowSearch)) ?>" data-record-status="<?= h(strtolower((string) ($record['status'] ?? 'incomplete'))) ?>">
                                        <?php $attendanceFormId = 'attendance_' . $safe_form_id((string) $record['date'] . '_' . (string) $record['employee_number']) . '_' . $recordIndex; ?>
                                        <td>
                                            <form id="<?= $attendanceFormId ?>_edit" method="post" class="inline-form">
                                                <input type="hidden" name="admin_action" value="update_attendance">
                                                <input type="hidden" name="original_date" value="<?= h($record['date']) ?>">
                                                <input type="hidden" name="original_employee_number" value="<?= h($record['employee_number']) ?>">
                                            </form>
                                            <input form="<?= $attendanceFormId ?>_edit" name="employee_number" type="text" value="<?= h($record['employee_number']) ?>" required>
                                        </td>
                                        <td><input form="<?= $attendanceFormId ?>_edit" name="employee_name" type="text" value="<?= h($record['employee_name'] ?? '') ?>" required></td>
                                        <td><input form="<?= $attendanceFormId ?>_edit" name="position" type="text" value="<?= h($record['position'] ?? '') ?>"></td>
                                        <td>
                                            <select form="<?= $attendanceFormId ?>_edit" name="department_id">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($departments as $department): ?>
                                                    <option value="<?= h($department['department_id']) ?>" <?= ($record['department_id'] ?? '') === $department['department_id'] ? 'selected' : '' ?>>
                                                        <?= h($department['department_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input form="<?= $attendanceFormId ?>_edit" name="date" type="date" value="<?= h($record['date']) ?>" required></td>
                                        <td><input form="<?= $attendanceFormId ?>_edit" name="clock_in" type="time" step="1" value="<?= h($record['clock_in'] ?? '') ?>"></td>
                                        <td>
                                            <?php if (($record['clock_in_photo'] ?? '') !== ''): ?>
                                                <a class="photo-link" href="<?= h($record['clock_in_photo']) ?>" target="_blank" rel="noopener">
                                                    <img src="<?= h($record['clock_in_photo']) ?>" alt="Clock-in photo for <?= h($record['employee_number']) ?>">
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><input form="<?= $attendanceFormId ?>_edit" name="clock_out" type="time" step="1" value="<?= h($record['clock_out'] ?? '') ?>"></td>
                                        <td><?= h(worked_hours($record) ?: '-') ?></td>
                                        <td>
                                            <span class="badge <?= $record['status'] === 'Complete' ? 'complete' : 'incomplete' ?>">
                                                <?= h($record['status']) ?>
                                            </span>
                                        </td>
                                        <td class="action-cell">
                                            <button form="<?= $attendanceFormId ?>_edit" class="link-button small-button" type="submit">Save</button>
                                            <form method="post" class="inline-form" onsubmit="return confirm('Delete this attendance record?');">
                                                <input type="hidden" name="admin_action" value="delete_attendance">
                                                <input type="hidden" name="date" value="<?= h($record['date']) ?>">
                                                <input type="hidden" name="employee_number" value="<?= h($record['employee_number']) ?>">
                                                <button class="link-button danger-link" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="empty small-empty" id="recordsFilterEmpty" hidden>No records match your search/filter.</div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div class="profile-modal" id="profileModal" hidden>
        <div class="profile-modal-backdrop" data-profile-close="backdrop"></div>
        <article class="profile-modal-card" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
            <button class="profile-modal-close" type="button" id="profileModalClose" aria-label="Close profile preview">Close</button>

            <div class="profile-modal-head">
                <div class="profile-modal-avatar" id="profileModalAvatar"></div>
                <div>
                    <h3 id="profileModalTitle">Employee Profile</h3>
                    <p id="profileModalSubtitle">Attendance details</p>
                </div>
            </div>

            <dl class="profile-modal-metrics" id="profileModalMetrics"></dl>
            <div id="profileModalStatusWrap"></div>
        </article>
    </div>

    <script>
        const profileReviewData = <?= json_encode($profileReviewData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const profileModal = document.getElementById('profileModal');
        const profileModalClose = document.getElementById('profileModalClose');
        const profileModalAvatar = document.getElementById('profileModalAvatar');
        const profileModalTitle = document.getElementById('profileModalTitle');
        const profileModalSubtitle = document.getElementById('profileModalSubtitle');
        const profileModalMetrics = document.getElementById('profileModalMetrics');
        const profileModalStatusWrap = document.getElementById('profileModalStatusWrap');

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function profileInitials(name) {
            const parts = String(name).trim().split(/\s+/).filter(Boolean);

            if (parts.length === 0) {
                return 'NA';
            }

            if (parts.length === 1) {
                return parts[0].slice(0, 2).toUpperCase();
            }

            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }

        function closeProfileModal() {
            if (profileModal) {
                profileModal.hidden = true;
                document.body.classList.remove('modal-open');
            }
        }

        function openProfileModal(index) {
            const profile = profileReviewData[index];

            if (!profile || !profileModal) {
                return;
            }

            const statusClass = profile.status === 'Complete' ? 'complete' : 'incomplete';
            const avatarHtml = profile.clock_in_photo !== ''
                ? `<img src="${escapeHtml(profile.clock_in_photo)}" alt="Clock-in photo for ${escapeHtml(profile.employee_number)}">`
                : `<span>${escapeHtml(profileInitials(profile.employee_name))}</span>`;

            profileModalAvatar.innerHTML = avatarHtml;
            profileModalTitle.textContent = profile.employee_name;
            profileModalSubtitle.textContent = `${profile.employee_number} - ${profile.department_name}`;

            profileModalMetrics.innerHTML = `
                <div><dt>Position</dt><dd>${escapeHtml(profile.position || '-')}</dd></div>
                <div><dt>Clock In</dt><dd>${escapeHtml(profile.clock_in || '-')}</dd></div>
                <div><dt>Clock Out</dt><dd>${escapeHtml(profile.clock_out || '-')}</dd></div>
                <div><dt>Worked Time</dt><dd>${escapeHtml(profile.worked_hours || '-')}</dd></div>
            `;

            profileModalStatusWrap.innerHTML = `<span class="badge ${statusClass}">${escapeHtml(profile.status)}</span>`;
            profileModal.hidden = false;
            document.body.classList.add('modal-open');
        }

        document.querySelectorAll('.profile-name-button').forEach((button) => {
            button.addEventListener('click', () => {
                openProfileModal(Number(button.dataset.profileIndex));
            });
        });

        profileModalClose?.addEventListener('click', closeProfileModal);
        profileModal?.addEventListener('click', (event) => {
            if (event.target instanceof HTMLElement && event.target.dataset.profileClose === 'backdrop') {
                closeProfileModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && profileModal && !profileModal.hidden) {
                closeProfileModal();
            }
        });

        const recordSearch = document.getElementById('recordSearch');
        const recordStatusFilter = document.getElementById('recordStatusFilter');
        const recordsFilterSummary = document.getElementById('recordsFilterSummary');
        const recordsFilterEmpty = document.getElementById('recordsFilterEmpty');
        const recordRows = Array.from(document.querySelectorAll('[data-record-row]'));

        function applyRecordsFilter() {
            if (recordRows.length === 0) {
                return;
            }

            const searchValue = (recordSearch?.value || '').trim().toLowerCase();
            const statusValue = (recordStatusFilter?.value || 'all').toLowerCase();
            let visibleCount = 0;

            recordRows.forEach((row) => {
                const haystack = String(row.getAttribute('data-record-search') || '');
                const status = String(row.getAttribute('data-record-status') || 'incomplete');
                const searchMatch = searchValue === '' || haystack.includes(searchValue);
                const statusMatch = statusValue === 'all' || status === statusValue;
                const isVisible = searchMatch && statusMatch;

                row.hidden = !isVisible;

                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (recordsFilterSummary) {
                recordsFilterSummary.textContent = `${visibleCount} of ${recordRows.length} records shown`;
            }

            if (recordsFilterEmpty) {
                recordsFilterEmpty.hidden = visibleCount !== 0;
            }
        }

        recordSearch?.addEventListener('input', applyRecordsFilter);
        recordStatusFilter?.addEventListener('change', applyRecordsFilter);
        applyRecordsFilter();

        document.querySelectorAll('[data-print-mode]').forEach((button) => {
            button.addEventListener('click', () => {
                document.body.dataset.printMode = button.dataset.printMode;
                window.print();
            });
        });

        window.addEventListener('afterprint', () => {
            delete document.body.dataset.printMode;
        });
    </script>
</body>
</html>
