<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function read_attendance(): array
{
    $handle = fopen(ATTENDANCE_FILE, 'c+');

    if (!$handle) {
        throw new RuntimeException('Unable to open attendance storage.');
    }

    flock($handle, LOCK_SH);
    rewind($handle);
    $contents = stream_get_contents($handle) ?: '[]';
    flock($handle, LOCK_UN);
    fclose($handle);

    $records = json_decode($contents, true);

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

function mutate_attendance(callable $mutator): mixed
{
    $handle = fopen(ATTENDANCE_FILE, 'c+');

    if (!$handle) {
        throw new RuntimeException('Unable to open attendance storage.');
    }

    flock($handle, LOCK_EX);
    rewind($handle);
    $contents = stream_get_contents($handle) ?: '[]';
    $records = json_decode($contents, true);

    if (!is_array($records)) {
        $records = [];
    }

    $result = $mutator($records);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($records, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $result;
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

function read_excuses(): array
{
    $contents = file_get_contents(EXCUSES_FILE);
    $excuses = json_decode($contents ?: '[]', true);

    return is_array($excuses) ? $excuses : [];
}

function write_excuses(array $excuses): void
{
    $handle = fopen(EXCUSES_FILE, 'c+');

    if (!$handle) {
        throw new RuntimeException('Unable to open excuse storage.');
    }

    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($excuses, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function read_employee_documents(): array
{
    $contents = file_get_contents(EMPLOYEE_DOCUMENTS_FILE);
    $documents = json_decode($contents ?: '[]', true);

    return is_array($documents) ? $documents : [];
}

function write_employee_documents(array $documents): void
{
    $handle = fopen(EMPLOYEE_DOCUMENTS_FILE, 'c+');
    if (!$handle) {
        throw new RuntimeException('Unable to open employee document storage.');
    }
    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($documents, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function employee_documents_for(string $employeeNumber): array
{
    $employeeNumber = normalize_employee_number($employeeNumber);
    $documents = array_values(array_filter(read_employee_documents(), static fn (array $document): bool => ($document['employee_number'] ?? '') === $employeeNumber));
    usort($documents, static fn (array $a, array $b): int => strcmp((string) ($b['uploaded_at'] ?? ''), (string) ($a['uploaded_at'] ?? '')));
    return $documents;
}

function upload_employee_document(string $employeeNumber, array $upload, string $label): array
{
    $employeeNumber = normalize_employee_number($employeeNumber);
    $employee = find_employee($employeeNumber);
    $label = normalize_person_name($label);

    if ($employee === null) {
        return ['ok' => false, 'message' => 'Employee was not found.'];
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
        return ['ok' => false, 'message' => 'Choose a document to upload.'];
    }
    if ((int) ($upload['size'] ?? 0) > 10 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Documents must be 10 MB or smaller.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $upload['tmp_name']);
    $allowed = [
        'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
        'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'message' => 'Upload a PDF, image, Word, or Excel file.'];
    }

    $id = 'DOC-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $filename = $id . '.' . $allowed[$mime];
    if (!move_uploaded_file((string) $upload['tmp_name'], EMPLOYEE_DOCUMENTS_DIR . '/' . $filename)) {
        return ['ok' => false, 'message' => 'Unable to save the uploaded document.'];
    }
    $documents = read_employee_documents();
    $documents[] = ['document_id' => $id, 'employee_number' => $employeeNumber, 'label' => $label !== '' ? $label : 'Employee document', 'original_name' => basename((string) ($upload['name'] ?? 'document')), 'filename' => $filename, 'mime_type' => $mime, 'size' => (int) $upload['size'], 'uploaded_at' => date('Y-m-d H:i:s')];
    write_employee_documents($documents);
    return ['ok' => true, 'message' => 'Document uploaded successfully.'];
}

function find_employee_document(string $documentId): ?array
{
    foreach (read_employee_documents() as $document) {
        if (($document['document_id'] ?? '') === $documentId) {
            return $document;
        }
    }
    return null;
}

function delete_employee_document(string $documentId, string $employeeNumber): array
{
    $employeeNumber = normalize_employee_number($employeeNumber);
    $documents = read_employee_documents();
    $documentIndex = null;

    foreach ($documents as $index => $document) {
        if (($document['document_id'] ?? '') === $documentId && ($document['employee_number'] ?? '') === $employeeNumber) {
            $documentIndex = $index;
            break;
        }
    }
    if ($documentIndex === null) {
        return ['ok' => false, 'message' => 'Document was not found for this employee.'];
    }

    $filename = basename((string) ($documents[$documentIndex]['filename'] ?? ''));
    $path = EMPLOYEE_DOCUMENTS_DIR . '/' . $filename;
    if ($filename !== '' && is_file($path) && !unlink($path)) {
        return ['ok' => false, 'message' => 'Unable to remove the document file.'];
    }
    array_splice($documents, $documentIndex, 1);
    write_employee_documents($documents);
    return ['ok' => true, 'message' => 'Employee document deleted.'];
}

function excuses_for_month(string $month, string $departmentId = ''): array
{
    $excuses = array_values(array_filter(read_excuses(), static function (array $excuse) use ($month, $departmentId): bool {
        $startDate = (string) ($excuse['absence_start'] ?? '');
        $endDate = (string) ($excuse['absence_end'] ?? $startDate);
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $matchesMonth = $startDate !== '' && $startDate <= $monthEnd && $endDate >= $monthStart;
        $matchesDepartment = $departmentId === '' || (string) ($excuse['department_id'] ?? '') === $departmentId;

        return $matchesMonth && $matchesDepartment;
    }));

    usort($excuses, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

    return $excuses;
}

function create_excuse(array $input): array
{
    $employeeNumber = normalize_employee_number((string) ($input['employee_number'] ?? ''));
    $employee = $employeeNumber !== '' ? find_employee($employeeNumber) : null;
    $absenceStart = trim((string) ($input['absence_start'] ?? ''));
    $absenceEnd = trim((string) ($input['absence_end'] ?? ''));
    $reason = trim((string) ($input['reason'] ?? ''));
    $otherReason = normalize_person_name((string) ($input['other_reason'] ?? ''));

    if ($employee === null) {
        return ['ok' => false, 'message' => 'Select a registered employee.'];
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $absenceStart) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $absenceEnd) || $absenceEnd < $absenceStart) {
        return ['ok' => false, 'message' => 'Enter a valid absence start and end date.'];
    }

    $allowedReasons = ['Medical Appointment', 'Illness', 'Family Emergency', 'Official Assignment', 'Other'];
    if (!in_array($reason, $allowedReasons, true)) {
        return ['ok' => false, 'message' => 'Choose a valid excuse reason.'];
    }

    if ($reason === 'Other' && $otherReason === '') {
        return ['ok' => false, 'message' => 'Specify the other excuse reason.'];
    }

    $id = 'EXC-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    $excuse = [
        'excuse_id' => $id,
        'employee_number' => $employeeNumber,
        'employee_name' => (string) ($employee['employee_name'] ?? ''),
        'position' => (string) ($employee['position'] ?? ''),
        'department_id' => (string) ($employee['department_id'] ?? ''),
        'department_name' => (string) ($employee['department_name'] ?? ''),
        'absence_start' => $absenceStart,
        'absence_end' => $absenceEnd,
        'absence_time' => trim((string) ($input['absence_time'] ?? '')),
        'reason' => $reason,
        'other_reason' => $otherReason,
        'supporting_documents' => array_values(array_intersect((array) ($input['supporting_documents'] ?? []), ['Medical Certificate', 'Official Assignment Letter', 'Other'])),
        'supervisor_name' => normalize_person_name((string) ($input['supervisor_name'] ?? '')),
        'supervisor_decision' => in_array(($input['supervisor_decision'] ?? ''), ['Approved', 'Not Approved'], true) ? $input['supervisor_decision'] : '',
        'supervisor_comments' => trim((string) ($input['supervisor_comments'] ?? '')),
        'hr_reviewed_by' => normalize_person_name((string) ($input['hr_reviewed_by'] ?? '')),
        'hr_approved' => in_array(($input['hr_approved'] ?? ''), ['Yes', 'No'], true) ? $input['hr_approved'] : '',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $excuses = read_excuses();
    $excuses[] = $excuse;
    write_excuses($excuses);

    return ['ok' => true, 'message' => 'Employee excuse form saved.', 'excuse' => $excuse];
}

function review_excuse(string $excuseId, string $reviewedBy, string $decision): array
{
    $decision = $decision === 'Approved' ? 'Yes' : ($decision === 'Denied' ? 'No' : '');
    $reviewedBy = normalize_person_name($reviewedBy);
    if ($excuseId === '' || $decision === '' || $reviewedBy === '') return ['ok' => false, 'message' => 'Enter the reviewer name and an approval decision.'];
    $excuses = read_excuses();
    foreach ($excuses as &$excuse) {
        if (($excuse['excuse_id'] ?? '') === $excuseId) {
            $excuse['hr_reviewed_by'] = $reviewedBy;
            $excuse['hr_approved'] = $decision;
            $excuse['reviewed_at'] = date('Y-m-d H:i:s');
            write_excuses($excuses);
            return ['ok' => true, 'message' => 'Excuse request ' . strtolower($decision === 'Yes' ? 'approved' : 'denied') . '.'];
        }
    }
    return ['ok' => false, 'message' => 'Excuse request not found.'];
}

function default_geofence_settings(): array
{
    return [
        'enabled' => false,
        'latitude' => null,
        'longitude' => null,
        'radius_meters' => 150,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
}

function read_geofence_settings(): array
{
    $contents = file_get_contents(GEOFENCE_FILE);
    $settings = json_decode($contents ?: '[]', true);
    $defaults = default_geofence_settings();

    if (!is_array($settings)) {
        return $defaults;
    }

    $enabled = (bool) ($settings['enabled'] ?? false);
    $latitude = isset($settings['latitude']) && is_numeric($settings['latitude']) ? (float) $settings['latitude'] : null;
    $longitude = isset($settings['longitude']) && is_numeric($settings['longitude']) ? (float) $settings['longitude'] : null;
    $radiusMeters = isset($settings['radius_meters']) && is_numeric($settings['radius_meters'])
        ? (int) round((float) $settings['radius_meters'])
        : (int) $defaults['radius_meters'];

    if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
        $latitude = null;
    }

    if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
        $longitude = null;
    }

    $radiusMeters = max(20, min(5000, $radiusMeters));

    return [
        'enabled' => $enabled,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'radius_meters' => $radiusMeters,
        'updated_at' => (string) ($settings['updated_at'] ?? $defaults['updated_at']),
    ];
}

function write_geofence_settings(array $settings): void
{
    $handle = fopen(GEOFENCE_FILE, 'c+');

    if (!$handle) {
        throw new RuntimeException('Unable to open geofence storage.');
    }

    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($settings, JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function update_geofence_settings(string $enabled, string $latitude, string $longitude, string $radiusMeters): array
{
    $isEnabled = $enabled === '1';
    $latitude = trim($latitude);
    $longitude = trim($longitude);
    $radiusMeters = trim($radiusMeters);

    if ($isEnabled) {
        if ($latitude === '' || $longitude === '') {
            return ['ok' => false, 'message' => 'Set both latitude and longitude when geofence is enabled.'];
        }

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return ['ok' => false, 'message' => 'Latitude and longitude must be numeric values.'];
        }
    }

    $parsedLatitude = $latitude === '' ? null : (float) $latitude;
    $parsedLongitude = $longitude === '' ? null : (float) $longitude;

    if ($parsedLatitude !== null && ($parsedLatitude < -90 || $parsedLatitude > 90)) {
        return ['ok' => false, 'message' => 'Latitude must be between -90 and 90.'];
    }

    if ($parsedLongitude !== null && ($parsedLongitude < -180 || $parsedLongitude > 180)) {
        return ['ok' => false, 'message' => 'Longitude must be between -180 and 180.'];
    }

    $parsedRadius = is_numeric($radiusMeters) ? (int) round((float) $radiusMeters) : 150;

    if ($parsedRadius < 20 || $parsedRadius > 5000) {
        return ['ok' => false, 'message' => 'Radius must be between 20 and 5000 meters.'];
    }

    $settings = [
        'enabled' => $isEnabled,
        'latitude' => $parsedLatitude,
        'longitude' => $parsedLongitude,
        'radius_meters' => $parsedRadius,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    write_geofence_settings($settings);

    return ['ok' => true, 'message' => 'Geofence settings updated successfully.'];
}

function geofence_public_settings(): array
{
    $settings = read_geofence_settings();

    return [
        'enabled' => (bool) ($settings['enabled'] ?? false),
        'latitude' => $settings['latitude'],
        'longitude' => $settings['longitude'],
        'radius_meters' => (int) ($settings['radius_meters'] ?? 150),
    ];
}

function geo_distance_meters(float $fromLat, float $fromLon, float $toLat, float $toLon): float
{
    $earthRadius = 6371000.0;
    $fromLatRad = deg2rad($fromLat);
    $toLatRad = deg2rad($toLat);
    $deltaLat = deg2rad($toLat - $fromLat);
    $deltaLon = deg2rad($toLon - $fromLon);

    $a = sin($deltaLat / 2) ** 2
        + cos($fromLatRad) * cos($toLatRad) * sin($deltaLon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

function validate_clock_in_geofence(?array $clockInLocation): array
{
    $settings = read_geofence_settings();

    if (!(bool) ($settings['enabled'] ?? false)) {
        return ['ok' => true, 'location' => null];
    }

    $targetLatitude = isset($settings['latitude']) && is_numeric($settings['latitude']) ? (float) $settings['latitude'] : null;
    $targetLongitude = isset($settings['longitude']) && is_numeric($settings['longitude']) ? (float) $settings['longitude'] : null;
    $radiusMeters = (int) ($settings['radius_meters'] ?? 150);

    if ($targetLatitude === null || $targetLongitude === null) {
        return ['ok' => false, 'message' => 'Clock-in location is not configured. Contact admin.'];
    }

    if (!is_array($clockInLocation)) {
        return ['ok' => false, 'message' => 'Location is required before clocking in. Please allow GPS and try again.'];
    }

    $sourceLatitude = isset($clockInLocation['latitude']) && is_numeric($clockInLocation['latitude'])
        ? (float) $clockInLocation['latitude']
        : null;
    $sourceLongitude = isset($clockInLocation['longitude']) && is_numeric($clockInLocation['longitude'])
        ? (float) $clockInLocation['longitude']
        : null;
    $accuracyMeters = isset($clockInLocation['accuracy_meters']) && is_numeric($clockInLocation['accuracy_meters'])
        ? max(0.0, (float) $clockInLocation['accuracy_meters'])
        : null;

    if ($sourceLatitude === null || $sourceLongitude === null) {
        return ['ok' => false, 'message' => 'Location is required before clocking in. Please allow GPS and try again.'];
    }

    if ($sourceLatitude < -90 || $sourceLatitude > 90 || $sourceLongitude < -180 || $sourceLongitude > 180) {
        return ['ok' => false, 'message' => 'Clock-in location is invalid. Please refresh your location and try again.'];
    }

    $distanceMeters = geo_distance_meters($sourceLatitude, $sourceLongitude, $targetLatitude, $targetLongitude);

    if ($distanceMeters > $radiusMeters) {
        return [
            'ok' => false,
            'message' => 'You are outside the allowed clock-in area. Move closer and try again.',
            'distance_meters' => (int) round($distanceMeters),
            'radius_meters' => $radiusMeters,
        ];
    }

    return [
        'ok' => true,
        'location' => [
            'latitude' => $sourceLatitude,
            'longitude' => $sourceLongitude,
            'accuracy_meters' => $accuracyMeters,
            'distance_meters' => $distanceMeters,
        ],
    ];
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

function normalize_person_name(string $name): string
{
    return trim(preg_replace('/\s+/', ' ', $name) ?? '');
}

function normalize_position(string $position): string
{
    return trim(preg_replace('/\s+/', ' ', $position) ?? '');
}

function register_department(string $departmentName): array
{
    $departmentName = normalize_person_name($departmentName);

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

function ensure_department(string $departmentName): ?array
{
    $departmentName = normalize_person_name($departmentName);

    if ($departmentName === '') {
        return null;
    }

    $departmentId = department_id_from_name($departmentName);
    $departments = read_departments();

    if (isset($departments[$departmentId])) {
        return $departments[$departmentId];
    }

    $result = register_department($departmentName);

    return $result['ok'] ? find_department($departmentId) : null;
}

function update_department(string $departmentId, string $departmentName): array
{
    $departmentId = normalize_department_id($departmentId);
    $departmentName = normalize_person_name($departmentName);
    $departments = read_departments();

    if (!isset($departments[$departmentId])) {
        return ['ok' => false, 'message' => 'Department was not found.'];
    }

    if ($departmentName === '' || !preg_match('/^[A-Za-z0-9 &.\'-]{2,80}$/', $departmentName)) {
        return ['ok' => false, 'message' => 'Enter a valid department name.'];
    }

    $newDepartmentId = department_id_from_name($departmentName);

    if ($newDepartmentId === '') {
        return ['ok' => false, 'message' => 'Enter a valid department name.'];
    }

    if ($newDepartmentId !== $departmentId && isset($departments[$newDepartmentId])) {
        return ['ok' => false, 'message' => 'A department with that name already exists.'];
    }

    $createdAt = $departments[$departmentId]['created_at'] ?? date('Y-m-d H:i:s');
    unset($departments[$departmentId]);
    $departments[$newDepartmentId] = [
        'department_id' => $newDepartmentId,
        'department_name' => $departmentName,
        'created_at' => $createdAt,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    write_departments($departments);

    $employees = read_employees();
    foreach ($employees as $employeeNumber => $employee) {
        if (($employee['department_id'] ?? '') === $departmentId) {
            $employees[$employeeNumber]['department_id'] = $newDepartmentId;
            $employees[$employeeNumber]['department_name'] = $departmentName;
            $employees[$employeeNumber]['updated_at'] = date('Y-m-d H:i:s');
        }
    }
    write_employees($employees);

    $records = read_attendance();
    foreach ($records as $date => $dayRecords) {
        foreach ($dayRecords as $employeeNumber => $record) {
            if (($record['department_id'] ?? '') === $departmentId) {
                $records[$date][$employeeNumber]['department_id'] = $newDepartmentId;
                $records[$date][$employeeNumber]['department_name'] = $departmentName;
            }
        }
    }
    write_attendance($records);

    return ['ok' => true, 'message' => 'Department updated successfully.'];
}

function delete_department(string $departmentId): array
{
    $departmentId = normalize_department_id($departmentId);
    $departments = read_departments();

    if (!isset($departments[$departmentId])) {
        return ['ok' => false, 'message' => 'Department was not found.'];
    }

    unset($departments[$departmentId]);
    write_departments($departments);

    $employees = read_employees();
    foreach ($employees as $employeeNumber => $employee) {
        if (($employee['department_id'] ?? '') === $departmentId) {
            $employees[$employeeNumber]['department_id'] = '';
            $employees[$employeeNumber]['department_name'] = 'Unassigned';
            $employees[$employeeNumber]['updated_at'] = date('Y-m-d H:i:s');
        }
    }
    write_employees($employees);

    $records = read_attendance();
    foreach ($records as $date => $dayRecords) {
        foreach ($dayRecords as $employeeNumber => $record) {
            if (($record['department_id'] ?? '') === $departmentId) {
                $records[$date][$employeeNumber]['department_id'] = '';
                $records[$date][$employeeNumber]['department_name'] = 'Unassigned';
            }
        }
    }
    write_attendance($records);

    return ['ok' => true, 'message' => 'Department deleted. Assigned employees are now unassigned.'];
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

function register_employee(string $employeeNumber, string $employeeName, string $departmentId, string $position = ''): array
{
    $employeeNumber = normalize_employee_number($employeeNumber);
    $employeeName = normalize_person_name($employeeName);
    $position = normalize_position($position);
    $department = normalize_department_id($departmentId) !== '' ? find_department($departmentId) : null;

    if ($employeeNumber === '' || !preg_match('/^[A-Z0-9-]{2,30}$/', $employeeNumber)) {
        return ['ok' => false, 'message' => 'Enter a valid employee number.'];
    }

    if ($employeeName === '' || !preg_match('/^[A-Za-z .\'-]{2,100}$/', $employeeName)) {
        return ['ok' => false, 'message' => 'Enter a valid employee name.'];
    }

    if ($position !== '' && !preg_match('/^[A-Za-z0-9 &.,\'\/()-]{2,100}$/', $position)) {
        return ['ok' => false, 'message' => 'Enter a valid position.'];
    }

    if (normalize_department_id($departmentId) !== '' && $department === null) {
        return ['ok' => false, 'message' => 'Select a registered department.'];
    }

    $employees = read_employees();
    $isUpdate = isset($employees[$employeeNumber]);
    $employees[$employeeNumber] = [
        'employee_number' => $employeeNumber,
        'employee_name' => $employeeName,
        'position' => $position,
        'department_id' => $department['department_id'] ?? '',
        'department_name' => $department['department_name'] ?? 'Unassigned',
        'registered_at' => $employees[$employeeNumber]['registered_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    write_employees($employees);
    sync_employee_details_to_attendance($employeeNumber, $employees[$employeeNumber]);

    return [
        'ok' => true,
        'message' => $isUpdate ? 'Employee updated successfully.' : 'Employee registered successfully.',
    ];
}

function update_employee_record(string $originalEmployeeNumber, string $employeeNumber, string $employeeName, string $departmentId, string $position = ''): array
{
    $originalEmployeeNumber = normalize_employee_number($originalEmployeeNumber);
    $employeeNumber = normalize_employee_number($employeeNumber);

    if ($originalEmployeeNumber === '') {
        return ['ok' => false, 'message' => 'Employee was not found.'];
    }

    $employees = read_employees();

    if (!isset($employees[$originalEmployeeNumber])) {
        return ['ok' => false, 'message' => 'Employee was not found.'];
    }

    if ($employeeNumber !== $originalEmployeeNumber && isset($employees[$employeeNumber])) {
        return ['ok' => false, 'message' => 'Another employee already uses that employee number.'];
    }

    $result = register_employee($employeeNumber, $employeeName, $departmentId, $position);

    if (!$result['ok']) {
        return $result;
    }

    if ($employeeNumber === $originalEmployeeNumber) {
        return ['ok' => true, 'message' => 'Employee updated successfully.'];
    }

    $employees = read_employees();
    $employees[$employeeNumber]['registered_at'] = $employees[$originalEmployeeNumber]['registered_at'] ?? ($employees[$employeeNumber]['registered_at'] ?? date('Y-m-d H:i:s'));
    unset($employees[$originalEmployeeNumber]);
    write_employees($employees);

    $records = read_attendance();

    foreach ($records as $date => $dayRecords) {
        if (!isset($dayRecords[$originalEmployeeNumber])) {
            continue;
        }

        $records[$date][$employeeNumber] = $dayRecords[$originalEmployeeNumber];
        $records[$date][$employeeNumber]['employee_number'] = $employeeNumber;
        $records[$date][$employeeNumber]['employee_name'] = $employees[$employeeNumber]['employee_name'] ?? '';
        $records[$date][$employeeNumber]['position'] = $employees[$employeeNumber]['position'] ?? '';
        $records[$date][$employeeNumber]['department_id'] = $employees[$employeeNumber]['department_id'] ?? '';
        $records[$date][$employeeNumber]['department_name'] = $employees[$employeeNumber]['department_name'] ?? 'Unassigned';
        unset($records[$date][$originalEmployeeNumber]);
        ksort($records[$date]);
    }

    write_attendance($records);

    return ['ok' => true, 'message' => 'Employee number and profile updated successfully.'];
}

function sync_employee_details_to_attendance(string $employeeNumber, array $employee): void
{
    $records = read_attendance();
    $changed = false;

    foreach ($records as $date => $dayRecords) {
        if (!isset($dayRecords[$employeeNumber])) {
            continue;
        }

        $records[$date][$employeeNumber]['employee_name'] = $employee['employee_name'] ?? '';
        $records[$date][$employeeNumber]['position'] = $employee['position'] ?? '';
        $records[$date][$employeeNumber]['department_id'] = $employee['department_id'] ?? '';
        $records[$date][$employeeNumber]['department_name'] = $employee['department_name'] ?? 'Unassigned';
        $changed = true;
    }

    if ($changed) {
        write_attendance($records);
    }
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

function import_cell_value(mixed $value): string
{
    return trim((string) $value);
}

function import_header_key(string $header): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', $header) ?? '');
}

function import_csv_rows(string $path): array
{
    $handle = fopen($path, 'r');

    if (!$handle) {
        throw new RuntimeException('Unable to read the uploaded file.');
    }

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = array_map('import_cell_value', $row);
    }

    fclose($handle);

    return $rows;
}

function import_html_table_rows(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('Unable to read the uploaded file.');
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $loaded = $document->loadHTML($contents);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        throw new RuntimeException('Unable to read the Excel table.');
    }

    $rows = [];

    foreach ($document->getElementsByTagName('tr') as $tr) {
        $row = [];

        foreach ($tr->childNodes as $cell) {
            if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                $row[] = import_cell_value($cell->textContent);
            }
        }

        if (count(array_filter($row, static fn (string $value): bool => $value !== '')) > 0) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function import_xlsx_shared_strings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');

    if ($xml === false) {
        return [];
    }

    $strings = [];
    $reader = new XMLReader();
    $reader->XML($xml);

    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
            $node = new SimpleXMLElement($reader->readOuterXML());
            $textParts = $node->xpath('.//*[local-name()="t"]') ?: [];
            $value = '';

            foreach ($textParts as $part) {
                $value .= (string) $part;
            }

            $strings[] = $value;
        }
    }

    $reader->close();

    return $strings;
}

function import_xlsx_first_sheet_path(ZipArchive $zip): string
{
    $workbook = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

    if ($workbook === false || $rels === false) {
        return 'xl/worksheets/sheet1.xml';
    }

    $workbookXml = new SimpleXMLElement($workbook);
    $relXml = new SimpleXMLElement($rels);
    $workbookXml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbookXml->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $sheets = $workbookXml->xpath('//main:sheet') ?: [];

    if (count($sheets) === 0) {
        return 'xl/worksheets/sheet1.xml';
    }

    $relationId = (string) $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

    foreach ($relXml->Relationship as $relationship) {
        if ((string) $relationship['Id'] === $relationId) {
            $target = (string) $relationship['Target'];
            return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . ltrim($target, '/');
        }
    }

    return 'xl/worksheets/sheet1.xml';
}

function import_xlsx_rows(string $path): array
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('This server cannot read .xlsx files. Upload CSV or .xls instead.');
    }

    $zip = new ZipArchive();

    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open the Excel file.');
    }

    $sharedStrings = import_xlsx_shared_strings($zip);
    $sheetPath = import_xlsx_first_sheet_path($zip);
    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Unable to find the first worksheet in the Excel file.');
    }

    $rows = [];
    $reader = new XMLReader();
    $reader->XML($sheetXml);

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
            continue;
        }

        $rowXml = new SimpleXMLElement($reader->readOuterXML());
        $row = [];

        foreach ($rowXml->children($rowXml->getNamespaces(true)[''] ?? null)->c as $cell) {
            $attributes = $cell->attributes();
            $reference = (string) ($attributes['r'] ?? '');
            $type = (string) ($attributes['t'] ?? '');
            $column = $reference !== '' ? (int) (array_reduce(str_split(preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?: 'A'), static function (int $carry, string $letter): int {
                return ($carry * 26) + (ord($letter) - 64);
            }, 0) - 1) : count($row);
            $value = '';

            if ($type === 's') {
                $value = $sharedStrings[(int) $cell->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]') ?: []));
            } else {
                $value = (string) ($cell->v ?? '');
            }

            $row[$column] = import_cell_value($value);
        }

        if (count($row) > 0) {
            ksort($row);
            $rows[] = array_values($row);
        }
    }

    $reader->close();

    return $rows;
}

function import_spreadsheet_rows(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Choose an Excel or CSV file to import.'];
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'The import file must be 5 MB or smaller.'];
    }

    $path = (string) ($file['tmp_name'] ?? '');
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    try {
        $rows = match ($extension) {
            'csv' => import_csv_rows($path),
            'xls' => import_html_table_rows($path),
            'xlsx' => import_xlsx_rows($path),
            default => throw new RuntimeException('Upload a .xlsx, .xls, or .csv file.'),
        };
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => $exception->getMessage()];
    }

    return ['ok' => true, 'rows' => $rows];
}

function import_excel_serial_date(float $serial): string
{
    $base = new DateTimeImmutable('1899-12-30');
    return $base->modify('+' . (int) floor($serial) . ' days')->format('Y-m-d');
}

function import_excel_serial_time(float $serial): string
{
    $seconds = (int) round(($serial - floor($serial)) * 86400);
    $seconds = max(0, min(86399, $seconds));

    return gmdate('H:i:s', $seconds);
}

function import_date_value(string $value): string
{
    $value = trim($value);

    if ($value === '' || $value === '-') {
        return '';
    }

    if (is_numeric($value) && (float) $value > 1000) {
        return import_excel_serial_date((float) $value);
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('Y-m-d', $timestamp) : '';
}

function import_time_value(string $value): string
{
    $value = trim($value);

    if ($value === '' || $value === '-') {
        return '';
    }

    if (is_numeric($value)) {
        return import_excel_serial_time((float) $value);
    }

    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $value, $matches)) {
        return sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) ($matches[3] ?? 0));
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('H:i:s', $timestamp) : '';
}

function attendance_flags_for_times(string $date, string $clockIn, string $clockOut): array
{
    if ($clockIn === '' && $clockOut === '') {
        return [];
    }

    $flags = [];
    $start = strtotime($date . ' ' . SHIFT_START_TIME);
    $end = strtotime($date . ' ' . SHIFT_END_TIME);
    $in = $clockIn !== '' ? strtotime($date . ' ' . $clockIn) : false;
    $out = $clockOut !== '' ? strtotime($date . ' ' . $clockOut) : false;

    if ($in !== false && $start !== false) {
        $lateSeconds = max(0, $in - $start - (LATE_GRACE_MINUTES * 60));

        if ($lateSeconds > 0) {
            $flags['late'] = true;
            $flags['late_minutes'] = (int) floor($lateSeconds / 60);
        }
    }

    if ($out !== false && $end !== false) {
        $earlySeconds = max(0, $end - $out - (EARLY_OUT_GRACE_MINUTES * 60));

        if ($earlySeconds > 0) {
            $flags['early_out'] = true;
            $flags['early_out_minutes'] = (int) floor($earlySeconds / 60);
        }
    }

    return $flags;
}

function merge_attendance_flags(array $record): array
{
    $date = (string) ($record['date'] ?? '');
    $clockIn = (string) ($record['clock_in'] ?? '');
    $clockOut = (string) ($record['clock_out'] ?? '');

    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        unset($record['flags']);
        return $record;
    }

    $record['flags'] = attendance_flags_for_times($date, $clockIn, $clockOut);

    return $record;
}

function backup_snapshot_payload(): array
{
    return [
        'meta' => [
            'app' => APP_NAME,
            'generated_at' => date('c'),
            'format_version' => 1,
        ],
        'attendance' => read_attendance(),
        'employees' => read_employees(),
        'departments' => read_departments(),
        'geofence' => read_geofence_settings(),
    ];
}

function create_json_backup_snapshot(): array
{
    $payload = backup_snapshot_payload();
    $json = json_encode($payload, JSON_PRETTY_PRINT);

    if ($json === false) {
        return ['ok' => false, 'message' => 'Unable to generate backup snapshot.'];
    }

    return [
        'ok' => true,
        'json' => $json,
        'filename' => 'mys-attendance-backup-' . date('Ymd-His') . '.json',
    ];
}

function restore_from_backup_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Choose a JSON backup file to restore.'];
    }

    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Backup file must be 10 MB or smaller.'];
    }

    $path = (string) ($file['tmp_name'] ?? '');
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

    if ($extension !== 'json') {
        return ['ok' => false, 'message' => 'Upload a valid .json backup file.'];
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        return ['ok' => false, 'message' => 'Unable to read uploaded backup file.'];
    }

    $payload = json_decode($contents, true);

    if (!is_array($payload)) {
        return ['ok' => false, 'message' => 'Backup file is not valid JSON.'];
    }

    $attendance = $payload['attendance'] ?? null;
    $employees = $payload['employees'] ?? null;
    $departments = $payload['departments'] ?? null;
    $geofence = $payload['geofence'] ?? default_geofence_settings();

    if (!is_array($attendance) || !is_array($employees) || !is_array($departments)) {
        return ['ok' => false, 'message' => 'Backup file is missing required data sections.'];
    }

    if (!is_array($geofence)) {
        $geofence = default_geofence_settings();
    }

    foreach ($attendance as $date => $dayRecords) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || !is_array($dayRecords)) {
            return ['ok' => false, 'message' => 'Attendance section in backup is invalid.'];
        }

        foreach ($dayRecords as $employeeNumber => $record) {
            if (!is_array($record)) {
                return ['ok' => false, 'message' => 'Attendance record format is invalid in backup.'];
            }

            if (!isset($record['employee_number'], $record['date'])) {
                return ['ok' => false, 'message' => 'Attendance record missing required fields in backup.'];
            }

            $record['employee_number'] = normalize_employee_number((string) $record['employee_number']);
            $record['date'] = import_date_value((string) $record['date']);
            $record['clock_in'] = import_time_value((string) ($record['clock_in'] ?? ''));
            $record['clock_out'] = import_time_value((string) ($record['clock_out'] ?? ''));
            $record['status'] = $record['clock_in'] !== '' && $record['clock_out'] !== '' ? 'Complete' : 'Incomplete';
            $record['flags'] = attendance_flags_for_times($record['date'], $record['clock_in'], $record['clock_out']);

            if ($record['employee_number'] === '' || $record['date'] === '') {
                return ['ok' => false, 'message' => 'Attendance record contains invalid employee/date values.'];
            }

            $attendance[$date][$employeeNumber] = $record;
        }
    }

    foreach ($employees as $employeeNumber => $employee) {
        if (!is_array($employee) || !isset($employee['employee_number'], $employee['employee_name'])) {
            return ['ok' => false, 'message' => 'Employee section in backup is invalid.'];
        }

        $normalized = normalize_employee_number((string) $employee['employee_number']);

        if ($normalized === '') {
            return ['ok' => false, 'message' => 'Employee number in backup is invalid.'];
        }

        $employees[$employeeNumber]['employee_number'] = $normalized;
    }

    foreach ($departments as $departmentId => $department) {
        if (!is_array($department) || !isset($department['department_id'], $department['department_name'])) {
            return ['ok' => false, 'message' => 'Department section in backup is invalid.'];
        }

        $normalized = normalize_department_id((string) $department['department_id']);

        if ($normalized === '') {
            return ['ok' => false, 'message' => 'Department id in backup is invalid.'];
        }

        $departments[$departmentId]['department_id'] = $normalized;
    }

    write_attendance($attendance);
    write_employees($employees);
    write_departments($departments);
    write_geofence_settings([
        'enabled' => (bool) ($geofence['enabled'] ?? false),
        'latitude' => isset($geofence['latitude']) && is_numeric($geofence['latitude']) ? (float) $geofence['latitude'] : null,
        'longitude' => isset($geofence['longitude']) && is_numeric($geofence['longitude']) ? (float) $geofence['longitude'] : null,
        'radius_meters' => isset($geofence['radius_meters']) && is_numeric($geofence['radius_meters'])
            ? max(20, min(5000, (int) round((float) $geofence['radius_meters'])))
            : 150,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return ['ok' => true, 'message' => 'Backup restored successfully.'];
}

function import_attendance_from_upload(array $file): array
{
    $spreadsheet = import_spreadsheet_rows($file);

    if (!$spreadsheet['ok']) {
        return $spreadsheet;
    }

    $rows = $spreadsheet['rows'];
    $headerIndex = null;
    $columns = [];

    foreach ($rows as $index => $row) {
        $keys = array_map(static fn (string $header): string => import_header_key($header), $row);

        foreach ($keys as $columnIndex => $key) {
            $columns[$key] = $columnIndex;
        }

        if (isset($columns['employeenumber'], $columns['date'])) {
            $headerIndex = $index;
            break;
        }

        $columns = [];
    }

    if ($headerIndex === null) {
        return ['ok' => false, 'message' => 'The sheet needs Employee Number and Date columns.'];
    }

    $records = read_attendance();
    $employees = read_employees();
    $imported = 0;
    $skipped = 0;

    foreach (array_slice($rows, $headerIndex + 1) as $row) {
        $employeeNumber = normalize_employee_number($row[$columns['employeenumber']] ?? '');
        $date = import_date_value($row[$columns['date']] ?? '');

        if ($employeeNumber === '' || !preg_match('/^[A-Z0-9-]{2,30}$/', $employeeNumber) || $date === '') {
            $skipped++;
            continue;
        }

        $employee = $employees[$employeeNumber] ?? [];
        $employeeName = import_cell_value($row[$columns['employeename'] ?? -1] ?? ($employee['employee_name'] ?? ''));
        $position = import_cell_value($row[$columns['position'] ?? -1] ?? ($employee['position'] ?? ''));
        $departmentName = import_cell_value($row[$columns['department'] ?? -1] ?? ($employee['department_name'] ?? 'Unassigned'));
        $department = $departmentName !== '' && $departmentName !== 'Unassigned' ? ensure_department($departmentName) : null;
        $departmentId = $department['department_id'] ?? ($employee['department_id'] ?? '');
        $departmentName = $department['department_name'] ?? $departmentName;
        $clockIn = import_time_value($row[$columns['clockin'] ?? -1] ?? '');
        $clockOut = import_time_value($row[$columns['clockout'] ?? -1] ?? '');
        $existing = $records[$date][$employeeNumber] ?? [];

        if ($clockIn === '' && $clockOut !== '') {
            $skipped++;
            continue;
        }

        if (!isset($records[$date])) {
            $records[$date] = [];
        }

        $record = [
            'employee_number' => $employeeNumber,
            'employee_name' => $employeeName !== '' ? $employeeName : ($existing['employee_name'] ?? ''),
            'position' => $position !== '' ? $position : ($existing['position'] ?? ''),
            'department_id' => $departmentId,
            'department_name' => $departmentName !== '' ? $departmentName : ($existing['department_name'] ?? 'Unassigned'),
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'clock_in_photo' => $existing['clock_in_photo'] ?? '',
            'status' => $clockIn !== '' && $clockOut !== '' ? 'Complete' : 'Incomplete',
        ];

        $records[$date][$employeeNumber] = merge_attendance_flags($record);

        $imported++;
    }

    if ($imported > 0) {
        ksort($records);
        write_attendance($records);
    }

    return [
        'ok' => $imported > 0,
        'message' => $imported . ' attendance record' . ($imported === 1 ? '' : 's') . ' imported. ' . $skipped . ' row' . ($skipped === 1 ? '' : 's') . ' skipped.',
    ];
}

function import_employees_from_upload(array $file): array
{
    $spreadsheet = import_spreadsheet_rows($file);

    if (!$spreadsheet['ok']) {
        return $spreadsheet;
    }

    $rows = $spreadsheet['rows'];
    $headerIndex = null;
    $columns = [];

    foreach ($rows as $index => $row) {
        $keys = array_map(static fn (string $header): string => import_header_key($header), $row);

        foreach ($keys as $columnIndex => $key) {
            $columns[$key] = $columnIndex;
        }

        if (isset($columns['employeenumber'], $columns['employeename'])) {
            $headerIndex = $index;
            break;
        }

        $columns = [];
    }

    if ($headerIndex === null) {
        return ['ok' => false, 'message' => 'The sheet needs Employee Number and Employee Name columns.'];
    }

    $imported = 0;
    $skipped = 0;

    foreach (array_slice($rows, $headerIndex + 1) as $row) {
        $employeeNumber = normalize_employee_number($row[$columns['employeenumber']] ?? '');
        $employeeName = import_cell_value($row[$columns['employeename']] ?? '');
        $position = import_cell_value($row[$columns['position'] ?? -1] ?? '');
        $departmentName = import_cell_value($row[$columns['department'] ?? -1] ?? '');
        $departmentId = '';

        if ($departmentName !== '') {
            $department = ensure_department($departmentName);
            $departmentId = $department['department_id'] ?? '';
        }

        $result = register_employee($employeeNumber, $employeeName, $departmentId, $position);

        if ($result['ok']) {
            $imported++;
        } else {
            $skipped++;
        }
    }

    return [
        'ok' => $imported > 0,
        'message' => $imported . ' employee' . ($imported === 1 ? '' : 's') . ' imported. ' . $skipped . ' row' . ($skipped === 1 ? '' : 's') . ' skipped.',
    ];
}

function update_attendance_record(
    string $originalDate,
    string $originalEmployeeNumber,
    string $employeeNumber,
    string $employeeName,
    string $position,
    string $departmentId,
    string $date,
    string $clockIn,
    string $clockOut
): array {
    $originalDate = import_date_value($originalDate);
    $originalEmployeeNumber = normalize_employee_number($originalEmployeeNumber);
    $employeeNumber = normalize_employee_number($employeeNumber);
    $employeeName = normalize_person_name($employeeName);
    $position = normalize_position($position);
    $departmentId = normalize_department_id($departmentId);
    $date = import_date_value($date);
    $clockIn = import_time_value($clockIn);
    $clockOut = import_time_value($clockOut);

    if ($originalDate === '' || $originalEmployeeNumber === '') {
        return ['ok' => false, 'message' => 'Attendance record was not found.'];
    }

    if ($employeeNumber === '' || !preg_match('/^[A-Z0-9-]{2,30}$/', $employeeNumber)) {
        return ['ok' => false, 'message' => 'Enter a valid employee number.'];
    }

    if ($employeeName === '' || !preg_match('/^[A-Za-z .\'-]{2,100}$/', $employeeName)) {
        return ['ok' => false, 'message' => 'Enter a valid employee name.'];
    }

    if ($position !== '' && !preg_match('/^[A-Za-z0-9 &.,\'\/()-]{2,100}$/', $position)) {
        return ['ok' => false, 'message' => 'Enter a valid position.'];
    }

    if ($date === '') {
        return ['ok' => false, 'message' => 'Enter a valid attendance date.'];
    }

    if ($clockIn === '' && $clockOut !== '') {
        return ['ok' => false, 'message' => 'Clock in must be set before clock out.'];
    }

    if ($clockIn !== '' && $clockOut !== '' && strtotime($date . ' ' . $clockOut) < strtotime($date . ' ' . $clockIn)) {
        return ['ok' => false, 'message' => 'Clock out cannot be earlier than clock in.'];
    }

    $department = $departmentId !== '' ? find_department($departmentId) : null;

    if ($departmentId !== '' && $department === null) {
        return ['ok' => false, 'message' => 'Select a registered department.'];
    }

    $records = read_attendance();
    $existing = $records[$originalDate][$originalEmployeeNumber] ?? null;

    if (!is_array($existing)) {
        return ['ok' => false, 'message' => 'Attendance record was not found.'];
    }

    unset($records[$originalDate][$originalEmployeeNumber]);

    if (isset($records[$originalDate]) && count($records[$originalDate]) === 0) {
        unset($records[$originalDate]);
    }

    if (!isset($records[$date])) {
        $records[$date] = [];
    }

    $record = [
        'employee_number' => $employeeNumber,
        'employee_name' => $employeeName,
        'position' => $position,
        'department_id' => $department['department_id'] ?? '',
        'department_name' => $department['department_name'] ?? 'Unassigned',
        'date' => $date,
        'clock_in' => $clockIn,
        'clock_out' => $clockOut,
        'clock_in_photo' => $existing['clock_in_photo'] ?? '',
        'status' => $clockIn !== '' && $clockOut !== '' ? 'Complete' : 'Incomplete',
    ];

    $records[$date][$employeeNumber] = merge_attendance_flags($record);

    ksort($records);
    write_attendance($records);

    return ['ok' => true, 'message' => 'Attendance record updated successfully.'];
}

function delete_attendance_record(string $date, string $employeeNumber): array
{
    $date = import_date_value($date);
    $employeeNumber = normalize_employee_number($employeeNumber);
    $records = read_attendance();

    if ($date === '' || !isset($records[$date][$employeeNumber])) {
        return ['ok' => false, 'message' => 'Attendance record was not found.'];
    }

    unset($records[$date][$employeeNumber]);

    if (isset($records[$date]) && count($records[$date]) === 0) {
        unset($records[$date]);
    }

    write_attendance($records);

    return ['ok' => true, 'message' => 'Attendance record deleted successfully.'];
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

function delete_clock_in_photo_file(string $relativePath): void
{
    $relativePath = trim($relativePath);

    if ($relativePath === '' || !str_starts_with($relativePath, 'storage/photos/')) {
        return;
    }

    $absolutePath = __DIR__ . '/' . $relativePath;

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function format_minutes_duration(int $minutes): string
{
    $minutes = max(0, $minutes);
    $hours = intdiv($minutes, 60);
    $remainingMinutes = $minutes % 60;

    if ($hours === 0) {
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }

    if ($remainingMinutes === 0) {
        return $hours . ' hour' . ($hours === 1 ? '' : 's');
    }

    return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ' . $remainingMinutes . ' minute' . ($remainingMinutes === 1 ? '' : 's');
}

function record_attendance_action(string $employeeNumber, string $action, string $photoData = '', ?DateTimeInterface $recordedAt = null, ?array $clockInLocation = null): array
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
    $position = $employee['position'] ?? '';
    $departmentId = $employee['department_id'] ?? '';
    $departmentName = $employee['department_name'] ?? 'Unassigned';
    $recordedAt ??= new DateTimeImmutable('now');
    $date = $recordedAt->format('Y-m-d');
    $time = $recordedAt->format('H:i:s');

    $savedClockInPhoto = '';
    $validLocation = null;

    if ($action === 'clock_in') {
        $geofenceValidation = validate_clock_in_geofence($clockInLocation);

        if (!$geofenceValidation['ok']) {
            return ['ok' => false, 'message' => (string) ($geofenceValidation['message'] ?? 'Unable to validate clock-in location.')];
        }

        $validLocation = $geofenceValidation['location'] ?? null;

        try {
            $savedClockInPhoto = save_clock_in_photo($photoData, $employeeNumber, $date, $time);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }
    }

    $result = mutate_attendance(static function (array &$records) use (
        $action,
        $date,
        $departmentId,
        $departmentName,
        $employeeName,
        $employeeNumber,
        $position,
        $savedClockInPhoto,
        $time,
        $validLocation
    ): array {
        if (!isset($records[$date])) {
            $records[$date] = [];
        }

        if (!isset($records[$date][$employeeNumber])) {
            $record = [
                'employee_number' => $employeeNumber,
                'employee_name' => $employeeName,
                'position' => $position,
                'department_id' => $departmentId,
                'department_name' => $departmentName,
                'date' => $date,
                'clock_in' => '',
                'clock_out' => '',
                'clock_in_photo' => '',
                'status' => 'Incomplete',
            ];

            $records[$date][$employeeNumber] = merge_attendance_flags($record);
        }

        $record = $records[$date][$employeeNumber];
        $record['employee_name'] = $employeeName;
        $record['position'] = $position;
        $record['department_id'] = $departmentId;
        $record['department_name'] = $departmentName;

        if ($action === 'clock_in') {
            if ($record['clock_in'] !== '') {
                return ['ok' => false, 'message' => 'This employee has already clocked in today.'];
            }

            $record['clock_in_photo'] = $savedClockInPhoto;

            if (is_array($validLocation)) {
                $record['clock_in_latitude'] = $validLocation['latitude'] ?? null;
                $record['clock_in_longitude'] = $validLocation['longitude'] ?? null;
                $record['clock_in_accuracy_m'] = $validLocation['accuracy_meters'] ?? null;
            }

            $record['clock_in'] = $time;
            $clockInFlags = attendance_flags_for_times($date, $time, '');
            $lateMinutes = (int) ($clockInFlags['late_minutes'] ?? 0);
            $message = 'Clock in recorded for ' . $employeeName . ' at ' . $time . '.';
            $title = 'Welcome to work';
            $body = 'Welcome to work this morning. Your clock in has been recorded successfully.';

            if ($lateMinutes > 0) {
                $lateDuration = format_minutes_duration($lateMinutes);
                $title = 'Clock in recorded';
                $body = 'Clock in recorded. You are late by ' . $lateDuration . '.';
            }
        } else {
            if ($record['clock_in'] === '') {
                return ['ok' => false, 'message' => 'You must clock in first before you can clock out.'];
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
        $records[$date][$employeeNumber] = merge_attendance_flags($record);

        $lateMinutesResult = 0;
        $lateDurationResult = '';

        if ($action === 'clock_in') {
            $lateMinutesResult = (int) (($records[$date][$employeeNumber]['flags']['late_minutes'] ?? 0));
            $lateDurationResult = $lateMinutesResult > 0 ? format_minutes_duration($lateMinutesResult) : '';
        }

        return [
            'ok' => true,
            'message' => $message,
            'title' => $title,
            'body' => $body,
            'employee_number' => $employeeNumber,
            'employee_name' => $employeeName,
            'position' => $position,
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'time' => $time,
            'action' => $action,
            'is_late' => $action === 'clock_in' && $lateMinutesResult > 0,
            'late_minutes' => $lateMinutesResult,
            'late_duration' => $lateDurationResult,
        ];
    });

    if (!$result['ok'] && $savedClockInPhoto !== '') {
        delete_clock_in_photo_file($savedClockInPhoto);
    }

    return $result;
}

function employee_attendance_action(string $employeeNumber, string $action, string $photoData = '', ?array $clockInLocation = null): array
{
    return record_attendance_action($employeeNumber, $action, $photoData, null, $clockInLocation);
}

function sync_offline_attendance(array $items): array
{
    $prepared = [];
    $results = [];

    foreach ($items as $index => $item) {
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

        $prepared[] = [
            'id' => $id,
            'item' => $item,
            'recorded_at' => $recordedAt,
            'index' => $index,
        ];
    }

    usort($prepared, static function (array $a, array $b): int {
        /** @var DateTimeInterface $aTime */
        $aTime = $a['recorded_at'];
        /** @var DateTimeInterface $bTime */
        $bTime = $b['recorded_at'];

        $timeCompare = $aTime->getTimestamp() <=> $bTime->getTimestamp();

        if ($timeCompare !== 0) {
            return $timeCompare;
        }

        $aAction = (string) (($a['item']['action'] ?? ''));
        $bAction = (string) (($b['item']['action'] ?? ''));

        if ($aAction !== $bAction) {
            if ($aAction === 'clock_in') {
                return -1;
            }

            if ($bAction === 'clock_in') {
                return 1;
            }
        }

        return $a['index'] <=> $b['index'];
    });

    foreach ($prepared as $entry) {
        $item = $entry['item'];
        /** @var DateTimeInterface $recordedAt */
        $recordedAt = $entry['recorded_at'];
        $id = $entry['id'];

        $result = record_attendance_action(
            (string) ($item['employee_number'] ?? ''),
            (string) ($item['action'] ?? ''),
            (string) ($item['clock_in_photo'] ?? ''),
            $recordedAt,
            is_array($item['clock_in_location'] ?? null) ? $item['clock_in_location'] : null
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

            if (($record['position'] ?? '') === '') {
                $dayRecords[$index]['position'] = $employees[$employeeNumber]['position'] ?? '';
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

function attendance_for_month(string $month, string $departmentId = ''): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return [];
    }

    $records = read_attendance();
    $monthRecords = [];

    foreach ($records as $date => $_dayRecords) {
        if (!str_starts_with((string) $date, $month . '-')) {
            continue;
        }

        $dayRecords = attendance_for_date((string) $date, $departmentId);

        if (count($dayRecords) > 0) {
            $monthRecords[$date] = $dayRecords;
        } else {
            $monthRecords[$date] = [];
        }
    }

    ksort($monthRecords);

    return $monthRecords;
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

function employee_clock_in_status(string $employeeNumber, ?string $date = null): array
{
    $employeeNumber = normalize_employee_number($employeeNumber);
    $date ??= date('Y-m-d');

    if ($employeeNumber === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return ['clocked_in' => false, 'clocked_out' => false, 'date' => $date];
    }

    $records = read_attendance();
    $record = $records[$date][$employeeNumber] ?? null;

    return [
        'clocked_in' => $record !== null && ($record['clock_in'] ?? '') !== '',
        'clocked_out' => $record !== null && ($record['clock_out'] ?? '') !== '',
        'date' => $date,
    ];
}
