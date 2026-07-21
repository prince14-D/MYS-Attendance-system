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

function read_employees(): array
{
    $contents = file_get_contents(EMPLOYEES_FILE);
    $employees = json_decode($contents ?: '[]', true);

    return is_array($employees) ? $employees : [];
}

function write_employees(array $employees): void
{
    $handle = fopen(EMPLOYEES_FILE, 'c+');

    if (!$handle) {
        throw new RuntimeException('Unable to open employee storage.');
    }

    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($employees, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function read_departments(): array
{
    $contents = file_get_contents(DEPARTMENTS_FILE);
    $departments = json_decode($contents ?: '[]', true);

    return is_array($departments) ? $departments : [];
}

function write_departments(array $departments): void
{
    $handle = fopen(DEPARTMENTS_FILE, 'c+');

    if (!$handle) {
        throw new RuntimeException('Unable to open department storage.');
    }

    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($departments, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function normalize_employee_number(string $employeeNumber): string
{
    return strtoupper(trim($employeeNumber));
}

function normalize_department_id(string $departmentId): string
{
    return strtolower(trim($departmentId));
}

function department_id_from_name(string $departmentName): string
{
    $departmentId = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $departmentName) ?? ''));
    return trim($departmentId, '-');
}

function register_department(string $departmentName): array
{
    $departmentName = trim(preg_replace('/\s+/', ' ', $departmentName) ?? '');

    if ($departmentName === '' || !preg_match('/^[A-Za-z0-9 &.\'-]{2,80}$/', $departmentName)) {
        return ['ok' => false, 'message' => 'Enter a valid department name.'];
    }

    $departmentId = department_id_from_name($departmentName);

    if ($departmentId === '') {
        return ['ok' => false, 'message' => 'Enter a valid department name.'];
    }

    $departments = read_departments();
    $isUpdate = isset($departments[$departmentId]);
    $departments[$departmentId] = [
        'department_id' => $departmentId,
        'department_name' => $departmentName,
        'created_at' => $departments[$departmentId]['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    write_departments($departments);

    return [
        'ok' => true,
        'message' => $isUpdate ? 'Department updated successfully.' : 'Department created successfully.',
    ];
}

function all_departments(): array
{
    $departments = array_values(read_departments());

    usort($departments, static function (array $a, array $b): int {
        return strcmp($a['department_name'], $b['department_name']);
    });

    return $departments;
}

function find_department(string $departmentId): ?array
{
    $departments = read_departments();
    $departmentId = normalize_department_id($departmentId);

    return $departments[$departmentId] ?? null;
}

function register_employee(string $employeeNumber, string $employeeName, string $departmentId): array
{
    $employeeNumber = normalize_employee_number($employeeNumber);
    $employeeName = trim(preg_replace('/\s+/', ' ', $employeeName) ?? '');
    $department = find_department($departmentId);

    if ($employeeNumber === '' || !preg_match('/^[A-Z0-9-]{2,30}$/', $employeeNumber)) {
        return ['ok' => false, 'message' => 'Enter a valid employee number.'];
    }

    if ($employeeName === '' || !preg_match('/^[A-Za-z .\'-]{2,80}$/', $employeeName)) {
        return ['ok' => false, 'message' => 'Enter a valid employee name.'];
    }

    if ($department === null) {
        return ['ok' => false, 'message' => 'Select a registered department.'];
    }

    $employees = read_employees();
    $isUpdate = isset($employees[$employeeNumber]);
    $employees[$employeeNumber] = [
        'employee_number' => $employeeNumber,
        'employee_name' => $employeeName,
        'department_id' => $department['department_id'],
        'department_name' => $department['department_name'],
        'registered_at' => $employees[$employeeNumber]['registered_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    write_employees($employees);

    return [
        'ok' => true,
        'message' => $isUpdate ? 'Employee updated successfully.' : 'Employee registered successfully.',
    ];
}

function delete_employee(string $employeeNumber): array
{
    $employeeNumber = normalize_employee_number($employeeNumber);
    $employees = read_employees();

    if (!isset($employees[$employeeNumber])) {
        return ['ok' => false, 'message' => 'Employee was not found.'];
    }

    unset($employees[$employeeNumber]);
    write_employees($employees);

    return ['ok' => true, 'message' => 'Employee deleted successfully.'];
}

function all_employees(): array
{
    $employees = array_values(read_employees());

    usort($employees, static function (array $a, array $b): int {
        $departmentCompare = strcmp($a['department_name'] ?? '', $b['department_name'] ?? '');
        return $departmentCompare !== 0 ? $departmentCompare : strcmp($a['employee_number'], $b['employee_number']);
    });

    return $employees;
}

function find_employee(string $employeeNumber): ?array
{
    $employees = read_employees();
    $employeeNumber = normalize_employee_number($employeeNumber);

    return $employees[$employeeNumber] ?? null;
}

function save_clock_in_photo(string $photoData, string $employeeNumber, string $date, string $time = ''): string
{
    if (!preg_match('/^data:image\/(png|jpeg);base64,([A-Za-z0-9+\/=]+)$/', $photoData, $matches)) {
        throw new InvalidArgumentException('Please capture a clear photo before clocking in.');
    }

    $extension = $matches[1] === 'jpeg' ? 'jpg' : 'png';
    $binary = base64_decode($matches[2], true);

    if ($binary === false || strlen($binary) < 1000) {
        throw new InvalidArgumentException('Please capture a clear photo before clocking in.');
    }

    if (strlen($binary) > 3 * 1024 * 1024) {
        throw new InvalidArgumentException('The captured photo is too large. Please retake it.');
    }

    $safeEmployee = preg_replace('/[^A-Za-z0-9-]+/', '-', $employeeNumber) ?: 'employee';
    $safeTime = preg_replace('/[^0-9]+/', '', $time) ?: date('His');
    $filename = $date . '-' . $safeEmployee . '-' . $safeTime . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
    $path = PHOTOS_DIR . '/' . $filename;

    if (file_put_contents($path, $binary) === false) {
        throw new RuntimeException('Unable to save the attendance photo.');
    }

    return 'storage/photos/' . $filename;
}

function record_attendance_action(string $employeeNumber, string $action, string $photoData = '', ?DateTimeInterface $recordedAt = null): array
{
    $employeeNumber = normalize_employee_number($employeeNumber);

    if ($employeeNumber === '' || !preg_match('/^[A-Z0-9-]{2,30}$/', $employeeNumber)) {
        return ['ok' => false, 'message' => 'Enter a valid employee number.'];
    }

    if (!in_array($action, ['clock_in', 'clock_out'], true)) {
        return ['ok' => false, 'message' => 'Invalid attendance action.'];
    }

    $employee = find_employee($employeeNumber);

    if ($employee === null) {
        return ['ok' => false, 'message' => 'This employee number is not registered. Please contact admin.'];
    }

    $employeeName = $employee['employee_name'];
    $departmentId = $employee['department_id'] ?? '';
    $departmentName = $employee['department_name'] ?? 'Unassigned';
    $records = read_attendance();
    $recordedAt ??= new DateTimeImmutable('now');
    $date = $recordedAt->format('Y-m-d');
    $time = $recordedAt->format('H:i:s');

    if (!isset($records[$date])) {
        $records[$date] = [];
    }

    if (!isset($records[$date][$employeeNumber])) {
        $records[$date][$employeeNumber] = [
            'employee_number' => $employeeNumber,
            'employee_name' => $employeeName,
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'date' => $date,
            'clock_in' => '',
            'clock_out' => '',
            'clock_in_photo' => '',
            'status' => 'Incomplete',
        ];
    }

    $record = $records[$date][$employeeNumber];
    $record['employee_name'] = $employeeName;
    $record['department_id'] = $departmentId;
    $record['department_name'] = $departmentName;

    if ($action === 'clock_in') {
        if ($record['clock_in'] !== '') {
            return ['ok' => false, 'message' => 'This employee has already clocked in today.'];
        }

        try {
            $record['clock_in_photo'] = save_clock_in_photo($photoData, $employeeNumber, $date, $time);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }

        $record['clock_in'] = $time;
        $message = 'Clock in recorded for ' . $employeeName . ' at ' . $time . '.';
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
        $message = 'Clock out recorded for ' . $employeeName . ' at ' . $time . '.';
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
        'employee_name' => $employeeName,
        'department_id' => $departmentId,
        'department_name' => $departmentName,
        'time' => $time,
        'action' => $action,
    ];
}

function employee_attendance_action(string $employeeNumber, string $action, string $photoData = ''): array
{
    return record_attendance_action($employeeNumber, $action, $photoData);
}

function sync_offline_attendance(array $items): array
{
    $results = [];

    foreach ($items as $item) {
        $id = is_array($item) ? (string) ($item['id'] ?? '') : '';
        $recordedAtValue = is_array($item) ? (string) ($item['recorded_at'] ?? '') : '';
        $recordedAt = null;

        if ($recordedAtValue !== '') {
            try {
                $recordedAt = new DateTimeImmutable($recordedAtValue);
            } catch (Exception) {
                $recordedAt = null;
            }
        }

        if (!$recordedAt) {
            $results[] = [
                'id' => $id,
                'ok' => false,
                'message' => 'Offline record has an invalid time.',
            ];
            continue;
        }

        $result = record_attendance_action(
            (string) ($item['employee_number'] ?? ''),
            (string) ($item['action'] ?? ''),
            (string) ($item['clock_in_photo'] ?? ''),
            $recordedAt
        );

        $results[] = [
            'id' => $id,
            'ok' => $result['ok'],
            'message' => $result['message'],
        ];
    }

    return $results;
}

function attendance_for_date(string $date, string $departmentId = ''): array
{
    $records = read_attendance();
    $dayRecords = $records[$date] ?? [];
    $employees = read_employees();
    $departmentId = normalize_department_id($departmentId);

    foreach ($dayRecords as $index => $record) {
        $employeeNumber = $record['employee_number'] ?? '';

        if (isset($employees[$employeeNumber])) {
            if (($record['employee_name'] ?? '') === '') {
                $dayRecords[$index]['employee_name'] = $employees[$employeeNumber]['employee_name'];
            }

            if (($record['department_id'] ?? '') === '') {
                $dayRecords[$index]['department_id'] = $employees[$employeeNumber]['department_id'] ?? '';
                $dayRecords[$index]['department_name'] = $employees[$employeeNumber]['department_name'] ?? 'Unassigned';
            }
        }
    }

    if ($departmentId !== '') {
        $dayRecords = array_filter($dayRecords, static function (array $record) use ($departmentId): bool {
            return ($record['department_id'] ?? '') === $departmentId;
        });
    }

    usort($dayRecords, static function (array $a, array $b): int {
        $departmentCompare = strcmp($a['department_name'] ?? '', $b['department_name'] ?? '');
        return $departmentCompare !== 0 ? $departmentCompare : strcmp($a['employee_number'], $b['employee_number']);
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
