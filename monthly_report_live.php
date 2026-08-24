<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

require_admin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedDepartment = normalize_department_id($_GET['department'] ?? '');
$selectedEmployeeType = normalize_employee_type_filter((string) ($_GET['employee_type'] ?? ''));
$selectedEmployeeNumber = normalize_employee_number((string) ($_GET['employee_number'] ?? ''));
$selectedStatus = normalize_attendance_status_filter((string) ($_GET['status'] ?? 'all'));

if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}

$selectedDepartmentData = $selectedDepartment !== '' ? find_department($selectedDepartment) : null;

if ($selectedDepartment !== '' && $selectedDepartmentData === null) {
    $selectedDepartment = '';
}

if (current_user_is_department_scoped()) {
    $selectedDepartment = current_user_department_id();
}

$monthlyRecords = attendance_for_month($selectedMonth, $selectedDepartment);

$monthlyTotals = [
    'records' => 0,
    'complete' => 0,
    'incomplete' => 0,
    'late' => 0,
    'worked_minutes' => 0,
];

$days = [];
$monthStart = DateTimeImmutable::createFromFormat('Y-m-d', $selectedMonth . '-01');
$daysInMonth = $monthStart ? (int) $monthStart->format('t') : 0;

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%s-%02d', $selectedMonth, $day);
    $dayRecords = $monthlyRecords[$date] ?? [];
    $dayRecords = filter_attendance_records($dayRecords, $selectedEmployeeType, $selectedEmployeeNumber, $selectedStatus);
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

    $days[] = [
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

echo json_encode([
    'ok' => true,
    'month' => $selectedMonth,
    'department' => $selectedDepartment,
    'employee_type' => $selectedEmployeeType,
    'employee_number' => $selectedEmployeeNumber,
    'status' => $selectedStatus,
    'totals' => $monthlyTotals,
    'days' => $days,
    'updated_at' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_SLASHES);
