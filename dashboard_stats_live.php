<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

require_roles(['admin', 'hr', 'supervisor', 'viewer']);

$departmentId = current_user_is_department_scoped() ? current_user_department_id() : normalize_department_id($_GET['department'] ?? '');

$employees = $departmentId !== ''
    ? array_values(array_filter(all_employees(), static fn (array $employee): bool => ($employee['department_id'] ?? '') === $departmentId))
    : all_employees();

$counts = ['Employee' => 0, 'Contractor' => 0, 'Volunteer' => 0];

foreach ($employees as $employee) {
    $type = (string) ($employee['employee_type'] ?? 'Employee');
    if (isset($counts[$type])) {
        $counts[$type]++;
    }
}

$today = date('Y-m-d');
$records = attendance_for_date($today, $departmentId);
$clockedIn = count(array_filter($records, static fn (array $record): bool => ($record['clock_in'] ?? '') !== '' && ($record['clock_out'] ?? '') === ''));
$complete = count(array_filter($records, static fn (array $record): bool => ($record['status'] ?? '') === 'Complete'));

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'total_employees' => $counts['Employee'],
    'total_contractors' => $counts['Contractor'],
    'total_volunteers' => $counts['Volunteer'],
    'total_staff' => count($employees),
    'today_records' => count($records),
    'clocked_in_now' => $clockedIn,
    'complete_today' => $complete,
    'generated_at' => date('c'),
]);
