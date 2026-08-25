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

function employee_pdf_rect(int $x, int $y, int $width, int $height, string $fill): string
{
    return $fill . "\n" . $x . ' ' . $y . ' ' . $width . ' ' . $height . " re f\n";
}

function employee_pdf_line(int $x1, int $y1, int $x2, int $y2): string
{
    return "0.82 0.86 0.92 RG\n" . $x1 . ' ' . $y1 . ' m ' . $x2 . ' ' . $y2 . " l S\n";
}

function employee_pdf_fit_text(string $text, int $x, int $y, int $maxWidth, int $size = 10, string $font = 'F1'): string
{
    $text = trim($text);
    while ($size > 7 && strlen($text) * $size * 0.52 > $maxWidth) $size--;
    if (strlen($text) * $size * 0.52 > $maxWidth) {
        $maxChars = max(1, (int) floor($maxWidth / ($size * 0.52)) - 3);
        $text = substr($text, 0, $maxChars) . '...';
    }
    return employee_pdf_text($text, $x, $y, $size, $font);
}

function employee_build_pdf(array $employee, array $rows, string $employeeNumber): string
{
    $complete = count(array_filter($rows, static fn (array $record): bool => ($record['status'] ?? '') === 'Complete'));
    $totalMinutes = 0;
    foreach ($rows as $record) {
        $worked = worked_hours($record);
        if ($worked !== '') {
            [$hours, $minutes] = array_map('intval', explode(':', $worked));
            $totalMinutes += ($hours * 60) + $minutes;
        }
    }
    $pageSize = 25;
    $pageCount = max(1, (int) ceil(count($rows) / $pageSize));
    $contents = [];

    for ($page = 0; $page < $pageCount; $page++) {
        $content = employee_pdf_rect(0, 0, 595, 842, '0.97 0.98 0.99 rg');
        $content .= employee_pdf_rect(0, 770, 595, 72, '0.07 0.25 0.48 rg');
        $content .= employee_pdf_rect(0, 770, 595, 6, '0.78 0.12 0.20 rg');
        $content .= "1 1 1 rg\n";
        $content .= employee_pdf_text(APP_NAME, 42, 814, 15, 'F2');
        $content .= employee_pdf_text('EMPLOYEE ATTENDANCE', 42, 792, 10, 'F2');
        $content .= employee_pdf_text('Page ' . ($page + 1) . ' of ' . $pageCount, 480, 804, 9, 'F2');

        if ($page === 0) {
            $content .= employee_pdf_rect(36, 682, 523, 68, '1 1 1 rg');
            $content .= "0.07 0.25 0.48 rg\n" . employee_pdf_text('Employee profile', 52, 730, 9, 'F2');
            $content .= employee_pdf_fit_text((string) ($employee['employee_name'] ?? ''), 52, 708, 250, 16, 'F2');
            $content .= employee_pdf_text('ID: ' . $employeeNumber, 52, 690, 9);
            $content .= "0.07 0.25 0.48 rg\n" . employee_pdf_text('Department', 330, 730, 9, 'F2');
            $content .= employee_pdf_fit_text((string) ($employee['department_name'] ?? 'Unassigned'), 330, 708, 205, 12, 'F2');
            $content .= employee_pdf_fit_text((string) ($employee['position'] ?? 'Position not recorded'), 330, 690, 205, 9);
            foreach ([['Records', (string) count($rows)], ['Complete', (string) $complete], ['Hours logged', sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60)]] as $index => [$label, $value]) {
                $x = 36 + ($index * 176);
                $content .= employee_pdf_rect($x, 620, 163, 48, $index === 1 ? '0.88 0.96 0.92 rg' : '0.91 0.94 0.98 rg');
                $content .= "0.07 0.25 0.48 rg\n" . employee_pdf_text($label, $x + 12, 650, 8, 'F2');
                $content .= employee_pdf_text($value, $x + 12, 631, 15, 'F2');
            }
        }

        $tableTop = $page === 0 ? 590 : 720;
        $content .= employee_pdf_rect(36, $tableTop, 523, 26, '0.07 0.25 0.48 rg');
        foreach ([['Date', 50], ['Clock in', 170], ['Clock out', 270], ['Worked', 370], ['Status', 465]] as [$label, $x]) $content .= employee_pdf_text($label, $x, $tableTop + 9, 9, 'F2');
        $y = $tableTop - 22;
        foreach (array_slice($rows, $page * $pageSize, $pageSize) as $index => $record) {
            if ($index % 2 === 0) $content .= employee_pdf_rect(36, $y - 7, 523, 25, '0.94 0.96 0.98 rg');
            $content .= "0.12 0.15 0.20 rg\n";
            $content .= employee_pdf_fit_text((string) ($record['date'] ?? '-'), 50, $y, 100, 9);
            $content .= employee_pdf_fit_text((string) (($record['clock_in'] ?? '') ?: '-'), 170, $y, 80, 9);
            $content .= employee_pdf_fit_text((string) (($record['clock_out'] ?? '') ?: '-'), 270, $y, 80, 9);
            $content .= employee_pdf_fit_text((string) (worked_hours($record) ?: '-'), 370, $y, 70, 9);
            $status = (string) ($record['status'] ?? 'Incomplete');
            $content .= ($status === 'Complete' ? "0.03 0.45 0.26 rg\n" : "0.72 0.12 0.14 rg\n") . employee_pdf_text($status, 465, $y, 9, 'F2');
            $content .= employee_pdf_line(36, $y - 10, 559, $y - 10);
            $y -= 25;
        }
        if (count($rows) === 0) $content .= employee_pdf_text('No attendance records found.', 52, $tableTop - 48, 11, 'F2');
        $content .= "0.35 0.4 0.48 rg\n" . employee_pdf_text('Generated ' . date('M j, Y H:i') . '  |  ' . APP_NAME, 36, 28, 8);
        $contents[] = $content;
    }

    $objects = ["<< /Type /Catalog /Pages 2 0 R >>", "<< /Type /Pages /Kids [" . implode(' ', array_map(static fn (int $index): string => (string) ($index + 3) . ' 0 R', range(0, $pageCount - 1))) . "] /Count " . $pageCount . " >>"];
    $fontObject = 3 + $pageCount;
    $contentObjectStart = $fontObject + 1;
    foreach ($contents as $index => $content) $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 " . $fontObject . " 0 R /F2 " . $fontObject . " 0 R >> >> /Contents " . ($contentObjectStart + $index) . " 0 R >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    foreach ($contents as $content) $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

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
