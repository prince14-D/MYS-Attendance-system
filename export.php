<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

require_admin();

$date = $_GET['date'] ?? date('Y-m-d');
$format = strtolower($_GET['format'] ?? 'csv');
$departmentId = normalize_department_id($_GET['department'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$department = $departmentId !== '' ? find_department($departmentId) : null;

if ($departmentId !== '' && $department === null) {
    $departmentId = '';
}

$records = attendance_for_date($date, $departmentId);
$departmentName = $department ? $department['department_name'] : 'All Departments';

function export_resolve_record(array $record, array $employees): array
{
    $employeeNumber = normalize_employee_number((string) ($record['employee_number'] ?? ''));
    $employee = $employeeNumber !== '' ? ($employees[$employeeNumber] ?? null) : null;

    return [
        'date' => (string) ($record['date'] ?? ''),
        'employee_number' => $employeeNumber !== '' ? $employeeNumber : '-',
        'employee_name' => trim((string) ($record['employee_name'] ?? ($employee['employee_name'] ?? ''))),
        'position' => trim((string) ($record['position'] ?? ($employee['position'] ?? ''))),
        'department_name' => trim((string) ($record['department_name'] ?? ($employee['department_name'] ?? 'Unassigned'))),
        'clock_in' => trim((string) ($record['clock_in'] ?? '')),
        'clock_out' => trim((string) ($record['clock_out'] ?? '')),
        'status' => (string) ($record['status'] ?? ''),
        'worked_hours' => worked_hours($record),
        'clock_in_photo' => (string) ($record['clock_in_photo'] ?? ''),
    ];
}

function export_rows(array $records, string $date, string $departmentName): array
{
    $employees = read_employees();
    $rows = [
        [APP_NAME],
        ['Daily Attendance Records'],
        ['Date: ' . $date],
        ['Department: ' . $departmentName],
        [''],
    ];

    $rows[] = [
        'Date',
        'Employee Number',
        'Employee Name',
        'Position',
        'Department',
        'Clock In',
        'Clock Out',
        'Hours Work',
        'Status',
    ];

    foreach ($records as $record) {
        $resolved = export_resolve_record($record, $employees);

        $rows[] = [
            $resolved['date'] !== '' ? $resolved['date'] : $date,
            $resolved['employee_number'],
            $resolved['employee_name'] !== '' ? $resolved['employee_name'] : '-',
            $resolved['position'] !== '' ? $resolved['position'] : '-',
            $resolved['department_name'] !== '' ? $resolved['department_name'] : 'Unassigned',
            $resolved['clock_in'] !== '' ? $resolved['clock_in'] : '-',
            $resolved['clock_out'] !== '' ? $resolved['clock_out'] : '-',
            $resolved['worked_hours'] !== '' ? $resolved['worked_hours'] : '-',
            $resolved['status'] !== '' ? $resolved['status'] : '-',
        ];
    }

    if (count($records) === 0) {
        $rows[] = ['No attendance records found for this date.', '', '', '', '', '', '', '', ''];
    }

    return $rows;
}

function download_csv(array $rows, string $date): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-' . $date . '.csv"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    foreach ($rows as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

function download_xls(array $rows, string $date): never
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-' . $date . '.xls"');

    echo "\xEF\xBB\xBF";
    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<table border="1">';

    foreach ($rows as $index => $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            $tag = $index < 6 ? 'th' : 'td';
            echo '<' . $tag . '>' . h((string) $cell) . '</' . $tag . '>';
        }
        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;
}

function pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function pdf_text(string $text, int $x, int $y, int $size = 10, string $font = 'F1'): string
{
    return "BT\n/" . $font . ' ' . $size . " Tf\n" . $x . ' ' . $y . " Td\n(" . pdf_escape($text) . ") Tj\nET\n";
}

function pdf_estimate_text_width(string $text, int $size): float
{
    return strlen($text) * ($size * 0.6);
}

function pdf_fit_text(string $text, int $x, int $y, int $maxWidth, int $size = 10, string $font = 'F1', int $minSize = 8): string
{
    $text = trim($text);

    while ($size > $minSize && pdf_estimate_text_width($text, $size) > $maxWidth) {
        $size--;
    }

    return pdf_text($text, $x, $y, $size, $font);
}

function pdf_shorten_text(string $text, int $maxWidth, int $size = 10): string
{
    $text = trim($text);

    if ($text === '') {
        return '-';
    }

    if (pdf_estimate_text_width($text, $size) <= $maxWidth) {
        return $text;
    }

    while ($text !== '' && pdf_estimate_text_width($text . '...', $size) > $maxWidth) {
        $text = substr($text, 0, -1);
    }

    return $text === '' ? '-' : $text . '...';
}

function pdf_line(int $x1, int $y1, int $x2, int $y2): string
{
    return $x1 . ' ' . $y1 . ' m ' . $x2 . ' ' . $y2 . " l S\n";
}

function pdf_rect(int $x, int $y, int $width, int $height, string $fill = ''): string
{
    return $fill . "\n" . $x . ' ' . $y . ' ' . $width . ' ' . $height . " re " . ($fill === '' ? 'S' : 'f') . "\n";
}

function pdf_logo_block(int $x, int $y): string
{
    $content = "0.07 0.25 0.48 rg\n";
    $content .= pdf_rect($x, $y, 34, 34, '0.07 0.25 0.48 rg');
    $content .= pdf_rect($x + 2, $y + 2, 30, 30, '1 1 1 rg');
    $content .= pdf_rect($x, $y, 34, 8, '0.78 0.12 0.20 rg');
    $content .= pdf_rect($x + 9, $y + 11, 16, 4, '0.07 0.25 0.48 rg');
    $content .= pdf_rect($x + 9, $y + 18, 16, 4, '0.07 0.25 0.48 rg');
    $content .= pdf_rect($x + 9, $y + 25, 11, 4, '0.07 0.25 0.48 rg');
    $content .= pdf_rect($x + 22, $y + 22, 6, 6, '0.78 0.12 0.20 rg');

    return $content;
}

function build_simple_pdf(array $records, string $date, string $departmentName): string
{
    $employees = read_employees();
    $completeCount = count(array_filter($records, static fn (array $record): bool => ($record['status'] ?? '') === 'Complete'));
    $incompleteCount = count($records) - $completeCount;
    $visibleRecords = array_slice($records, 0, 15);

    $content = "0.97 0.98 1 rg\n0 0 842 595 re f\n";
    $content .= "0.07 0.25 0.48 rg\n0 522 842 73 re f\n";
    $content .= "0.78 0.12 0.20 rg\n0 517 842 5 re f\n";
    $content .= pdf_logo_block(36, 536);
    $content .= "1 1 1 rg\n";
    $content .= pdf_text(APP_NAME, 86, 566, 16, 'F2');
    $content .= pdf_text('Daily Attendance Report', 86, 549, 11, 'F2');
    $content .= pdf_text('Date: ' . $date, 86, 533, 10);
    $content .= pdf_text('Department: ' . $departmentName, 280, 533, 10);
    $content .= pdf_text('Generated: ' . date('Y-m-d H:i'), 606, 533, 9);

    $summaryCards = [
        [38, 443, 172, 42, 'Total Records', (string) count($records)],
        [228, 443, 172, 42, 'Complete', (string) $completeCount],
        [418, 443, 172, 42, 'Incomplete', (string) $incompleteCount],
        [608, 443, 196, 42, 'Scope', substr($departmentName, 0, 28)],
    ];

    foreach ($summaryCards as [$x, $y, $width, $height, $label, $value]) {
        $content .= "0.93 0.95 0.98 rg\n" . $x . ' ' . $y . ' ' . $width . ' ' . $height . " re f\n";
        $content .= "0.07 0.25 0.48 rg\n";
        $content .= pdf_text($label, $x + 12, $y + 24, 8, 'F2');
        $content .= pdf_text($value, $x + 12, $y + 10, 13, 'F2');
    }

    $content .= "0.07 0.25 0.48 rg\n38 391 790 24 re f\n";
    $content .= "1 1 1 rg\n";
    $headers = [
        ['Employee Number', 50],
        ['Employee Name', 142],
        ['Department', 372],
        ['Clock In', 522],
        ['Clock Out', 617],
        ['Hours Work', 712],
        ['Status', 778],
    ];

    foreach ($headers as [$label, $x]) {
        $content .= pdf_text($label, $x, 399, 9, 'F2');
    }

    $content .= "0 0 0 rg\n";
    $y = 373;
    $rowHeight = 17;

    foreach ($visibleRecords as $index => $record) {
        $resolved = export_resolve_record($record, $employees);

        if ($index % 2 === 0) {
            $content .= "0.97 0.98 0.99 rg\n38 " . ($y - 12) . " 790 " . $rowHeight . " re f\n";
        }

        $content .= "0.84 0.87 0.91 RG\n";
        $content .= pdf_line(38, $y - 12, 828, $y - 12);

        $values = [
            [substr($resolved['employee_number'], 0, 18), 50, 84],
            [$resolved['employee_name'] !== '' ? $resolved['employee_name'] : '-', 142, 218],
            [$resolved['department_name'] !== '' ? $resolved['department_name'] : '-', 372, 136],
            [$resolved['clock_in'] !== '' ? $resolved['clock_in'] : '-', 522, 84],
            [$resolved['clock_out'] !== '' ? $resolved['clock_out'] : '-', 617, 84],
            [$resolved['worked_hours'] !== '' ? $resolved['worked_hours'] : '-', 712, 58],
            [$resolved['status'] !== '' ? $resolved['status'] : '-', 778, 50],
        ];

        foreach ($values as [$value, $x, $maxWidth]) {
            $content .= pdf_text(pdf_shorten_text((string) $value, $maxWidth, 9), $x, $y, 9);
        }

        $y -= $rowHeight;
    }

    if (count($records) === 0) {
        $content .= pdf_text('No attendance records found for this date and department.', 58, 335, 10, 'F2');
    } elseif (count($records) > 15) {
        $content .= pdf_text('Only the first 15 records are shown on this PDF page. Export CSV or Excel for the complete list.', 58, $y - 4, 9);
    }

    $content .= "0.07 0.25 0.48 rg\n";
    $content .= pdf_text('Authorization', 38, 90, 11, 'F2');
    $content .= "0 0 0 rg\n";
    $content .= pdf_line(250, 74, 430, 74);
    $content .= pdf_text('Prepared By', 295, 57, 9);
    $content .= pdf_line(525, 74, 705, 74);
    $content .= pdf_text('Approved By', 572, 57, 9);

    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>";
    $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $number => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($number + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    return $pdf;
}

function download_pdf(array $records, string $date, string $departmentName): never
{
    $pdf = build_simple_pdf($records, $date, $departmentName);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="attendance-' . $date . '.pdf"');
    header('Content-Length: ' . strlen($pdf));

    echo $pdf;
    exit;
}

$rows = export_rows($records, $date, $departmentName);

match ($format) {
    'csv' => download_csv($rows, $date),
    'xls', 'excel' => download_xls($rows, $date),
    'pdf' => download_pdf($records, $date, $departmentName),
    default => download_csv($rows, $date),
};
