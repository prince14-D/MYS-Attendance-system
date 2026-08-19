<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_roles(['admin']);

$employeeNumber = normalize_employee_number((string) ($_GET['employee'] ?? ''));
$employee = find_employee($employeeNumber);
if ($employee === null) {
    http_response_code(404);
    exit('Employee not found.');
}

$rows = [];
foreach (read_attendance() as $date => $dayRecords) {
    $record = $dayRecords[$employeeNumber] ?? null;
    if (is_array($record)) $rows[] = $record;
}
usort($rows, static fn (array $a, array $b): int => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));

function employee_pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function employee_pdf_text(string $text, int $x, int $y, int $size = 10, string $font = 'F1'): string
{
    return "BT\n/" . $font . ' ' . $size . " Tf\n" . $x . ' ' . $y . " Td\n(" . employee_pdf_escape($text) . ") Tj\nET\n";
}

function employee_build_pdf(array $employee, array $rows, string $employeeNumber): string
{
    $headerY = 790;
    $content = "0 0 0 rg\n";
    $content .= employee_pdf_text(APP_NAME, 50, $headerY, 18, 'F2');
    $content .= employee_pdf_text('Employee Attendance Record', 50, $headerY - 26, 14, 'F2');
    $content .= employee_pdf_text('Employee: ' . ($employee['employee_name'] ?? ''), 50, $headerY - 52, 11);
    $content .= employee_pdf_text('Employee Number: ' . $employeeNumber, 50, $headerY - 70, 11);
    $content .= employee_pdf_text('Department: ' . ($employee['department_name'] ?? 'Unassigned'), 50, $headerY - 88, 11);
    $content .= "0.85 0.9 0.96 rg\n50 120 495 22 re f\n0 0 0 rg\n";
    $content .= employee_pdf_text('Date', 70, 130, 10, 'F2');
    $content .= employee_pdf_text('Clock In', 190, 130, 10, 'F2');
    $content .= employee_pdf_text('Clock Out', 300, 130, 10, 'F2');
    $content .= employee_pdf_text('Worked Hours', 410, 130, 10, 'F2');
    $content .= employee_pdf_text('Status', 500, 130, 10, 'F2');

    $y = 100;
    foreach ($rows as $record) {
        $y -= 22;
        if ($y < 60) {
            break;
        }

        $date = (string) ($record['date'] ?? '');
        $clockIn = (string) (($record['clock_in'] ?? '') ?: '-');
        $clockOut = (string) (($record['clock_out'] ?? '') ?: '-');
        $workedHours = (string) (worked_hours($record) ?: '-');
        $status = (string) ($record['status'] ?? 'Incomplete');

        $content .= employee_pdf_text($date, 70, $y, 9);
        $content .= employee_pdf_text($clockIn, 190, $y, 9);
        $content .= employee_pdf_text($clockOut, 300, $y, 9);
        $content .= employee_pdf_text($workedHours, 410, $y, 9);
        $content .= employee_pdf_text($status, 500, $y, 9);
    }

    if (count($rows) === 0) {
        $content .= employee_pdf_text('No attendance records found.', 130, 140, 12, 'F2');
    }

    $objects = [
        "<< /Type /Catalog /Pages 2 0 R >>",
        "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 4 0 R >> >> /Contents 5 0 R >>",
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream"
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    return $pdf;
}

$format = strtolower((string) ($_GET['format'] ?? 'csv'));
if ($format === 'pdf') {
    $pdf = employee_build_pdf($employee, $rows, $employeeNumber);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="attendance-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $employeeNumber) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $employeeNumber) . '.csv"');
$output = fopen('php://output', 'w');
fwrite($output, "ï»¿");
fputcsv($output, [APP_NAME]);
fputcsv($output, ['Employee Attendance Record']);
fputcsv($output, ['Employee', $employee['employee_name'] ?? '']);
fputcsv($output, ['Employee Number', $employeeNumber]);
fputcsv($output, ['Department', $employee['department_name'] ?? 'Unassigned']);
fputcsv($output, []);
fputcsv($output, ['Date', 'Clock In', 'Clock Out', 'Worked Hours', 'Status']);
foreach ($rows as $record) fputcsv($output, [(string) ($record['date'] ?? ''), (string) (($record['clock_in'] ?? '') ?: '-'), (string) (($record['clock_out'] ?? '') ?: '-'), worked_hours($record) ?: '-', (string) ($record['status'] ?? 'Incomplete')]);
if (count($rows) === 0) fputcsv($output, ['No attendance records found.']);
fclose($output);
