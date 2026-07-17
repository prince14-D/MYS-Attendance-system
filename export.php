<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

require_admin();

$date = $_GET['date'] ?? date('Y-m-d');
$format = strtolower($_GET['format'] ?? 'csv');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$records = attendance_for_date($date);

function export_rows(array $records, string $date): array
{
    $rows = [
        [APP_NAME],
        ['Daily Attendance Records'],
        ['Date: ' . $date],
        [''],
    ];

    $rows[] = [
        'Employee Number',
        'Date',
        'Clock In',
        'Clock Out',
        'Worked Hours',
        'Status',
    ];

    foreach ($records as $record) {
        $rows[] = [
            $record['employee_number'],
            $record['date'],
            $record['clock_in'] ?: '-',
            $record['clock_out'] ?: '-',
            worked_hours($record) ?: '-',
            $record['status'],
        ];
    }

    if (count($records) === 0) {
        $rows[] = ['No attendance records found for this date.', '', '', '', '', ''];
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
            $tag = $index < 5 ? 'th' : 'td';
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

function build_simple_pdf(array $rows, string $date): string
{
    $lines = [APP_NAME, 'Daily Attendance Records', 'Date: ' . $date, ''];

    foreach ($rows as $index => $row) {
        if ($index < 4) {
            continue;
        }

        if ($index === 4) {
            $lines[] = implode(' | ', $row);
            $lines[] = str_repeat('-', 95);
            continue;
        }

        $lines[] = implode(' | ', $row);
    }

    $objects = [];
    $content = "BT\n/F1 10 Tf\n40 555 Td\n14 TL\n";

    foreach ($lines as $line) {
        $content .= '(' . pdf_escape(substr($line, 0, 115)) . ") Tj\nT*\n";
    }

    $content .= "ET\n";

    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
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

function download_pdf(array $rows, string $date): never
{
    $pdf = build_simple_pdf($rows, $date);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="attendance-' . $date . '.pdf"');
    header('Content-Length: ' . strlen($pdf));

    echo $pdf;
    exit;
}

$rows = export_rows($records, $date);

match ($format) {
    'csv' => download_csv($rows, $date),
    'xls', 'excel' => download_xls($rows, $date),
    'pdf' => download_pdf($rows, $date),
    default => download_csv($rows, $date),
};
