<?php
declare(strict_types=1);

$_GET['page'] = 'department_monthly_print';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Department Monthly Print';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';

$selectedEmployeeType = normalize_employee_type_filter((string) ($_GET['employee_type'] ?? ''));
$monthlyDepartmentLabel = $activeFilterLabel;

if ($selectedEmployeeType !== '') {
    $monthlyDepartmentLabel .= ' | ' . $selectedEmployeeType;
}

$attendanceRows = [];

foreach ($monthlyRecords as $date => $dayRecords) {
    foreach ($dayRecords as $record) {
        $employeeNumber = normalize_employee_number((string) ($record['employee_number'] ?? ''));
        $employeeData = $employeesByNumber[$employeeNumber] ?? null;
        $recordType = trim((string) ($record['employee_type'] ?? ($employeeData['employee_type'] ?? 'Employee')));

        if ($selectedEmployeeType !== '' && $recordType !== $selectedEmployeeType) {
            continue;
        }

        $status = trim((string) ($record['status'] ?? ''));
        if ($status === '') {
            $status = (string) ($record['clock_in'] ?? '') !== '' && (string) ($record['clock_out'] ?? '') !== '' ? 'Complete' : 'Incomplete';
        }

        $flags = is_array($record['flags'] ?? null) ? $record['flags'] : [];
        $isLate = ($flags['late'] ?? false) === true;

        $attendanceRows[] = [
            'date' => (string) ($record['date'] ?? $date),
            'employee_number' => $employeeNumber,
            'employee_name' => trim((string) ($record['employee_name'] ?? ($employeeData['employee_name'] ?? ''))),
            'position' => trim((string) ($record['position'] ?? ($employeeData['position'] ?? ''))),
            'department_name' => trim((string) ($record['department_name'] ?? ($employeeData['department_name'] ?? 'Unassigned'))),
            'employee_type' => $recordType,
            'clock_in' => trim((string) ($record['clock_in'] ?? '')),
            'clock_out' => trim((string) ($record['clock_out'] ?? '')),
            'worked_hours' => worked_hours($record),
            'status' => $status,
            'late' => $isLate,
        ];
    }
}

usort($attendanceRows, static function (array $a, array $b): int {
    $dateOrder = $a['date'] <=> $b['date'];

    return $dateOrder !== 0 ? $dateOrder : ($a['employee_number'] <=> $b['employee_number']);
});

$monthLabel = date('F Y', strtotime($selectedMonth . '-01'));
$exportQuery = http_build_query([
    'report' => 'department_monthly',
    'month' => $selectedMonth,
    'department' => $selectedDepartment,
    'employee_type' => $selectedEmployeeType,
]);

require_once __DIR__ . '/admin_shell_start.php';
?>
<div class="dashboard-hero panel department-monthly-no-print">
    <div class="dashboard-title">
        <span class="eyebrow">Monthly Attendance</span>
        <h1>Department Monthly Print</h1>
        <p class="muted">View and print every attendance record for the selected month and department.</p>
    </div>
</div>

<section class="admin-box department-monthly-controls department-monthly-no-print">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label" for="dept_monthly_month">Month</label>
            <input class="form-control" id="dept_monthly_month" name="month" type="month" value="<?= h($selectedMonth) ?>">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="dept_monthly_department">Department</label>
            <select class="form-select" id="dept_monthly_department" name="department">
                <option value="">All Departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= h($department['department_id']) ?>" <?= $selectedDepartment === $department['department_id'] ? 'selected' : '' ?>>
                        <?= h($department['department_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label class="form-label" for="dept_monthly_type">Employee type</label>
            <select class="form-select" id="dept_monthly_type" name="employee_type">
                <option value="">All Types</option>
                <option value="Employee" <?= $selectedEmployeeType === 'Employee' ? 'selected' : '' ?>>Employee</option>
                <option value="Contractor" <?= $selectedEmployeeType === 'Contractor' ? 'selected' : '' ?>>Contractor</option>
                <option value="Volunteer" <?= $selectedEmployeeType === 'Volunteer' ? 'selected' : '' ?>>Volunteer</option>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <button class="btn btn-primary w-100" type="submit">View</button>
        </div>
        <div class="col-6 col-md-2">
            <button class="btn btn-outline-primary w-100" type="button" onclick="window.print()">Print</button>
        </div>
    </form>
    <div class="export-links department-monthly-export-links">
        <a class="btn btn-outline-secondary" href="export.php?<?= h($exportQuery) ?>&amp;format=pdf">Download PDF</a>
        <a class="btn btn-outline-secondary" href="export.php?<?= h($exportQuery) ?>&amp;format=csv">Download CSV</a>
    </div>
</section>

<section class="department-monthly-paper" aria-label="Department monthly attendance print report">
    <header class="department-monthly-header">
        <strong>REPUBLIC OF LIBERIA</strong>
        <h2>Ministry of Youth and Sports</h2>
        <h3>Department Monthly Attendance Register</h3>
        <p><b>Month:</b> <?= h($monthLabel) ?> &nbsp; | &nbsp; <b>Department:</b> <?= h($monthlyDepartmentLabel) ?></p>
    </header>

    <div class="department-monthly-table-wrap">
        <table class="department-monthly-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Date</th>
                    <th>Employee Number</th>
                    <th>Employee Name</th>
                    <th>Position</th>
                    <th>Department</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Worked Hours</th>
                    <th>Status</th>
                    <th>Late</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowIndex = 1; ?>
                <?php foreach ($attendanceRows as $row): ?>
                    <tr>
                        <td><?= $rowIndex++ ?></td>
                        <td><?= h($row['date']) ?></td>
                        <td><?= h($row['employee_number']) ?></td>
                        <td><?= h($row['employee_name'] !== '' ? $row['employee_name'] : '-') ?></td>
                        <td><?= h($row['position'] !== '' ? $row['position'] : '-') ?></td>
                        <td><?= h($row['department_name'] !== '' ? $row['department_name'] : 'Unassigned') ?></td>
                        <td><?= h($row['clock_in'] !== '' ? $row['clock_in'] : '-') ?></td>
                        <td><?= h($row['clock_out'] !== '' ? $row['clock_out'] : '-') ?></td>
                        <td><?= h($row['worked_hours'] !== '' ? $row['worked_hours'] : '-') ?></td>
                        <td><?= h($row['status']) ?></td>
                        <td><?= $row['late'] ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($attendanceRows === []): ?>
                    <tr>
                        <td class="department-monthly-empty" colspan="11">No attendance records found for this month and department.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <footer class="department-monthly-footer">
        <span>Generated: <?= h(date('M j, Y H:i')) ?></span>
        <span>Prepared by: ____________________</span>
        <span>Approved by: ____________________</span>
    </footer>
</section>
<?php require_once __DIR__ . '/admin_shell_end.php'; ?>
