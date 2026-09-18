<?php
declare(strict_types=1);

$_GET['page'] = 'payroll_attendance';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Payroll Attendance';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
require_once __DIR__ . '/admin_shell_start.php';

$selectedEmployeeType = (string) ($_GET['employee_type'] ?? 'Employee');
$employeeTypes = ['Employee', 'Contractor', 'Volunteer'];
if (!in_array($selectedEmployeeType, $employeeTypes, true)) $selectedEmployeeType = 'Employee';

$monthStart = new DateTimeImmutable($selectedMonth . '-01');
$monthEnd = $monthStart->modify('last day of this month');
$payrollStorageKey = 'mys-payroll-' . $selectedMonth . '-' . ($selectedDepartment ?: 'all') . '-' . strtolower($selectedEmployeeType);
$workingDates = [];
for ($date = $monthStart; $date <= $monthEnd; $date = $date->modify('+1 day')) {
    if ((int) $date->format('N') <= 5) $workingDates[] = $date->format('Y-m-d');
}

$payrollEmployees = $employees;
if ($selectedDepartment !== '') {
    $payrollEmployees = array_values(array_filter($payrollEmployees, static fn (array $employee): bool => (string) ($employee['department_id'] ?? '') === $selectedDepartment));
}
$payrollEmployees = array_values(array_filter($payrollEmployees, static fn (array $employee): bool => (string) ($employee['employee_type'] ?? 'Employee') === $selectedEmployeeType));

$recordsByEmployee = [];
foreach ($monthlyRecords as $date => $dayRecords) {
    foreach ($dayRecords as $record) {
        $number = normalize_employee_number((string) ($record['employee_number'] ?? ''));
        if ($number !== '') $recordsByEmployee[$number][$date] = $record;
    }
}

$approvedExcuseDates = [];
foreach ($monthlyExcuses as $excuse) {
    if (($excuse['hr_approved'] ?? '') !== 'Yes') continue;
    $number = normalize_employee_number((string) ($excuse['employee_number'] ?? ''));
    $start = max((string) ($excuse['absence_start'] ?? ''), $monthStart->format('Y-m-d'));
    $end = min((string) ($excuse['absence_end'] ?? $start), $monthEnd->format('Y-m-d'));
    if ($number === '' || $start === '' || $end === '') continue;
    for ($date = new DateTimeImmutable($start); $date <= new DateTimeImmutable($end); $date = $date->modify('+1 day')) {
        if ((int) $date->format('N') <= 5) $approvedExcuseDates[$number][$date->format('Y-m-d')] = true;
    }
}
?>
<div class="dashboard-hero panel payroll-no-print">
    <div class="dashboard-title"><span class="eyebrow">Monthly Payroll</span><h1>Payroll Attendance Register</h1><p class="muted">Attendance figures are calculated from clock records. The report opens on Employees; choose another type when needed.</p></div>
</div>

<section class="admin-box payroll-controls payroll-no-print">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-12 col-md-3"><label class="form-label" for="payroll_month">Month</label><input class="form-control" id="payroll_month" name="month" type="month" value="<?= h($selectedMonth) ?>"></div>
        <div class="col-12 col-md-4"><label class="form-label" for="payroll_department">Department</label><select class="form-select" id="payroll_department" name="department"><option value="">All Departments</option><?php foreach ($departments as $department): ?><option value="<?= h($department['department_id']) ?>" <?= $selectedDepartment === $department['department_id'] ? 'selected' : '' ?>><?= h($department['department_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="payroll_employee_type">Employee type</label><select class="form-select" id="payroll_employee_type" name="employee_type"><?php foreach ($employeeTypes as $type): ?><option value="<?= h($type) ?>" <?= $selectedEmployeeType === $type ? 'selected' : '' ?>><?= h($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-3 col-md-2"><button class="btn btn-primary w-100" type="submit">Generate</button></div>
        <div class="col-3 col-md-1"><button class="btn btn-outline-primary w-100" type="button" onclick="window.print()">Print</button></div>
    </form>
</section>

<section class="payroll-paper" aria-label="Payroll attendance register">
    <header class="payroll-header"><strong>REPUBLIC OF LIBERIA</strong><h2>Ministry of Youth and Sports</h2><h3>Monthly Attendance Report</h3><p><b>Month:</b> <?= h($monthStart->format('F Y')) ?> &nbsp;|&nbsp; <b>Department:</b> <?= h($activeFilterLabel) ?> &nbsp;|&nbsp; <b>Type:</b> <?= h($selectedEmployeeType) ?></p></header>
    <p class="payroll-note payroll-no-print">Gross salary and gender are payroll inputs for this report only. Salary is retained in this browser for the selected month; it is not yet stored in employee records.</p>
    <div class="payroll-table-wrap"><table class="payroll-table"><thead><tr><th>No.</th><th>Name / ID</th><th>Gender</th><th>Position</th><th>Gross monthly salary</th><th>Salary earned per day</th><th>Working days</th><th>Days off</th><th>Adjusted working days</th><th>Days present</th><th>Excused absence</th><th>Absent without excuse</th><th>Days late</th><th>Total deduction applicable</th><th>Deduction applied</th><th>HR comment</th></tr></thead><tbody>
<?php foreach ($payrollEmployees as $index => $employee): ?>
<?php
    $number = normalize_employee_number((string) ($employee['employee_number'] ?? ''));
    $present = 0; $late = 0;
    foreach ($workingDates as $date) {
        $record = $recordsByEmployee[$number][$date] ?? null;
        $complete = $record !== null && (($record['status'] ?? '') === 'Complete' || ((string) ($record['clock_in'] ?? '') !== '' && (string) ($record['clock_out'] ?? '') !== ''));
        if ($complete) $present++;
        if ($record !== null && !empty($record['flags']['late'])) $late++;
    }
    $excused = 0;
    foreach ($workingDates as $date) {
        $record = $recordsByEmployee[$number][$date] ?? null;
        $complete = $record !== null && (($record['status'] ?? '') === 'Complete' || ((string) ($record['clock_in'] ?? '') !== '' && (string) ($record['clock_out'] ?? '') !== ''));
        if (!$complete && isset($approvedExcuseDates[$number][$date])) $excused++;
    }
    $unexcused = max(0, count($workingDates) - $present - $excused);
?>
<tr data-employee="<?= h($number) ?>"><td><?= $index + 1 ?></td><td class="payroll-name"><strong><?= h((string) ($employee['employee_name'] ?? '-')) ?></strong><small><?= h($number) ?></small></td><td><select class="payroll-input payroll-gender" aria-label="Gender for <?= h($number) ?>"><option value="">—</option><option value="M">M</option><option value="F">F</option></select></td><td><?= h((string) ($employee['position'] ?? '-')) ?></td><td><input class="payroll-input payroll-salary" inputmode="decimal" min="0" step="0.01" type="number" aria-label="Gross monthly salary for <?= h($number) ?>" placeholder="0.00"></td><td class="payroll-daily">0.00</td><td><?= count($workingDates) ?></td><td>0</td><td><?= count($workingDates) ?></td><td class="payroll-present"><?= $present ?></td><td><?= $excused ?></td><td class="payroll-unexcused"><?= $unexcused ?></td><td><?= $late ?></td><td class="payroll-deduction">0.00</td><td><input class="payroll-input payroll-applied" inputmode="decimal" min="0" step="0.01" type="number" aria-label="Deduction applied for <?= h($number) ?>" placeholder="0.00"></td><td><input class="payroll-input payroll-comment" type="text" aria-label="HR comment for <?= h($number) ?>"></td></tr>
<?php endforeach; ?>
<?php if ($payrollEmployees === []): ?><tr><td colspan="16" class="payroll-empty">No employees found for this department.</td></tr><?php endif; ?>
</tbody></table></div>
    <footer class="payroll-footer"><span>Generated: <?= h(date('M j, Y H:i')) ?></span><span>Prepared by: ____________________</span><span>Approved by: ____________________</span></footer>
</section>
<script>
(() => {
 const key = <?= json_encode($payrollStorageKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
 const saved = JSON.parse(localStorage.getItem(key) || '{}');
 document.querySelectorAll('.payroll-table tbody tr[data-employee]').forEach(row => {
   const id = row.dataset.employee, data = saved[id] || {}, salary = row.querySelector('.payroll-salary'), gender = row.querySelector('.payroll-gender'), applied = row.querySelector('.payroll-applied'), comment = row.querySelector('.payroll-comment');
   salary.value = data.salary || ''; gender.value = data.gender || ''; applied.value = data.applied || ''; comment.value = data.comment || '';
   const update = () => { const daily = (+salary.value || 0) / <?= max(1, count($workingDates)) ?>, deduction = daily * (+row.querySelector('.payroll-unexcused').textContent || 0); row.querySelector('.payroll-daily').textContent = daily.toFixed(2); row.querySelector('.payroll-deduction').textContent = deduction.toFixed(2); saved[id] = {salary: salary.value, gender: gender.value, applied: applied.value, comment: comment.value}; localStorage.setItem(key, JSON.stringify(saved)); };
   [salary, gender, applied, comment].forEach(input => input.addEventListener('input', update)); update();
 });
})();
</script>
<?php require_once __DIR__ . '/admin_shell_end.php'; ?>
