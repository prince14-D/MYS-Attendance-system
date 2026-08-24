<?php
declare(strict_types=1);

/*
 * One-time import of the old storage/*.json files into MySQL.
 *
 * Usage (from the project root, after creating database/config.mysql.php
 * and importing database/schema.sql):
 *
 *   php database/migrate_json_to_mysql.php
 */

require_once __DIR__ . '/config.mysql.php';

$storageDir = dirname(__DIR__) . '/storage';

function load_json(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : [];
}

$pdo = mysql_connection();
$pdo->beginTransaction();

try {
    // Departments
    $departments = load_json($storageDir . '/departments.json');
    $stmt = $pdo->prepare(
        'INSERT INTO departments (department_id, department_name, created_at, updated_at)
         VALUES (:id, :name, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE department_name = VALUES(department_name), updated_at = VALUES(updated_at)'
    );
    foreach ($departments as $departmentId => $department) {
        $stmt->execute([
            'id' => (string) $departmentId,
            'name' => (string) ($department['department_name'] ?? ''),
            'created_at' => (string) ($department['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => (string) ($department['updated_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }
    echo 'Departments imported: ' . count($departments) . PHP_EOL;

    // Employees
    $employees = load_json($storageDir . '/employees.json');
    $stmt = $pdo->prepare(
        'INSERT INTO employees (employee_number, employee_name, position, department_id, registered_at, updated_at)
         VALUES (:employee_number, :employee_name, :position, :department_id, :registered_at, :updated_at)
         ON DUPLICATE KEY UPDATE employee_name = VALUES(employee_name), position = VALUES(position),
            department_id = VALUES(department_id), updated_at = VALUES(updated_at)'
    );
    foreach ($employees as $employeeNumber => $employee) {
        $stmt->execute([
            'employee_number' => (string) $employeeNumber,
            'employee_name' => (string) ($employee['employee_name'] ?? ''),
            'position' => (string) ($employee['position'] ?? ''),
            'department_id' => ($employee['department_id'] ?? '') !== '' ? $employee['department_id'] : null,
            'registered_at' => (string) ($employee['registered_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => (string) ($employee['updated_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }
    echo 'Employees imported: ' . count($employees) . PHP_EOL;

    // Attendance
    $attendance = load_json($storageDir . '/attendance.json');
    $knownEmployeeNumbers = array_column($pdo->query('SELECT employee_number FROM employees')->fetchAll(), 'employee_number');
    $knownEmployeeNumbers = array_flip($knownEmployeeNumbers);
    $stmt = $pdo->prepare(
        'INSERT INTO attendance (employee_number, employee_name, position, department_id, department_name, attendance_date, clock_in, clock_out, clock_in_photo, clock_in_latitude, clock_in_longitude, clock_in_accuracy_m, status, late, late_minutes, early_out, early_out_minutes)
         VALUES (:employee_number, :employee_name, :position, :department_id, :department_name, :attendance_date, :clock_in, :clock_out, :clock_in_photo, :clock_in_latitude, :clock_in_longitude, :clock_in_accuracy_m, :status, :late, :late_minutes, :early_out, :early_out_minutes)
         ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in), clock_out = VALUES(clock_out), status = VALUES(status)'
    );
    $attendanceCount = 0;
    $attendanceSkipped = 0;
    foreach ($attendance as $date => $dayRecords) {
        foreach ($dayRecords as $employeeNumber => $record) {
            // Skip attendance rows for employees that no longer exist (e.g. deleted after the record was made).
            if (!isset($knownEmployeeNumbers[(string) $employeeNumber])) {
                $attendanceSkipped++;
                continue;
            }

            $flags = is_array($record['flags'] ?? null) ? $record['flags'] : [];
            $stmt->execute([
                'employee_number' => (string) $employeeNumber,
                'employee_name' => (string) ($record['employee_name'] ?? ''),
                'position' => (string) ($record['position'] ?? ''),
                'department_id' => ($record['department_id'] ?? '') !== '' ? $record['department_id'] : null,
                'department_name' => (string) ($record['department_name'] ?? 'Unassigned'),
                'attendance_date' => (string) $date,
                'clock_in' => ($record['clock_in'] ?? '') !== '' ? $record['clock_in'] : null,
                'clock_out' => ($record['clock_out'] ?? '') !== '' ? $record['clock_out'] : null,
                'clock_in_photo' => (string) ($record['clock_in_photo'] ?? ''),
                'clock_in_latitude' => $record['clock_in_latitude'] ?? null,
                'clock_in_longitude' => $record['clock_in_longitude'] ?? null,
                'clock_in_accuracy_m' => $record['clock_in_accuracy_m'] ?? null,
                'status' => (string) ($record['status'] ?? 'Incomplete'),
                'late' => !empty($flags['late']) ? 1 : 0,
                'late_minutes' => (int) ($flags['late_minutes'] ?? 0),
                'early_out' => !empty($flags['early_out']) ? 1 : 0,
                'early_out_minutes' => (int) ($flags['early_out_minutes'] ?? 0),
            ]);
            $attendanceCount++;
        }
    }
    echo 'Attendance records imported: ' . $attendanceCount . ' (skipped ' . $attendanceSkipped . ' for unknown employees)' . PHP_EOL;

    // Excuses
    $excuses = load_json($storageDir . '/excuses.json');
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO excuses (excuse_id, employee_number, employee_name, position, department_id, department_name, absence_start, absence_end, absence_time, reason, other_reason, supporting_documents, supervisor_name, supervisor_decision, supervisor_comments, hr_reviewed_by, hr_approved, created_at, reviewed_at)
         VALUES (:excuse_id, :employee_number, :employee_name, :position, :department_id, :department_name, :absence_start, :absence_end, :absence_time, :reason, :other_reason, :supporting_documents, :supervisor_name, :supervisor_decision, :supervisor_comments, :hr_reviewed_by, :hr_approved, :created_at, :reviewed_at)'
    );
    $excusesSkipped = 0;
    foreach ($excuses as $excuse) {
        if (!isset($knownEmployeeNumbers[(string) ($excuse['employee_number'] ?? '')])) {
            $excusesSkipped++;
            continue;
        }

        $stmt->execute([
            'excuse_id' => (string) $excuse['excuse_id'],
            'employee_number' => (string) $excuse['employee_number'],
            'employee_name' => (string) ($excuse['employee_name'] ?? ''),
            'position' => (string) ($excuse['position'] ?? ''),
            'department_id' => ($excuse['department_id'] ?? '') !== '' ? $excuse['department_id'] : null,
            'department_name' => (string) ($excuse['department_name'] ?? ''),
            'absence_start' => (string) $excuse['absence_start'],
            'absence_end' => (string) $excuse['absence_end'],
            'absence_time' => (string) ($excuse['absence_time'] ?? ''),
            'reason' => (string) $excuse['reason'],
            'other_reason' => (string) ($excuse['other_reason'] ?? ''),
            'supporting_documents' => json_encode($excuse['supporting_documents'] ?? []),
            'supervisor_name' => (string) ($excuse['supervisor_name'] ?? ''),
            'supervisor_decision' => ($excuse['supervisor_decision'] ?? '') !== '' ? $excuse['supervisor_decision'] : null,
            'supervisor_comments' => (string) ($excuse['supervisor_comments'] ?? ''),
            'hr_reviewed_by' => (string) ($excuse['hr_reviewed_by'] ?? ''),
            'hr_approved' => ($excuse['hr_approved'] ?? '') !== '' ? $excuse['hr_approved'] : null,
            'created_at' => (string) ($excuse['created_at'] ?? date('Y-m-d H:i:s')),
            'reviewed_at' => ($excuse['reviewed_at'] ?? '') !== '' ? $excuse['reviewed_at'] : null,
        ]);
    }
    echo 'Excuses imported: ' . (count($excuses) - $excusesSkipped) . ' (skipped ' . $excusesSkipped . ' for unknown employees)' . PHP_EOL;

    // Letters
    $letters = load_json($storageDir . '/letters.json');
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO letters (letter_id, employee_number, employee_name, position, department_id, department_name, letter_type, subject, details, issued_by, issued_at, status, notes)
         VALUES (:letter_id, :employee_number, :employee_name, :position, :department_id, :department_name, :letter_type, :subject, :details, :issued_by, :issued_at, :status, :notes)'
    );
    $lettersSkipped = 0;
    foreach ($letters as $letter) {
        if (!isset($knownEmployeeNumbers[(string) ($letter['employee_number'] ?? '')])) {
            $lettersSkipped++;
            continue;
        }

        $stmt->execute([
            'letter_id' => (string) $letter['letter_id'],
            'employee_number' => (string) $letter['employee_number'],
            'employee_name' => (string) ($letter['employee_name'] ?? ''),
            'position' => (string) ($letter['position'] ?? ''),
            'department_id' => ($letter['department_id'] ?? '') !== '' ? $letter['department_id'] : null,
            'department_name' => (string) ($letter['department_name'] ?? 'Unassigned'),
            'letter_type' => (string) $letter['letter_type'],
            'subject' => (string) ($letter['subject'] ?? ''),
            'details' => (string) ($letter['details'] ?? ''),
            'issued_by' => (string) ($letter['issued_by'] ?? ''),
            'issued_at' => (string) ($letter['issued_at'] ?? date('Y-m-d H:i:s')),
            'status' => (string) ($letter['status'] ?? 'Issued'),
            'notes' => (string) ($letter['notes'] ?? ''),
        ]);
    }
    echo 'Letters imported: ' . (count($letters) - $lettersSkipped) . ' (skipped ' . $lettersSkipped . ' for unknown employees)' . PHP_EOL;

    // Employee documents (metadata only; files stay in storage/employee_documents)
    $documents = load_json($storageDir . '/employee_documents.json');
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO employee_documents (document_id, employee_number, label, original_name, filename, mime_type, size, uploaded_at)
         VALUES (:document_id, :employee_number, :label, :original_name, :filename, :mime_type, :size, :uploaded_at)'
    );
    $documentsSkipped = 0;
    foreach ($documents as $document) {
        if (!isset($knownEmployeeNumbers[(string) ($document['employee_number'] ?? '')])) {
            $documentsSkipped++;
            continue;
        }

        $stmt->execute([
            'document_id' => (string) $document['document_id'],
            'employee_number' => (string) $document['employee_number'],
            'label' => (string) ($document['label'] ?? ''),
            'original_name' => (string) ($document['original_name'] ?? ''),
            'filename' => (string) $document['filename'],
            'mime_type' => (string) ($document['mime_type'] ?? ''),
            'size' => (int) ($document['size'] ?? 0),
            'uploaded_at' => (string) ($document['uploaded_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }
    echo 'Employee documents imported: ' . (count($documents) - $documentsSkipped) . ' (skipped ' . $documentsSkipped . ' for unknown employees)' . PHP_EOL;

    // Devices
    $devices = load_json($storageDir . '/devices.json');
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO devices (device_id, device_name, location_name, registered_at, updated_at)
         VALUES (:device_id, :device_name, :location_name, :registered_at, :updated_at)'
    );
    foreach ($devices as $device) {
        if (!is_array($device)) {
            continue;
        }
        $stmt->execute([
            'device_id' => (string) $device['device_id'],
            'device_name' => (string) ($device['device_name'] ?? ''),
            'location_name' => (string) ($device['location_name'] ?? ''),
            'registered_at' => (string) ($device['registered_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => (string) ($device['updated_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }
    echo 'Devices imported: ' . count($devices) . PHP_EOL;

    // Users
    $users = load_json($storageDir . '/users.json');
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, role, created_at)
         VALUES (:username, :password_hash, :role, :created_at)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)'
    );
    foreach ($users as $user) {
        $stmt->execute([
            'username' => (string) $user['username'],
            'password_hash' => (string) $user['password_hash'],
            'role' => (string) ($user['role'] ?? 'viewer'),
            'created_at' => (string) ($user['created_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }
    echo 'Users imported: ' . count($users) . PHP_EOL;

    // Geofence
    $geofence = load_json($storageDir . '/geofence.json');
    if ($geofence !== []) {
        $stmt = $pdo->prepare(
            'INSERT INTO geofence_settings (id, enabled, latitude, longitude, radius_meters, updated_at)
             VALUES (1, :enabled, :latitude, :longitude, :radius_meters, :updated_at)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), latitude = VALUES(latitude),
                longitude = VALUES(longitude), radius_meters = VALUES(radius_meters), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'enabled' => !empty($geofence['enabled']) ? 1 : 0,
            'latitude' => isset($geofence['latitude']) && is_numeric($geofence['latitude']) ? (float) $geofence['latitude'] : null,
            'longitude' => isset($geofence['longitude']) && is_numeric($geofence['longitude']) ? (float) $geofence['longitude'] : null,
            'radius_meters' => (int) ($geofence['radius_meters'] ?? 150),
            'updated_at' => (string) ($geofence['updated_at'] ?? date('Y-m-d H:i:s')),
        ]);

        $locations = is_array($geofence['locations'] ?? null) ? $geofence['locations'] : [];
        $pdo->exec('DELETE FROM geofence_locations');
        $locationStmt = $pdo->prepare(
            'INSERT INTO geofence_locations (name, latitude, longitude, radius_meters, sort_order)
             VALUES (:name, :latitude, :longitude, :radius_meters, :sort_order)'
        );
        foreach ($locations as $index => $location) {
            if (!is_array($location) || !isset($location['latitude'], $location['longitude'])) {
                continue;
            }
            $locationStmt->execute([
                'name' => (string) ($location['name'] ?? 'Location ' . ($index + 1)),
                'latitude' => (float) $location['latitude'],
                'longitude' => (float) $location['longitude'],
                'radius_meters' => (int) ($location['radius_meters'] ?? 150),
                'sort_order' => (int) $index,
            ]);
        }
        echo 'Geofence settings imported.' . PHP_EOL;
    }

    $pdo->commit();
    echo 'Migration completed successfully.' . PHP_EOL;
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
