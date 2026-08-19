<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_admin();

$letterId = trim((string) ($_GET['letter_id'] ?? ''));
$letter = find_letter($letterId);
if ($letter === null) {
    http_response_code(404);
    exit('Letter not found.');
}

function letter_pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function letter_pdf_text(string $text, int $x, int $y, int $size = 10, string $font = 'F1'): string
{
    return "BT\n/" . $font . ' ' . $size . " Tf\n" . $x . ' ' . $y . " Td\n(" . letter_pdf_escape($text) . ") Tj\nET\n";
}

function letter_pdf_wrap_text(string $text, int $maxCharsPerLine): array
{
    $normalized = preg_replace('/\s+/', ' ', trim($text));
    if ($normalized === null || $normalized === '') {
        return [''];
    }

    $words = preg_split('/\s+/', $normalized);
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if (mb_strlen($candidate) <= $maxCharsPerLine) {
            $current = $candidate;
            continue;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (mb_strlen($word) > $maxCharsPerLine) {
            while (mb_strlen($word) > $maxCharsPerLine) {
                $lines[] = mb_substr($word, 0, $maxCharsPerLine);
                $word = mb_substr($word, $maxCharsPerLine);
            }
            $current = $word;
        } else {
            $current = $word;
        }
    }

    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

function letter_pdf_line(int $x1, int $y1, int $x2, int $y2, string $color = '0 0 0 RG'): string
{
    return $color . "\n" . $x1 . ' ' . $y1 . ' m ' . $x2 . ' ' . $y2 . " l S\n";
}

function letter_pdf_rect(int $x, int $y, int $width, int $height, string $color): string
{
    return $color . "\n" . $x . ' ' . $y . ' ' . $width . ' ' . $height . " re f\n";
}

function letter_build_pdf(array $letter): string
{
    $title = (string) ($letter['letter_type'] ?? 'Employee Letter');
    $employeeName = (string) ($letter['employee_name'] ?? 'Employee');
    $employeeNumber = (string) ($letter['employee_number'] ?? '');
    $department = (string) ($letter['department_name'] ?? 'Unassigned');
    $position = (string) ($letter['position'] ?? '');
    $issuedBy = (string) ($letter['issued_by'] ?? 'Unknown');
    $issuedAtRaw = (string) ($letter['issued_at'] ?? 'now');
    $issuedAt = strtotime($issuedAtRaw) !== false ? date('F j, Y', strtotime($issuedAtRaw)) : date('F j, Y');
    $subject = (string) ($letter['subject'] ?? $title);
    $details = trim((string) ($letter['details'] ?? 'No additional details provided.'));
    if ($details === '') {
        $details = 'No additional details provided.';
    }

    $content = "q\n";
    $content .= "0 0 0 rg\n";
    $content .= "0 0 595 842 re f\n";
    $content .= "0.96 0.97 0.95 rg\n";
    $content .= "0 760 595 82 re f\n";
    $content .= "0.83 0.88 0.84 rg\n";
    $content .= "0 748 595 12 re f\n";
    $content .= "1 1 1 rg\n";
    $content .= letter_pdf_text('REPUBLIC OF LIBERIA', 170, 810, 15, 'F2');
    $content .= letter_pdf_text('Ministry of Youth and Sports', 130, 790, 17, 'F2');
    $content .= letter_pdf_text('Samuel Kanyon Doe Sports Complex, Paynesville, Liberia', 95, 772, 10);
    $content .= letter_pdf_text('Office of the Human Resource | 0770000042 / 0886710956 / nyanwaygrace@gmail.com', 82, 756, 9, 'F2');

    $content .= "0 0 0 rg\n";
    $content .= letter_pdf_text('Date: ' . $issuedAt, 440, 732, 10);
    $content .= letter_pdf_text(strtoupper($title), 220, 706, 15, 'F2');
    $content .= letter_pdf_line(48, 692, 548, 692, '0.70 0.73 0.76 RG');

    $meta = [
        'Subject' => $subject,
        'Employee Name' => $employeeName,
        'Employee Number' => $employeeNumber,
        'Position' => ($position !== '' ? $position : 'Not stated'),
        'Department' => $department,
        'Issued By' => $issuedBy,
    ];

    $metaY = 662;
    foreach ($meta as $label => $value) {
        $content .= letter_pdf_text($label . ': ' . $value, 58, $metaY, 9);
        $metaY -= 18;
    }

    $content .= "0.97 0.98 0.97 rg\n";
    $content .= "48 162 500 450 re f\n";
    $content .= "0 0 0 rg\n";
    $content .= letter_pdf_text('BODY OF LETTER', 58, 598, 11, 'F2');

    $paragraphs = preg_split('/\r\n\r\n|\n\n|\r\r/', $details) ?: [$details];
    $bodyLines = [];
    foreach ($paragraphs as $paragraph) {
        $bodyLines = array_merge($bodyLines, letter_pdf_wrap_text($paragraph, 96));
        $bodyLines[] = '';
    }

    $bodyY = 570;
    foreach ($bodyLines as $line) {
        if ($bodyY < 180) {
            break;
        }
        if ($line === '') {
            $bodyY -= 14;
            continue;
        }
        $content .= letter_pdf_text((string) $line, 72, $bodyY, 10);
        $bodyY -= 16;
    }

    $content .= letter_pdf_text('This letter is issued in accordance with the Ministry administrative process.', 58, 116, 8);
    $content .= letter_pdf_line(72, 138, 260, 138, '0 0 0 RG');
    $content .= letter_pdf_text('Issued By', 102, 122, 9, 'F2');
    $content .= letter_pdf_line(360, 138, 520, 138, '0 0 0 RG');
    $content .= letter_pdf_text('Signature', 405, 122, 9, 'F2');
    $content .= letter_pdf_line(360, 90, 520, 90, '0 0 0 RG');
    $content .= letter_pdf_text('Date', 425, 74, 9, 'F2');
    $content .= letter_pdf_text('Authorized by: ' . $issuedBy, 58, 90, 9, 'F2');
    $content .= "Q\n";

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

$pdf = letter_build_pdf($letter);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($letter['letter_type'] ?? 'employee-letter')) . '-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($letter['employee_number'] ?? 'employee')) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
