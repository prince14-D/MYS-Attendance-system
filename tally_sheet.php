<?php
declare(strict_types=1);

$_GET['page'] = 'tally_sheet';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Attendance Tally Sheet';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
require_once __DIR__ . '/admin_shell_start.php';

$monthDate = DateTimeImmutable::createFromFormat('Y-m-d', $selectedMonth . '-01');
$tallyDays = [];
if ($monthDate !== false) {
    $daysInMonth = (int) $monthDate->format('t');
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%s-%02d', $selectedMonth, $day);
        $dateObject = new DateTimeImmutable($date);
        if ((int) $dateObject->format('N') <= 5) {
            $tallyDays[] = ['date' => $date, 'label' => (string) $day, 'weekday' => $dateObject->format('D')];
        }
    }
}

$tallyEmployees = $employees;
if ($selectedDepartment !== '') {
    $tallyEmployees = array_values(array_filter($tallyEmployees, static fn (array $employee): bool => (string) ($employee['department_id'] ?? '') === $selectedDepartment));
}

$tallyRecords = [];
foreach ($monthlyRecords as $date => $dayRecords) {
    foreach ($dayRecords as $record) {
        $employeeNumber = normalize_employee_number((string) ($record['employee_number'] ?? ''));
        if ($employeeNumber !== '') {
            $tallyRecords[$employeeNumber][$date] = $record;
        }
    }
}
?>
<div class="dashboard-hero panel tally-no-print">
    <div class="dashboard-title">
        <span class="eyebrow">Monthly Attendance</span>
        <h1>Employee Attendance Tally Sheet</h1>
        <p class="muted">A ✓ means the employee completed both clock-in and clock-out. An X means the attendance record is incomplete.</p>
    </div>
</div>

<section class="admin-box tally-controls tally-no-print">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-12 col-md-4"><label class="form-label" for="tally_month">Month</label><input class="form-control" id="tally_month" name="month" type="month" value="<?= h($selectedMonth) ?>"></div>
        <div class="col-12 col-md-5"><label class="form-label" for="tally_department">Department</label><select class="form-select" id="tally_department" name="department"><option value="">All Departments</option><?php foreach ($departments as $department): ?><option value="<?= h($department['department_id']) ?>" <?= $selectedDepartment === $department['department_id'] ? 'selected' : '' ?>><?= h($department['department_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><button class="btn btn-primary w-100" type="submit">View Sheet</button></div>
        <div class="col-6 col-md-1"><button class="btn btn-outline-primary w-100" type="button" onclick="window.print()">Print</button></div>
    </form>
</section>

<section class="tally-paper" aria-label="Employee attendance tally sheet">
    <header class="tally-header">
        <strong>REPUBLIC OF LIBERIA</strong>
        <h2>Ministry of Youth and Sports</h2>
        <p>Samuel Kanyon Doe Sports Complex, Paynesville, Montserrado County</p>
        <h3>Employee Attendance Tally Sheet</h3>
        <p><b>Month:</b> <?= h(date('F Y', strtotime($selectedMonth . '-01'))) ?> &nbsp; | &nbsp; <b>Department:</b> <?= h($activeFilterLabel) ?></p>
    </header>
    <div class="tally-key"><span><b>✓</b> Complete</span><span><b>X</b> Incomplete</span><span>Blank: no record</span></div>
    <div class="tally-table-wrap">
        <table class="tally-table">
            <thead><tr><th class="tally-number">No.</th><th class="tally-name">Employee Name</th><?php foreach ($tallyDays as $day): ?><th title="<?= h($day['date']) ?>"><?= h($day['label']) ?><small><?= h($day['weekday']) ?></small></th><?php endforeach; ?><th class="tally-total">✓</th><th class="tally-total">X</th></tr></thead>
            <tbody>
                <?php foreach ($tallyEmployees as $index => $employee): ?>
                    <?php $employeeNumber = normalize_employee_number((string) ($employee['employee_number'] ?? '')); $complete = 0; $incomplete = 0; ?>
                    <tr><td class="tally-number"><?= $index + 1 ?></td><td class="tally-name"><strong><?= h((string) ($employee['employee_name'] ?? '-')) ?></strong><small><?= h($employeeNumber) ?></small></td><?php foreach ($tallyDays as $day): ?><?php $record = $tallyRecords[$employeeNumber][$day['date']] ?? null; $isComplete = $record !== null && (($record['status'] ?? '') === 'Complete' || ((string) ($record['clock_in'] ?? '') !== '' && (string) ($record['clock_out'] ?? '') !== '')); if ($record !== null) { $isComplete ? $complete++ : $incomplete++; } ?><td class="tally-mark <?= $record === null ? '' : ($isComplete ? 'complete' : 'incomplete') ?>"><?= $record === null ? '' : ($isComplete ? '✓' : 'X') ?></td><?php endforeach; ?><td class="tally-total complete"><?= $complete ?></td><td class="tally-total incomplete"><?= $incomplete ?></td></tr>
                <?php endforeach; ?>
                <?php if (count($tallyEmployees) === 0): ?><tr><td class="tally-empty" colspan="<?= count($tallyDays) + 4 ?>">No employees found for this department.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <footer class="tally-footer"><span>Generated: <?= h(date('M j, Y H:i')) ?></span><span>Prepared by: ____________________</span><span>Approved by: ____________________</span></footer>
</section>
<?php require_once __DIR__ . '/admin_shell_end.php'; ?>
