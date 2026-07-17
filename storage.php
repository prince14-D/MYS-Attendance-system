<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function read_attendance(): array
{
    $contents = file_get_contents(ATTENDANCE_FILE);
    $records = json_decode($contents ?: '[]', true);

    return is_array($records) ? $records : [];
}

function write_attendance(array $records): void
{
    $handle = fopen(ATTENDANCE_FILE, 'c+');

    if (!$handle) {
        throw new RuntimeException('Unable to open attendance storage.');
    }

    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($records, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function employee_attendance_action(string $employeeNumber, string $action): array
{
    $employeeNumber = trim($employeeNumber);

    if ($employeeNumber === '' || !preg_match('/^[A-Za-z0-9-]{2,30}$/', $employeeNumber)) {
        return ['ok' => false, 'message' => 'Enter a valid employee number.'];
    }

    if (!in_array($action, ['clock_in', 'clock_out'], true)) {
        return ['ok' => false, 'message' => 'Invalid attendance action.'];
    }

    $records = read_attendance();
    $date = date('Y-m-d');
    $time = date('H:i:s');

    if (!isset($records[$date])) {
        $records[$date] = [];
    }

    if (!isset($records[$date][$employeeNumber])) {
        $records[$date][$employeeNumber] = [
            'employee_number' => $employeeNumber,
            'date' => $date,
            'clock_in' => '',
            'clock_out' => '',
            'status' => 'Incomplete',
        ];
    }

    $record = $records[$date][$employeeNumber];

    if ($action === 'clock_in') {
        if ($record['clock_in'] !== '') {
            return ['ok' => false, 'message' => 'This employee has already clocked in today.'];
        }

        $record['clock_in'] = $time;
        $message = 'Clock in recorded for employee ' . $employeeNumber . ' at ' . $time . '.';
        $title = 'Welcome to work';
        $body = 'Welcome to work this morning. Your clock in has been recorded successfully.';
    } else {
        if ($record['clock_in'] === '') {
            return ['ok' => false, 'message' => 'Please clock in before clocking out.'];
        }

        if ($record['clock_out'] !== '') {
            return ['ok' => false, 'message' => 'This employee has already clocked out today.'];
        }

        $record['clock_out'] = $time;
        $message = 'Clock out recorded for employee ' . $employeeNumber . ' at ' . $time . '.';
        $title = 'Safe trip home';
        $body = 'Have a safe trip home. Goodbye, and it was nice having you at work today.';
    }

    $record['status'] = $record['clock_in'] !== '' && $record['clock_out'] !== '' ? 'Complete' : 'Incomplete';
    $records[$date][$employeeNumber] = $record;
    write_attendance($records);

    return [
        'ok' => true,
        'message' => $message,
        'title' => $title,
        'body' => $body,
        'employee_number' => $employeeNumber,
        'time' => $time,
        'action' => $action,
    ];
}

function attendance_for_date(string $date): array
{
    $records = read_attendance();
    $dayRecords = $records[$date] ?? [];

    usort($dayRecords, static function (array $a, array $b): int {
        return strcmp($a['employee_number'], $b['employee_number']);
    });

    return $dayRecords;
}

function all_attendance_dates(): array
{
    $records = read_attendance();
    $dates = array_keys($records);
    rsort($dates);

    return $dates;
}

function worked_hours(array $record): string
{
    if (($record['clock_in'] ?? '') === '' || ($record['clock_out'] ?? '') === '') {
        return '';
    }

    $start = strtotime($record['date'] . ' ' . $record['clock_in']);
    $end = strtotime($record['date'] . ' ' . $record['clock_out']);

    if (!$start || !$end || $end < $start) {
        return '';
    }

    $minutes = (int) floor(($end - $start) / 60);
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    return sprintf('%02d:%02d', $hours, $remainingMinutes);
}
