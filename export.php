<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

require_admin();

$report = strtolower($_GET['report'] ?? 'daily');
$date = $_GET['date'] ?? date('Y-m-d');
$month = $_GET['month'] ?? date('Y-m');
$format = strtolower($_GET['format'] ?? 'csv');
$departmentId = normalize_department_id($_GET['department'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
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
    $clockIn = trim((string) ($record['clock_in'] ?? ''));
    $clockOut = trim((string) ($record['clock_out'] ?? ''));
    $status = trim((string) ($record['status'] ?? ''));

    if ($status === '') {
        $status = $clockIn !== '' && $clockOut !== '' ? 'Complete' : 'Incomplete';
    }

    return [
        'date' => (string) ($record['date'] ?? ''),
        'employee_number' => $employeeNumber !== '' ? $employeeNumber : '-',
        'employee_name' => trim((string) ($record['employee_name'] ?? ($employee['employee_name'] ?? ''))),
        'position' => trim((string) ($record['position'] ?? ($employee['position'] ?? ''))),
        'department_name' => trim((string) ($record['department_name'] ?? ($employee['department_name'] ?? 'Unassigned'))),
        'clock_in' => $clockIn,
        'clock_out' => $clockOut,
        'status' => $status,
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
        'Worked Hours',
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

function export_monthly_rows(array $monthRecords, string $month, string $departmentName): array
{
    $employees = read_employees();
    $rows = [
        [APP_NAME],
        ['Monthly Attendance Report'],
        ['Month: ' . date('F Y', strtotime($month . '-01'))],
        ['Department: ' . $departmentName],
        [''],
    ];

    $rows[] = [
        'Date',
        'Day',
        'Total Records',
        'Complete',
        'Late',
        'Incomplete',
        'Worked Hours',
        'Top Staff',
    ];

    $grandComplete = 0;
    $grandLate = 0;
    $grandIncomplete = 0;
    $grandWorkedMinutes = 0;

    foreach ($monthRecords as $date => $dayRecords) {
        $complete = 0;
        $late = 0;
        $incomplete = 0;
        $workedMinutes = 0;
        $staffNames = [];

        foreach ($dayRecords as $record) {
            $resolved = export_resolve_record($record, $employees);
            $staffNames[] = $resolved['employee_name'] !== '' ? $resolved['employee_name'] : $resolved['employee_number'];

            if (($record['status'] ?? '') === 'Complete') {
                $complete++;
            } else {
                $incomplete++;
            }

            $flags = is_array($record['flags'] ?? null) ? $record['flags'] : [];

            if (($flags['late'] ?? false) === true) {
                $late++;
            }

            $worked = worked_hours($record);

            if ($worked !== '') {
                [$hours, $minutes] = array_map('intval', explode(':', $worked));
                $workedMinutes += ($hours * 60) + $minutes;
            }
        }

        $grandComplete += $complete;
        $grandLate += $late;
        $grandIncomplete += $incomplete;
        $grandWorkedMinutes += $workedMinutes;

        $rows[] = [
            $date,
            date('D', strtotime($date)),
            (string) count($dayRecords),
            (string) $complete,
            (string) $late,
            (string) $incomplete,
            sprintf('%02d:%02d', intdiv($workedMinutes, 60), $workedMinutes % 60),
            implode(', ', array_slice(array_unique(array_filter($staffNames)), 0, 3)),
        ];
    }

    $rows[] = [''];
    $rows[] = ['Totals', '', '', (string) $grandComplete, (string) $grandLate, (string) $grandIncomplete, sprintf('%02d:%02d', intdiv($grandWorkedMinutes, 60), $grandWorkedMinutes % 60), ''];

    if (count($monthRecords) === 0) {
        $rows[] = ['No attendance records found for this month.', '', '', '', '', '', '', ''];
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

function download_monthly_csv(array $rows, string $month): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-' . $month . '-monthly.csv"');

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

    $columnCount = 9;

    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8">';
    echo '<style>';
    echo '@page{mso-page-orientation:landscape;size:landscape;margin:.35in .25in .35in .25in;}';
    echo 'body{font-family:Arial,Helvetica,sans-serif;color:#111827;}';
    echo 'table{border-collapse:collapse;width:100%;}';
    echo 'th,td{border:1px solid #b9c7d8;padding:7px 9px;font-size:11pt;vertical-align:middle;mso-number-format:"\\@";}';
    echo '.title th{background:#123f7a;color:#ffffff;font-size:16pt;text-align:left;padding:12px 10px;}';
    echo '.subtitle th{background:#eaf1fb;color:#123f7a;text-align:left;font-size:12pt;}';
    echo '.meta th{background:#f5f7fb;color:#40504b;text-align:left;font-weight:700;}';
    echo '.spacer td{border:0;height:10px;}';
    echo '.header th{background:#c81e32;color:#ffffff;text-align:left;}';
    echo '.empty td{text-align:center;color:#61708a;font-style:italic;}';
    echo '</style>';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Attendance</x:Name><x:WorksheetOptions><x:PageSetup><x:Layout x:Orientation="Landscape"/></x:PageSetup><x:FitToPage/><x:Print><x:FitWidth>1</x:FitWidth><x:FitHeight>0</x:FitHeight></x:Print><x:FreezePanes/><x:FrozenNoSplit/><x:SplitHorizontal>6</x:SplitHorizontal><x:TopRowBottomPane>6</x:TopRowBottomPane><x:ActivePane>2</x:ActivePane></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '</head><body>';
    echo '<table>';
    echo '<colgroup>';
    echo '<col style="width:95px"><col style="width:135px"><col style="width:220px"><col style="width:190px"><col style="width:190px"><col style="width:95px"><col style="width:95px"><col style="width:100px"><col style="width:95px">';
    echo '</colgroup>';

    foreach ($rows as $index => $row) {
        if ($index === 0) {
            echo '<tr class="title"><th colspan="' . $columnCount . '">' . h((string) ($row[0] ?? '')) . '</th></tr>';
            continue;
        }

        if ($index === 1) {
            echo '<tr class="subtitle"><th colspan="' . $columnCount . '">' . h((string) ($row[0] ?? '')) . '</th></tr>';
            continue;
        }

        if ($index === 2 || $index === 3) {
            echo '<tr class="meta"><th colspan="' . $columnCount . '">' . h((string) ($row[0] ?? '')) . '</th></tr>';
            continue;
        }

        if ($index === 4) {
            echo '<tr class="spacer"><td colspan="' . $columnCount . '"></td></tr>';
            continue;
        }

        $rowClass = $index === 5 ? ' class="header"' : (str_starts_with((string) ($row[0] ?? ''), 'No attendance records') ? ' class="empty"' : '');
        $tag = $index === 5 ? 'th' : 'td';
        echo '<tr' . $rowClass . '>';

        foreach ($row as $cell) {
            echo '<' . $tag . '>' . h((string) $cell) . '</' . $tag . '>';
        }

        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;
}

function download_monthly_xls(array $rows, string $month): never
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance-' . $month . '-monthly.xls"');

    $columnCount = 8;

    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8">';
    echo '<style>';
    echo '@page{mso-page-orientation:landscape;size:landscape;margin:.35in .25in .35in .25in;}';
    echo 'body{font-family:Arial,Helvetica,sans-serif;color:#111827;}';
    echo 'table{border-collapse:collapse;width:100%;}';
    echo 'th,td{border:1px solid #b9c7d8;padding:7px 9px;font-size:11pt;vertical-align:middle;mso-number-format:"\\@";}';
    echo '.title th{background:#123f7a;color:#ffffff;font-size:16pt;text-align:left;padding:12px 10px;}';
    echo '.subtitle th{background:#eaf1fb;color:#123f7a;text-align:left;font-size:12pt;}';
    echo '.meta th{background:#f5f7fb;color:#40504b;text-align:left;font-weight:700;}';
    echo '.spacer td{border:0;height:10px;}';
    echo '.header th{background:#c81e32;color:#ffffff;text-align:left;}';
    echo '.empty td{text-align:center;color:#61708a;font-style:italic;}';
    echo '</style>';
    echo '</head><body>';
    echo '<table>';
    echo '<colgroup>';
    echo '<col style="width:105px"><col style="width:80px"><col style="width:110px"><col style="width:95px"><col style="width:95px"><col style="width:110px"><col style="width:100px"><col style="width:220px">';
    echo '</colgroup>';

    foreach ($rows as $index => $row) {
        if ($index === 0) {
            echo '<tr class="title"><th colspan="' . $columnCount . '">' . h((string) ($row[0] ?? '')) . '</th></tr>';
            continue;
        }

        if ($index === 1) {
            echo '<tr class="subtitle"><th colspan="' . $columnCount . '">' . h((string) ($row[0] ?? '')) . '</th></tr>';
            continue;
        }

        if ($index === 2 || $index === 3) {
            echo '<tr class="meta"><th colspan="' . $columnCount . '">' . h((string) ($row[0] ?? '')) . '</th></tr>';
            continue;
        }

        if ($index === 4) {
            echo '<tr class="spacer"><td colspan="' . $columnCount . '"></td></tr>';
            continue;
        }

        $rowClass = $index === 5 ? ' class="header"' : (str_starts_with((string) ($row[0] ?? ''), 'No attendance records') ? ' class="empty"' : '');
        $tag = $index === 5 ? 'th' : 'td';
        echo '<tr' . $rowClass . '>';

        foreach ($row as $cell) {
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

function build_pdf_content_for_page(
    array $records,
    array $employees,
    string $date,
    string $departmentName,
    int $pageNumber,
    int $pageCount,
    int $completeCount,
    int $incompleteCount,
    int $offset,
    int $perPage
): string {
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
    $content .= pdf_text('Page ' . $pageNumber . ' of ' . $pageCount, 700, 549, 9, 'F2');

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
    $visibleRecords = array_slice($records, $offset, $perPage);

    foreach ($visibleRecords as $index => $record) {
        $resolved = export_resolve_record($record, $employees);

        if ($index % 2 === 0) {
            $content .= "0.97 0.98 0.99 rg\n38 " . ($y - 12) . " 790 " . $rowHeight . " re f\n";
        }

        $content .= "0.84 0.87 0.91 RG\n";
        $content .= pdf_line(38, $y - 12, 828, $y - 12);
        $content .= "0 0 0 rg\n";

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
    }

    $content .= "0.07 0.25 0.48 rg\n";
    $content .= pdf_text('Authorization', 38, 90, 11, 'F2');
    $content .= "0 0 0 rg\n";
    $content .= pdf_line(250, 74, 430, 74);
    $content .= pdf_text('Prepared By', 295, 57, 9);
    $content .= pdf_line(525, 74, 705, 74);
    $content .= pdf_text('Approved By', 572, 57, 9);

    return $content;
}

function build_simple_pdf(array $records, string $date, string $departmentName): string
{
    $employees = read_employees();
    $completeCount = count(array_filter($records, static fn (array $record): bool => ($record['status'] ?? '') === 'Complete'));
    $incompleteCount = count($records) - $completeCount;
    $perPage = 15;
    $pageCount = max(1, (int) ceil(max(1, count($records)) / $perPage));

    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";

    $fontCourierObjectNumber = 3;
    $fontBoldObjectNumber = 4;
    $firstPageObjectNumber = 5;
    $pagesObjectNumber = $firstPageObjectNumber + ($pageCount * 2);

    $pageObjectNumbers = [];
    $contentObjectNumbers = [];

    for ($index = 0; $index < $pageCount; $index++) {
        $pageObjectNumbers[] = $firstPageObjectNumber + ($index * 2);
        $contentObjectNumbers[] = $firstPageObjectNumber + ($index * 2) + 1;
    }

    $kids = implode(' ', array_map(static fn (int $number): string => $number . ' 0 R', $pageObjectNumbers));
    $objects[] = "<< /Type /Pages /Kids [" . $kids . "] /Count " . $pageCount . " >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>";

    for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
        $content = build_pdf_content_for_page(
            $records,
            $employees,
            $date,
            $departmentName,
            $pageIndex + 1,
            $pageCount,
            $completeCount,
            $incompleteCount,
            $pageIndex * $perPage,
            $perPage
        );

        $objects[] = "<< /Type /Page /Parent " . $pagesObjectNumber . " 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 " . $fontCourierObjectNumber . " 0 R /F2 " . $fontBoldObjectNumber . " 0 R >> >> /Contents " . $contentObjectNumbers[$pageIndex] . " 0 R >>";
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
    }

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

function build_monthly_simple_pdf(array $monthRecords, string $month, string $departmentName): string
{
    $monthLabel = date('F Y', strtotime($month . '-01'));
    $rows = [];
    $totalRecords = 0;
    $completeCount = 0;
    $lateCount = 0;

    foreach ($monthRecords as $date => $dayRecords) {
        $dayComplete = 0;
        $dayLate = 0;

        foreach ($dayRecords as $record) {
            $totalRecords++;

            if (($record['status'] ?? '') === 'Complete') {
                $completeCount++;
                $dayComplete++;
            }

            $flags = is_array($record['flags'] ?? null) ? $record['flags'] : [];

            if (($flags['late'] ?? false) === true) {
                $lateCount++;
                $dayLate++;
            }
        }

        $rows[] = [
            $date,
            date('D', strtotime($date)),
            (string) count($dayRecords),
            (string) $dayComplete,
            (string) $dayLate,
        ];
    }

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = '<< /Type /Pages /Kids [5 0 R] /Count 1 >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>';
    $fontCourierObjectNumber = 3;
    $fontBoldObjectNumber = 4;

    $content = "0.97 0.98 1 rg\n0 0 842 595 re f\n";
    $content .= "0.07 0.25 0.48 rg\n0 522 842 73 re f\n";
    $content .= "0.78 0.12 0.20 rg\n0 517 842 5 re f\n";
    $content .= pdf_logo_block(36, 536);
    $content .= "1 1 1 rg\n";
    $content .= pdf_text(APP_NAME, 86, 566, 16, 'F2');
    $content .= pdf_text('Monthly Attendance Report', 86, 549, 11, 'F2');
    $content .= pdf_text('Month: ' . $monthLabel, 86, 533, 10);
    $content .= pdf_text('Department: ' . $departmentName, 300, 533, 10);
    $content .= pdf_text('Generated: ' . date('Y-m-d H:i'), 606, 533, 9);

    $summaryCards = [
        [38, 443, 172, 42, 'Total Records', (string) $totalRecords],
        [228, 443, 172, 42, 'Complete', (string) $completeCount],
        [418, 443, 172, 42, 'Late', (string) $lateCount],
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
        ['Date', 50],
        ['Day', 145],
        ['Total', 220],
        ['Complete', 295],
        ['Late', 395],
        ['Worked', 475],
    ];

    foreach ($headers as [$label, $x]) {
        $content .= pdf_text($label, $x, 399, 9, 'F2');
    }

    $content .= "0 0 0 rg\n";
    $y = 373;
    foreach ($rows as $index => $row) {
        if ($index % 2 === 0) {
            $content .= "0.97 0.98 0.99 rg\n38 " . ($y - 12) . " 790 17 re f\n";
        }

        $content .= "0.84 0.87 0.91 RG\n";
        $content .= pdf_line(38, $y - 12, 828, $y - 12);
        $content .= "0 0 0 rg\n";

        $values = [
            [substr((string) ($row[0] ?? ''), 0, 18), 50, 84],
            [$row[1] ?? '-', 145, 50],
            [$row[2] ?? '-', 220, 60],
            [$row[3] ?? '-', 295, 60],
            [$row[4] ?? '-', 395, 60],
            [sprintf('%02d:%02d', intdiv((int) ($row[2] ?? 0) * 15, 60), ((int) ($row[2] ?? 0) * 15) % 60), 475, 70],
        ];

        foreach ($values as [$value, $x, $maxWidth]) {
            $content .= pdf_text(pdf_shorten_text((string) $value, $maxWidth, 9), $x, $y, 9);
        }

        $y -= 17;
    }

    if (count($rows) === 0) {
        $content .= pdf_text('No attendance records found for this month and department.', 58, 335, 10, 'F2');
    }

    $content .= '0.07 0.25 0.48 rg\n';
    $content .= pdf_text('Authorization', 38, 90, 11, 'F2');
    $content .= "0 0 0 rg\n";
    $content .= pdf_line(250, 74, 430, 74);
    $content .= pdf_text('Prepared By', 295, 57, 9);
    $content .= pdf_line(525, 74, 705, 74);
    $content .= pdf_text('Approved By', 572, 57, 9);

    $pdf = "%PDF-1.4\n";
    $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 ' . $fontCourierObjectNumber . ' 0 R /F2 ' . $fontBoldObjectNumber . ' 0 R >> >> /Contents 6 0 R >>';
    $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
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

function download_monthly_pdf(array $monthRecords, string $month, string $departmentName): never
{
    $pdf = build_monthly_simple_pdf($monthRecords, $month, $departmentName);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="attendance-' . $month . '-monthly.pdf"');
    header('Content-Length: ' . strlen($pdf));

    echo $pdf;
    exit;
}

function export_employee_rows(array $employees): array
{
    $rows = [
        [APP_NAME],
        ['Employee Directory'],
        ['Generated: ' . date('Y-m-d H:i')],
        [''],
        ['Employee ID', 'Name', 'Position', 'Department'],
    ];

    foreach ($employees as $employee) {
        $rows[] = [
            trim((string) ($employee['employee_number'] ?? '')) !== '' ? trim((string) ($employee['employee_number'] ?? '')) : '-',
            trim((string) ($employee['employee_name'] ?? '')) !== '' ? trim((string) ($employee['employee_name'] ?? '')) : '-',
            trim((string) ($employee['position'] ?? '')) !== '' ? trim((string) ($employee['position'] ?? '')) : '-',
            trim((string) ($employee['department_name'] ?? '')) !== '' ? trim((string) ($employee['department_name'] ?? '')) : 'Unassigned',
        ];
    }

    if (count($employees) === 0) {
        $rows[] = ['No employees found.', '', '', ''];
    }

    return $rows;
}

function download_employees_csv(array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employees-directory.csv"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    foreach ($rows as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

function download_employees_xls(array $rows): never
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="employees-directory.xls"');

    $columnCount = 4;

    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8">';
    echo '<style>';
    echo '@page{mso-page-orientation:portrait;size:portrait;margin:.35in .25in .35in .25in;}';
    echo 'body{font-family:Arial,Helvetica,sans-serif;color:#111827;}';
    echo 'table{border-collapse:collapse;width:100%;}';
    echo 'th,td{border:1px solid #b9c7d8;padding:7px 9px;font-size:11pt;vertical-align:middle;mso-number-format:"\\@";}';
    echo '.title th{background:#123f7a;color:#ffffff;font-size:16pt;text-align:left;padding:12px 10px;}';
    echo '.subtitle th{background:#eaf1fb;color:#123f7a;text-align:left;font-size:12pt;}';
    echo '.meta th{background:#f5f7fb;color:#40504b;text-align:left;font-weight:700;}';
    echo '.spacer td{border:0;height:10px;}';
    echo '.header th{background:#c81e32;color:#ffffff;text-align:left;}';
    echo '.empty td{text-align:center;color:#61708a;font-style:italic;}';
    echo '</style>';
    echo '</head><body><table>';
    echo '<colgroup><col style="width:150px"><col style="width:240px"><col style="width:220px"><col style="width:220px"></colgroup>';

    foreach ($rows as $index => $row) {
        if ($index === 0) {
            echo '<tr class="title"><th colspan="' . $columnCount . '">' . h((string) ($row[0] ?? '')) . '</th></tr>';
            continue;
        }

        if ($index === 1) {
            echo '<tr class="subtitle"><th colspan="' . $columnCount . '">' . h((string) ($row[0] ?? '')) . '</th></tr>';
            continue;
        }

        if ($index === 2) {
            echo '<tr class="meta"><th colspan="' . $columnCount . '">' . h((string) ($row[0] ?? '')) . '</th></tr>';
            continue;
        }

        if ($index === 3) {
            echo '<tr class="spacer"><td colspan="' . $columnCount . '"></td></tr>';
            continue;
        }

        $rowClass = $index === 4 ? ' class="header"' : (str_starts_with((string) ($row[0] ?? ''), 'No employees found') ? ' class="empty"' : '');
        $tag = $index === 4 ? 'th' : 'td';
        echo '<tr' . $rowClass . '>';

        foreach ($row as $cell) {
            echo '<' . $tag . '>' . h((string) $cell) . '</' . $tag . '>';
        }

        echo '</tr>';
    }

    echo '</table></body></html>';
    exit;
}

function build_employees_pdf_content(array $employees, int $pageNumber, int $pageCount, int $offset, int $perPage): string
{
    $content = "0.97 0.98 1 rg\n0 0 842 595 re f\n";
    $content .= "0.07 0.25 0.48 rg\n0 522 842 73 re f\n";
    $content .= "0.78 0.12 0.20 rg\n0 517 842 5 re f\n";
    $content .= pdf_logo_block(36, 536);
    $content .= "1 1 1 rg\n";
    $content .= pdf_text(APP_NAME, 86, 566, 16, 'F2');
    $content .= pdf_text('Employee Directory', 86, 549, 11, 'F2');
    $content .= pdf_text('Generated: ' . date('Y-m-d H:i'), 86, 533, 10);
    $content .= pdf_text('Page ' . $pageNumber . ' of ' . $pageCount, 700, 549, 9, 'F2');

    $content .= "0.93 0.95 0.98 rg\n38 443 220 42 re f\n";
    $content .= "0.07 0.25 0.48 rg\n";
    $content .= pdf_text('Total Employees', 50, 467, 8, 'F2');
    $content .= pdf_text((string) count($employees), 50, 453, 13, 'F2');

    $content .= "0.07 0.25 0.48 rg\n38 391 790 24 re f\n";
    $content .= "1 1 1 rg\n";
    $headers = [
        ['Employee ID', 50],
        ['Name', 198],
        ['Position', 430],
        ['Department', 632],
    ];

    foreach ($headers as [$label, $x]) {
        $content .= pdf_text($label, $x, 399, 9, 'F2');
    }

    $content .= "0 0 0 rg\n";
    $y = 373;
    $rowHeight = 17;
    $visibleEmployees = array_slice($employees, $offset, $perPage);

    foreach ($visibleEmployees as $index => $employee) {
        if ($index % 2 === 0) {
            $content .= "0.97 0.98 0.99 rg\n38 " . ($y - 12) . " 790 " . $rowHeight . " re f\n";
        }

        $content .= "0.84 0.87 0.91 RG\n";
        $content .= pdf_line(38, $y - 12, 828, $y - 12);
        $content .= "0 0 0 rg\n";

        $employeeNumber = trim((string) ($employee['employee_number'] ?? ''));
        $employeeName = trim((string) ($employee['employee_name'] ?? ''));
        $position = trim((string) ($employee['position'] ?? ''));
        $departmentName = trim((string) ($employee['department_name'] ?? ''));

        $values = [
            [$employeeNumber !== '' ? $employeeNumber : '-', 50, 132],
            [$employeeName !== '' ? $employeeName : '-', 198, 218],
            [$position !== '' ? $position : '-', 430, 182],
            [$departmentName !== '' ? $departmentName : 'Unassigned', 632, 176],
        ];

        foreach ($values as [$value, $x, $maxWidth]) {
            $content .= pdf_text(pdf_shorten_text((string) $value, $maxWidth, 9), $x, $y, 9);
        }

        $y -= $rowHeight;
    }

    if (count($employees) === 0) {
        $content .= pdf_text('No employees found.', 58, 335, 10, 'F2');
    }

    return $content;
}

function build_employees_pdf(array $employees): string
{
    $perPage = 15;
    $pageCount = max(1, (int) ceil(max(1, count($employees)) / $perPage));

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

    $fontCourierObjectNumber = 3;
    $fontBoldObjectNumber = 4;
    $firstPageObjectNumber = 5;
    $pagesObjectNumber = $firstPageObjectNumber + ($pageCount * 2);

    $pageObjectNumbers = [];
    $contentObjectNumbers = [];

    for ($index = 0; $index < $pageCount; $index++) {
        $pageObjectNumbers[] = $firstPageObjectNumber + ($index * 2);
        $contentObjectNumbers[] = $firstPageObjectNumber + ($index * 2) + 1;
    }

    $kids = implode(' ', array_map(static fn (int $number): string => $number . ' 0 R', $pageObjectNumbers));
    $objects[] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . $pageCount . ' >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>';

    for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
        $content = build_employees_pdf_content(
            $employees,
            $pageIndex + 1,
            $pageCount,
            $pageIndex * $perPage,
            $perPage
        );

        $objects[] = '<< /Type /Page /Parent ' . $pagesObjectNumber . ' 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 ' . $fontCourierObjectNumber . ' 0 R /F2 ' . $fontBoldObjectNumber . ' 0 R >> >> /Contents ' . $contentObjectNumbers[$pageIndex] . ' 0 R >>';
        $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
    }

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

function download_employees_pdf(array $employees): never
{
    $pdf = build_employees_pdf($employees);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="employees-directory.pdf"');
    header('Content-Length: ' . strlen($pdf));

    echo $pdf;
    exit;
}

if ($report === 'employees') {
    $employees = all_employees();
    $rows = export_employee_rows($employees);

    match ($format) {
        'csv' => download_employees_csv($rows),
        'xls', 'excel' => download_employees_xls($rows),
        'pdf' => download_employees_pdf($employees),
        default => download_employees_pdf($employees),
    };
}

if ($report === 'monthly') {
    $monthRecords = attendance_for_month($month, $departmentId);
    $rows = export_monthly_rows($monthRecords, $month, $departmentName);

    match ($format) {
        'csv' => download_monthly_csv($rows, $month),
        'xls', 'excel' => download_monthly_xls($rows, $month),
        'pdf' => download_monthly_pdf($monthRecords, $month, $departmentName),
        default => download_monthly_csv($rows, $month),
    };
}

$rows = export_rows($records, $date, $departmentName);

match ($format) {
    'csv' => download_csv($rows, $date),
    'xls', 'excel' => download_xls($rows, $date),
    'pdf' => download_pdf($records, $date, $departmentName),
    default => download_csv($rows, $date),
};
