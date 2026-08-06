<?php
declare(strict_types=1);
require_once __DIR__ . '/../storage.php';

$employees = all_employees();
if (count($employees) === 0) {
    echo "verify=skipped_no_employee\n";
    exit;
}

$employee = (string) ($employees[0]['employee_number'] ?? '');
$status = employee_clock_in_status($employee, date('Y-m-d'));

echo "verify_employee={$employee}\n";
echo "already_clocked_in=" . (($status['clocked_in'] ?? false) ? 'yes' : 'no') . "\n";

if (($status['clocked_in'] ?? false) === true) {
    $second = employee_attendance_action($employee, 'clock_in', '');
    echo "second_clock_in_blocked=" . ((\!$second['ok'] && str_contains($second['message'], 'already clocked in')) ? 'yes' : 'no') . "\n";
    exit;
}

echo "second_clock_in_blocked=not_tested_employee_not_clocked_in\n";
