<?php
declare(strict_types=1);

$_GET['page'] = 'excuse_form';
require_once __DIR__ . '/admin_bootstrap.php';

$result = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = create_excuse($_POST);
}

$pageTitle = 'Employee Excuse Form';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
require_once __DIR__ . '/admin_shell_start.php';

$savedExcuse = is_array($result['excuse'] ?? null) ? $result['excuse'] : null;
$excuseRegister = excuses_for_month($selectedMonth, $selectedDepartment);
?>
<div class="dashboard-hero panel excuse-no-print">
    <div class="dashboard-title">
        <span class="eyebrow">Human Resource</span>
        <h1>Employee Excuse Form</h1>
        <p class="muted">Complete the form, then print it or download a PDF copy. <?= h((string) $monthlyTotals['excuses']) ?> excuse<?= $monthlyTotals['excuses'] === 1 ? '' : 's' ?> recorded for <?= h(date('F Y', strtotime($selectedMonth . '-01'))) ?>.</p>
    </div>
</div>

<?php if ($result !== null): ?>
    <div class="alert <?= $result['ok'] ? 'success' : 'error' ?> excuse-no-print"><?= h($result['message']) ?></div>
<?php endif; ?>

<?php if ($savedExcuse !== null): ?>
    <div class="d-flex flex-wrap gap-2 mb-3 excuse-no-print">
        <button class="btn btn-primary" type="button" onclick="window.print()">Print Form</button>
        <a class="btn btn-outline-primary" href="export.php?report=excuse&format=pdf&id=<?= h(urlencode($savedExcuse['excuse_id'])) ?>">Download PDF</a>
        <a class="btn btn-outline-secondary" href="excuse_form.php?month=<?= h(urlencode($selectedMonth)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">Create Another Form</a>
    </div>

    <section class="excuse-paper" id="printableExcuse">
        <div class="excuse-paper-header">
            <strong>REPUBLIC OF LIBERIA</strong>
            <h2>Ministry of Youth and Sports</h2>
            <p>Office of the Human Resource</p>
            <h3>Employee Excuse Form</h3>
        </div>
        <h4>Employee Information</h4>
        <div class="excuse-paper-grid"><p><b>Full Name:</b> <?= h($savedExcuse['employee_name']) ?></p><p><b>Position/Title:</b> <?= h($savedExcuse['position'] ?: '-') ?></p><p><b>Department/Division:</b> <?= h($savedExcuse['department_name'] ?: '-') ?></p><p><b>Employee ID:</b> <?= h($savedExcuse['employee_number']) ?></p></div>
        <h4>Excuse Details</h4>
        <p><b>Date(s) of Absence:</b> <?= h($savedExcuse['absence_start']) ?> to <?= h($savedExcuse['absence_end']) ?></p>
        <p><b>Time (if partial absence):</b> <?= h($savedExcuse['absence_time'] ?: '-') ?></p>
        <p><b>Reason for Excuse:</b> <?= h($savedExcuse['reason'] . ($savedExcuse['other_reason'] !== '' ? ': ' . $savedExcuse['other_reason'] : '')) ?></p>
        <h4>Supporting Documentation</h4>
        <p><?= h(implode(', ', $savedExcuse['supporting_documents']) ?: 'None') ?></p>
        <h4>Supervisor Section</h4>
        <p><b>Supervisor's Name:</b> <?= h($savedExcuse['supervisor_name'] ?: '-') ?></p><p><b>Decision:</b> <?= h($savedExcuse['supervisor_decision'] ?: 'Pending') ?></p><p><b>Comments:</b> <?= h($savedExcuse['supervisor_comments'] ?: '-') ?></p>
        <h4>HR/Administration Section</h4>
        <p><b>Reviewed By:</b> <?= h($savedExcuse['hr_reviewed_by'] ?: '-') ?></p><p><b>Approved:</b> <?= h($savedExcuse['hr_approved'] ?: 'Pending') ?></p>
        <div class="excuse-signature"><span>Human Resource Division</span><div>Signature &amp; Date: ______________________________</div></div>
    </section>
<?php else: ?>
    <section class="admin-box excuse-form-card">
        <form method="post" class="row g-3">
            <div class="col-12"><h2 class="mb-0">Employee Information</h2><p class="muted mb-0">Selecting an employee fills their stored profile details on the printed form.</p></div>
            <div class="col-12 col-md-6"><label class="form-label" for="employee_number">Employee</label><select class="form-select" id="employee_number" name="employee_number" required><option value="">Select employee</option><?php foreach ($employees as $employee): ?><option value="<?= h($employee['employee_number']) ?>"><?= h($employee['employee_name'] . ' — ' . $employee['employee_number']) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><h2 class="mb-0">Excuse Details</h2></div>
            <div class="col-12 col-md-6"><label class="form-label" for="absence_start">Date of absence</label><input class="form-control" id="absence_start" name="absence_start" type="date" required></div>
            <div class="col-12 col-md-6"><label class="form-label" for="absence_end">End date</label><input class="form-control" id="absence_end" name="absence_end" type="date" required></div>
            <div class="col-12 col-md-6"><label class="form-label" for="absence_time">Time (partial absence)</label><input class="form-control" id="absence_time" name="absence_time" placeholder="Example: 10:00 AM - 1:00 PM"></div>
            <div class="col-12 col-md-6"><label class="form-label" for="reason">Reason for excuse</label><select class="form-select" id="reason" name="reason" required><option value="">Select reason</option><option>Medical Appointment</option><option>Illness</option><option>Family Emergency</option><option>Official Assignment</option><option>Other</option></select></div>
            <div class="col-12" id="otherReasonWrap" hidden><label class="form-label" for="other_reason">Other reason</label><input class="form-control" id="other_reason" name="other_reason"></div>
            <div class="col-12"><label class="form-label d-block">Attached documents (if required)</label><?php foreach (['Medical Certificate', 'Official Assignment Letter', 'Other'] as $document): ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="supporting_documents[]" value="<?= h($document) ?>" id="doc<?= str_replace(' ', '', $document) ?>"><label class="form-check-label" for="doc<?= str_replace(' ', '', $document) ?>"><?= h($document) ?></label></div><?php endforeach; ?></div>
            <div class="col-12"><h2 class="mb-0">Supervisor Section</h2></div>
            <div class="col-12 col-md-6"><label class="form-label" for="supervisor_name">Supervisor's name</label><input class="form-control" id="supervisor_name" name="supervisor_name"></div><div class="col-12 col-md-6"><label class="form-label" for="supervisor_decision">Decision</label><select class="form-select" id="supervisor_decision" name="supervisor_decision"><option value="">Pending</option><option>Approved</option><option>Not Approved</option></select></div>
            <div class="col-12"><label class="form-label" for="supervisor_comments">Comments</label><textarea class="form-control" id="supervisor_comments" name="supervisor_comments" rows="2"></textarea></div>
            <div class="col-12"><h2 class="mb-0">HR/Administration Section</h2></div>
            <div class="col-12 col-md-6"><label class="form-label" for="hr_reviewed_by">Reviewed by</label><input class="form-control" id="hr_reviewed_by" name="hr_reviewed_by"></div><div class="col-12 col-md-6"><label class="form-label" for="hr_approved">Approved</label><select class="form-select" id="hr_approved" name="hr_approved"><option value="">Pending</option><option>Yes</option><option>No</option></select></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save &amp; Prepare Form</button></div>
        </form>
    </section>
    <script>document.getElementById('reason')?.addEventListener('change', (event) => { document.getElementById('otherReasonWrap').hidden = event.target.value !== 'Other'; });</script>
<?php endif; ?>

<section class="admin-box excuse-register excuse-no-print">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Excuse Register</span>
            <h2>Submitted Excuse Forms</h2>
        </div>
        <p class="muted">Showing <?= count($excuseRegister) ?> form<?= count($excuseRegister) === 1 ? '' : 's' ?> that overlap <?= h(date('F Y', strtotime($selectedMonth . '-01'))) ?>.</p>
    </div>
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-4"><label class="form-label" for="register_month">Month</label><input class="form-control" id="register_month" name="month" type="month" value="<?= h($selectedMonth) ?>"></div>
        <div class="col-12 col-md-5"><label class="form-label" for="register_department">Department</label><select class="form-select" id="register_department" name="department"><option value="">All Departments</option><?php foreach ($departments as $department): ?><option value="<?= h($department['department_id']) ?>" <?= $selectedDepartment === $department['department_id'] ? 'selected' : '' ?>><?= h($department['department_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-3"><button class="btn btn-outline-primary w-100" type="submit">View Register</button></div>
    </form>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Employee</th><th>Department</th><th>Absence Dates</th><th>Reason</th><th>HR Decision</th><th>Submitted</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($excuseRegister as $excuse): ?>
                    <tr>
                        <td><strong><?= h((string) ($excuse['employee_name'] ?? '-')) ?></strong><br><small class="text-muted"><?= h((string) ($excuse['employee_number'] ?? '-')) ?></small></td>
                        <td><?= h((string) ($excuse['department_name'] ?? '-')) ?></td>
                        <td><?= h((string) ($excuse['absence_start'] ?? '-')) ?> to <?= h((string) ($excuse['absence_end'] ?? '-')) ?></td>
                        <td><?= h((string) ($excuse['reason'] ?? '-')) ?></td>
                        <td><?= h((string) (($excuse['hr_approved'] ?? '') === 'Yes' ? 'Approved' : (($excuse['hr_approved'] ?? '') === 'No' ? 'Denied' : 'Pending'))) ?></td>
                        <td><?= h(date('M j, Y', strtotime((string) ($excuse['created_at'] ?? 'now')))) ?></td>
                        <td><div class="d-flex flex-wrap gap-1"><a class="btn btn-sm btn-outline-primary" href="export.php?report=excuse&format=pdf&id=<?= h(urlencode((string) ($excuse['excuse_id'] ?? ''))) ?>">PDF</a><?php if (in_array(current_user_role(), ['admin', 'hr', 'supervisor'], true)): ?><details class="excuse-review"><summary>Review</summary><form method="post" class="mt-2"><input type="hidden" name="admin_action" value="review_excuse"><input type="hidden" name="excuse_id" value="<?= h((string) ($excuse['excuse_id'] ?? '')) ?>"><select class="form-select form-select-sm mb-1" name="decision" required><option value="">Decision</option><option value="Approved">Approve</option><option value="Denied">Deny</option></select><button class="btn btn-sm btn-primary" type="submit">Save Review</button></form></details><?php endif; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($excuseRegister) === 0): ?><tr><td class="text-center text-muted py-4" colspan="7">No excuse forms found for this month and department.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/admin_shell_end.php'; ?>
