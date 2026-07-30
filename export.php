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

function export_rows(array $records, string $date, string $departmentName): array
{
    $rows = [
        [APP_NAME],
        ['Daily Attendance Records'],
        ['Date: ' . $date],
        ['Department: ' . $departmentName],
        [''],
    ];

    $rows[] = [
        'Employee Number',
        'Employee Name',
        'Position',
        'Department',
        'Date',
        'Clock In',
        'Clock In Photo',
        'Clock Out',
        'Worked Hours',
        'Status',
    ];

    foreach ($records as $record) {
        $rows[] = [
            $record['employee_number'],
            $record['employee_name'] ?? '-',
            $record['position'] ?? '-',
            $record['department_name'] ?? 'Unassigned',
            $record['date'],
            $record['clock_in'] ?: '-',
            ($record['clock_in_photo'] ?? '') ?: '-',
            $record['clock_out'] ?: '-',
            worked_hours($record) ?: '-',
            $record['status'],
        ];
    }

    if (count($records) === 0) {
        $rows[] = ['No attendance records found for this date.', '', '', '', '', '', '', '', '', ''];
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

function pdf_line(int $x1, int $y1, int $x2, int $y2): string
{
    return $x1 . ' ' . $y1 . ' m ' . $x2 . ' ' . $y2 . " l S\n";
}

function pdf_rect(int $x, int $y, int $width, int $height, string $fill = ''): string
{
    return $fill . "\n" . $x . ' ' . $y . ' ' . $width . ' ' . $height . " re " . ($fill === '' ? 'S' : 'f') . "\n";
}

function build_simple_pdf(array $records, string $date, string $departmentName): string
{
    $completeCount = count(array_filter($records, static fn (array $record): bool => ($record['status'] ?? '') === 'Complete'));
    $incompleteCount = count($records) - $completeCount;

    $content = "0.07 0.25 0.48 rg\n0 535 842 60 re f\n";
    $content .= "0.78 0.12 0.20 rg\n0 530 842 5 re f\n";
    $content .= "1 1 1 rg\n";
    $content .= pdf_text(APP_NAME, 38, 566, 16, 'F2');
    $content .= pdf_text('Daily Attendance Record', 38, 548, 11);

    $content .= "0 0 0 rg\n";
    $content .= pdf_text('Date: ' . $date, 38, 508, 11, 'F2');
    $content .= pdf_text('Department: ' . $departmentName, 180, 508, 11, 'F2');
    $content .= pdf_text('Generated: ' . date('Y-m-d H:i'), 590, 508, 10);

    $content .= "0.93 0.95 0.98 rg\n38 462 174 30 re f\n232 462 174 30 re f\n426 462 174 30 re f\n620 462 184 30 re f\n";
    $content .= "0.07 0.25 0.48 rg\n";
    $content .= pdf_text('Total Records', 50, 480, 9, 'F2');
    $content .= pdf_text((string) count($records), 154, 480, 11, 'F2');
    $content .= pdf_text('Complete', 244, 480, 9, 'F2');
    $content .= pdf_text((string) $completeCount, 348, 480, 11, 'F2');
    $content .= pdf_text('Incomplete', 438, 480, 9, 'F2');
    $content .= pdf_text((string) $incompleteCount, 552, 480, 11, 'F2');
    $content .= pdf_text('Department', 632, 480, 9, 'F2');
    $content .= pdf_text(substr($departmentName, 0, 18), 718, 480, 9, 'F2');

    $content .= "0.07 0.25 0.48 rg\n38 426 766 26 re f\n";
    $content .= "1 1 1 rg\n";
    $content .= "0.07 0.25 0.48 rg\n";
    $headers = [
        ['Employee Number', 58],
        ['Employee Name', 202],
        ['Clock In', 430],
        ['Clock Out', 558],
        ['Hours Work', 690],
    ];

    foreach ($headers as [$label, $x]) {
        $content .= "1 1 1 rg\n";
        $content .= pdf_text($label, $x, 435, 10, 'F2');
    }

    $content .= "0 0 0 rg\n";
    $y = 404;

    foreach (array_slice($records, 0, 15) as $index => $record) {
        if ($index % 2 === 0) {
            $content .= "0.98 0.99 1 rg\n38 " . ($y - 9) . " 766 24 re f\n";
            $content .= "0 0 0 rg\n";
        }

        $content .= pdf_line(38, $y - 11, 804, $y - 11);
        $values = [
            [substr((string) $record['employee_number'], 0, 18), 58],
            [substr((string) ($record['employee_name'] ?? '-'), 0, 30), 202],
            [(string) ($record['clock_in'] ?: '-'), 430],
            [(string) ($record['clock_out'] ?: '-'), 558],
            [(string) (worked_hours($record) ?: '-'), 690],
        ];

        foreach ($values as [$value, $x]) {
            $content .= pdf_text($value, $x, $y, 10);
        }

        $y -= 26;
    }

    if (count($records) === 0) {
        $content .= pdf_text('No attendance records found for this date and department.', 58, 398, 10);
    } elseif (count($records) > 15) {
        $content .= pdf_text('Only the first 15 records are shown on this PDF page. Export CSV or Excel for the complete list.', 58, $y, 9);
    }

    $content .= "0.07 0.25 0.48 rg\n";
    $content .= pdf_text('Authorization', 38, 92, 11, 'F2');
    $content .= "0 0 0 rg\n";
    $content .= pdf_line(250, 72, 430, 72);
    $content .= pdf_text('Prepared By', 295, 55, 9);
    $content .= pdf_line(525, 72, 705, 72);
    $content .= pdf_text('Approved By', 572, 55, 9);

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
