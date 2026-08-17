<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

require_admin();

$registrationResult = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $adminAction = $_POST['admin_action'] ?? '';

    if ($adminAction === 'download_backup') {
        $backup = create_json_backup_snapshot();

        if (!$backup['ok']) {
            $registrationResult = $backup;
        } else {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
            echo $backup['json'];
            exit;
        }
    }

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
    } elseif ($adminAction === 'update_geofence') {
        $registrationResult = update_geofence_settings(
            isset($_POST['geofence_enabled']) ? '1' : '0',
            $_POST['geofence_latitude'] ?? '',
            $_POST['geofence_longitude'] ?? '',
            $_POST['geofence_radius_meters'] ?? ''
        );
    } elseif ($adminAction === 'restore_backup') {
        $registrationResult = restore_from_backup_upload($_FILES['backup_file'] ?? []);
    } elseif ($adminAction === 'upload_employee_document') {
        $registrationResult = upload_employee_document($_POST['employee_number'] ?? '', $_FILES['employee_document'] ?? [], $_POST['document_label'] ?? '');
    } elseif ($adminAction === 'delete_employee_document') {
        $registrationResult = delete_employee_document($_POST['document_id'] ?? '', $_POST['employee_number'] ?? '');
    } elseif ($adminAction === 'update_employee_profile') {
        $employeeNumber = $_POST['employee_number'] ?? '';
        $registrationResult = update_employee_record($employeeNumber, $employeeNumber, $_POST['employee_name'] ?? '', $_POST['department_id'] ?? '', $_POST['position'] ?? '');
    } elseif ($adminAction === 'create_system_user') {
        require_roles(['admin']);
        $registrationResult = create_system_user($_POST['username'] ?? '', $_POST['password'] ?? '', $_POST['role'] ?? '');
    } elseif ($adminAction === 'update_system_user') {
        require_roles(['admin']);
        $registrationResult = update_system_user($_POST['username'] ?? '', $_POST['role'] ?? '', $_POST['new_password'] ?? '');
    } elseif ($adminAction === 'delete_system_user') {
        require_roles(['admin']);
        $registrationResult = delete_system_user($_POST['username'] ?? '');
    } elseif ($adminAction === 'review_excuse') {
        require_roles(['admin', 'hr', 'supervisor']);
        $registrationResult = review_excuse($_POST['excuse_id'] ?? '', current_username(), $_POST['decision'] ?? '');
    }
}

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedDepartment = normalize_department_id($_GET['department'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$selectedDepartmentData = $selectedDepartment !== '' ? find_department($selectedDepartment) : null;

if ($selectedDepartment !== '' && $selectedDepartmentData === null) {
    $selectedDepartment = '';
}

$allowedAdminPages = [
    'home',
    'register_employee',
    'employees',
    'attendance_log',
    'create_department',
    'backup_restore',
    'geofence',
    'monthly_report',
    'excuse_form',
    'tally_sheet',
    'employee_profiles',
    'user_management',
];

$activePage = $_GET['page'] ?? 'home';

if (!in_array($activePage, $allowedAdminPages, true)) {
    $activePage = 'home';
}

$adminPageLinks = [
    'home' => ['label' => 'Overview', 'href' => 'overview.php'],
    'register_employee' => ['label' => 'Register Employee', 'href' => 'register_employee.php'],
    'employees' => ['label' => 'Employees', 'href' => 'employees.php'],
    'attendance_log' => ['label' => 'Attendance Log', 'href' => 'attendance_log.php?date=' . urlencode($selectedDate) . '&department=' . urlencode($selectedDepartment)],
    'create_department' => ['label' => 'Create Department', 'href' => 'create_department.php'],
    'backup_restore' => ['label' => 'Backup & Restore', 'href' => 'backup_restore.php'],
    'geofence' => ['label' => 'Setup Geofence', 'href' => 'geofence.php'],
    'monthly_report' => ['label' => 'Monthly Report', 'href' => 'monthly_report.php?month=' . urlencode($selectedMonth) . '&department=' . urlencode($selectedDepartment)],
    'excuse_form' => ['label' => 'Employee Excuse Form', 'href' => 'excuse_form.php?month=' . urlencode($selectedMonth) . '&department=' . urlencode($selectedDepartment)],
    'tally_sheet' => ['label' => 'Attendance Tally Sheet', 'href' => 'tally_sheet.php?month=' . urlencode($selectedMonth) . '&department=' . urlencode($selectedDepartment)],
    'employee_profiles' => ['label' => 'Employee Profiles', 'href' => 'employee_profiles.php'],
    'user_management' => ['label' => 'User Management', 'href' => 'user_management.php'],
];

$pageRoles = [
    'home' => ['admin', 'hr', 'supervisor', 'viewer'],
    'register_employee' => ['admin'], 'employees' => ['admin', 'hr'], 'attendance_log' => ['admin', 'supervisor'],
    'create_department' => ['admin'], 'backup_restore' => ['admin'], 'geofence' => ['admin'],
    'monthly_report' => ['admin', 'hr', 'viewer'], 'excuse_form' => ['admin', 'hr', 'supervisor'],
    'tally_sheet' => ['admin', 'supervisor', 'viewer'], 'employee_profiles' => ['admin', 'hr'], 'user_management' => ['admin'],
];
require_roles($pageRoles[$activePage] ?? ['admin']);
$adminPageLinks = array_filter($adminPageLinks, static fn (string $key): bool => in_array(current_user_role(), $pageRoles[$key] ?? [], true), ARRAY_FILTER_USE_KEY);

$records = attendance_for_date($selectedDate, $selectedDepartment);
$monthlyRecords = attendance_for_month($selectedMonth, $selectedDepartment);
$monthlyExcuses = excuses_for_month($selectedMonth, $selectedDepartment);
$dates = all_attendance_dates();
$employees = all_employees();
$departments = all_departments();
$geofenceSettings = read_geofence_settings();
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

$monthlyReportDays = [];
$monthlyTotals = [
    'records' => 0,
    'complete' => 0,
    'incomplete' => 0,
    'late' => 0,
    'worked_minutes' => 0,
    'excuses' => count($monthlyExcuses),
];

$monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $selectedMonth . '-01');
$daysInMonth = $monthStart ? (int) $monthStart->format('t') : 0;

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%s-%02d', $selectedMonth, $day);
    $dayRecords = $monthlyRecords[$date] ?? [];
    $completeCount = 0;
    $lateCount = 0;
    $workedMinutes = 0;

    foreach ($dayRecords as $record) {
        $monthlyTotals['records']++;

        if (($record['status'] ?? '') === 'Complete') {
            $completeCount++;
            $monthlyTotals['complete']++;
        } else {
            $monthlyTotals['incomplete']++;
        }

        $flags = is_array($record['flags'] ?? null) ? $record['flags'] : [];

        if (($flags['late'] ?? false) === true) {
            $lateCount++;
            $monthlyTotals['late']++;
        }

        $worked = worked_hours($record);

        if ($worked !== '') {
            [$hours, $minutes] = array_map('intval', explode(':', $worked));
            $workedMinutes += ($hours * 60) + $minutes;
            $monthlyTotals['worked_minutes'] += ($hours * 60) + $minutes;
        }
    }

    $monthlyReportDays[] = [
        'date' => $date,
        'day_label' => date('j', strtotime($date)),
        'weekday' => date('D', strtotime($date)),
        'total' => count($dayRecords),
        'complete' => $completeCount,
        'incomplete' => max(0, count($dayRecords) - $completeCount),
        'late' => $lateCount,
        'worked_minutes' => $workedMinutes,
    ];
}

$monthlySummaryCards = [
    ['label' => 'Total Records', 'value' => (string) $monthlyTotals['records'], 'note' => 'All attendance entries in month'],
    ['label' => 'Complete', 'value' => (string) $monthlyTotals['complete'], 'note' => 'Finished shifts'],
    ['label' => 'Late Arrivals', 'value' => (string) $monthlyTotals['late'], 'note' => 'Clock-ins after shift start'],
    ['label' => 'Incomplete', 'value' => (string) $monthlyTotals['incomplete'], 'note' => 'Missing clock-out or clock-in'],
    ['label' => 'Excuses', 'value' => (string) $monthlyTotals['excuses'], 'note' => 'Employee excuse forms submitted'],
];

$monthlyMaxBar = max(1, ...array_map(static fn (array $item): int => max((int) $item['total'], (int) $item['complete'], (int) $item['late'], 1), $monthlyReportDays));

$monthlyAverageWorked = $monthlyTotals['complete'] > 0 ? (int) floor($monthlyTotals['worked_minutes'] / $monthlyTotals['complete']) : 0;

$formatMinutes = static function (int $minutes): string {
    $hours = intdiv(max(0, $minutes), 60);
    $remaining = max(0, $minutes) % 60;

    return sprintf('%02d:%02d', $hours, $remaining);
};

$employeeSearchRows = [];

foreach ($employees as $employeeIndex => $employee) {
    $employeeSearchRows[] = [
        'index' => $employeeIndex,
        'haystack' => strtolower(implode(' ', [
            (string) ($employee['employee_number'] ?? ''),
            (string) ($employee['employee_name'] ?? ''),
            (string) ($employee['position'] ?? ''),
            (string) ($employee['department_name'] ?? ''),
        ])),
    ];
}
