<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../storage.php';

echo "invalid_login=" . (attempt_admin_login('admin', 'wrong') ? 'fail' : 'ok') . PHP_EOL;
echo "valid_login=" . (attempt_admin_login('admin', 'admin123') ? 'ok' : 'fail') . PHP_EOL;

$_SESSION['admin_logged_in'] = true;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['date' => date('Y-m-d')];

ob_start();
include __DIR__ . '/../dashboard.php';
$html = ob_get_clean();

echo "dashboard_attendance_log=" . (strpos($html, 'Attendance Log') !== false ? 'ok' : 'fail') . PHP_EOL;
echo "dashboard_quick_search=" . (strpos($html, 'recordSearch') !== false ? 'ok' : 'fail') . PHP_EOL;
echo "dashboard_status_filter=" . (strpos($html, 'recordStatusFilter') !== false ? 'ok' : 'fail') . PHP_EOL;

$today = date('Y-m-d');
$todayRecords = read_attendance()[$today] ?? [];
$candidate = '';
foreach (all_employees() as $employee) {
    $number = (string) ($employee['employee_number'] ?? '');
    if ($number !== '' && !isset($todayRecords[$number])) {
        $candidate = $number;
        break;
    }
}

if ($candidate === '') {
    echo "clockout_validation=skipped_no_candidate" . PHP_EOL;
} else {
    $clockOutAttempt = employee_attendance_action($candidate, 'clock_out', '');
    $ok = !$clockOutAttempt['ok'] && str_contains($clockOutAttempt['message'], 'clock in first');
    echo "clockout_validation=" . ($ok ? 'ok' : 'fail') . PHP_EOL;
    echo "clockout_validation_employee=" . $candidate . PHP_EOL;
}

$adminUpdateAttempt = update_attendance_record(
    '2026-08-05',
    '436175',
    '436175',
    'Mishell S. Feika',
    'Administrative Assistant',
    '',
    '2026-08-05',
    '',
    '17:00:00'
);

$adminOk = !$adminUpdateAttempt['ok'] && str_contains($adminUpdateAttempt['message'], 'Clock in must be set before clock out');
echo "admin_update_clockout_without_clockin=" . ($adminOk ? 'ok' : 'fail') . PHP_EOL;
