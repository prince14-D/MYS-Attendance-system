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

        $records[$date][$employeeNumber] = [
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

    $records[$date][$employeeNumber] = [
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
    $position = $employee['position'] ?? '';
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
            'position' => $position,
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
    $record['position'] = $position;
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
    $records[$date][$employeeNumber] = $record;
    write_attendance($records);

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
