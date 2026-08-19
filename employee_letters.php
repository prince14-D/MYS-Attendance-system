<?php
declare(strict_types=1);

$_GET['page'] = 'employee_letters';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Employee Letters';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
require_once __DIR__ . '/admin_shell_start.php';

$letters = array_values(read_letters());
usort($letters, static fn (array $a, array $b): int => strcmp((string) ($b['issued_at'] ?? ''), (string) ($a['issued_at'] ?? '')));
$letterResult = $registrationResult ?? null;
?>
<div class="dashboard-hero panel">
    <div class="dashboard-title">
        <span class="eyebrow">Human Resource</span>
        <h1>Employee Letters</h1>
        <p class="muted">Track warning and transfer letters issued to staff, including the issuing officer and the date of issue.</p>
    </div>
</div>

<?php if ($letterResult !== null): ?>
    <div class="alert <?= $letterResult['ok'] ? 'success' : 'error' ?>"><?= h($letterResult['message']) ?></div>
<?php endif; ?>

<section class="admin-box mb-4">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Issue Letter</span>
            <h2>Warning / Transfer Letter</h2>
        </div>
        <p class="muted">Create a new letter and log who issued it.</p>
    </div>

    <form method="post" class="row g-3">
        <input type="hidden" name="admin_action" value="issue_employee_letter">

        <div class="col-12 col-md-6">
            <label class="form-label" for="letter_employee">Employee</label>
            <select class="form-select" id="letter_employee" name="employee_number" required>
                <option value="">Select employee</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?= h((string) ($employee['employee_number'] ?? '')) ?>"><?= h((string) ($employee['employee_name'] ?? '')) ?> — <?= h((string) ($employee['employee_number'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="letter_type">Letter Type</label>
            <select class="form-select" id="letter_type" name="letter_type" required>
                <option value="">Select type</option>
                <option>Warning Letter</option>
                <option>Transfer Letter</option>
            </select>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="letter_subject">Subject</label>
            <input class="form-control" id="letter_subject" name="subject" placeholder="Example: Final Warning on Attendance">
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="letter_issuer">Issued By</label>
            <input class="form-control" id="letter_issuer" name="issued_by" value="<?= h(current_username()) ?>" required>
        </div>

        <div class="col-12">
            <label class="form-label" for="letter_details">Letter Details</label>
            <textarea class="form-control" id="letter_details" name="details" rows="4" required placeholder="Describe the reason or action being communicated."></textarea>
        </div>

        <div class="col-12">
            <label class="form-label" for="letter_notes">Notes</label>
            <textarea class="form-control" id="letter_notes" name="notes" rows="2" placeholder="Internal notes, follow-up actions, or references."></textarea>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit">Issue Letter</button>
        </div>
    </form>
</section>

<section class="admin-box">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Letter Register</span>
            <h2>Issued Letters</h2>
        </div>
        <p class="muted">Total issued: <?= count($letters) ?></p>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Letter Type</th>
                    <th>Subject</th>
                    <th>Issued By</th>
                    <th>Date Issued</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($letters as $letter): ?>
                    <tr>
                        <td>
                            <strong><?= h((string) ($letter['employee_name'] ?? '-')) ?></strong><br>
                            <small class="text-muted"><?= h((string) ($letter['employee_number'] ?? '-')) ?></small>
                        </td>
                        <td><?= h((string) ($letter['letter_type'] ?? 'Letter')) ?></td>
                        <td><?= h((string) ($letter['subject'] ?? '-')) ?></td>
                        <td><?= h((string) ($letter['issued_by'] ?? 'Unknown')) ?></td>
                        <td><?= h(date('M j, Y H:i', strtotime((string) ($letter['issued_at'] ?? 'now')))) ?></td>
                        <td><span class="badge text-bg-primary"><?= h((string) ($letter['status'] ?? 'Issued')) ?></span></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="employee_letter_export.php?letter_id=<?= h(urlencode((string) ($letter['letter_id'] ?? ''))) ?>">PDF</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($letters) === 0): ?>
                    <tr>
                        <td class="text-center text-muted py-4" colspan="6">No letters have been issued yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/admin_shell_end.php'; ?>
