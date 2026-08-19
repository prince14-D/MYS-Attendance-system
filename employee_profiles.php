<?php
declare(strict_types=1);

$_GET['page'] = 'employee_profiles';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Employee Profiles';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
require_once __DIR__ . '/admin_shell_start.php';

$requestedEmployeeNumber = normalize_employee_number((string) ($_GET['employee'] ?? ''));
$profileEmployee = $requestedEmployeeNumber !== '' ? find_employee($requestedEmployeeNumber) : ($employees[0] ?? null);
$profileDocuments = $profileEmployee !== null ? employee_documents_for((string) $profileEmployee['employee_number']) : [];
$profileRecords = [];
if ($profileEmployee !== null) {
    foreach (read_attendance() as $dateRecords) {
        $record = $dateRecords[(string) $profileEmployee['employee_number']] ?? null;
        if (is_array($record)) { $profileRecords[] = $record; }
    }
    usort($profileRecords, static fn (array $a, array $b): int => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
}
$completedCount = count(array_filter($profileRecords, static fn (array $record): bool => ($record['status'] ?? '') === 'Complete'));
$latestPhoto = '';
foreach ($profileRecords as $record) { if (($record['clock_in_photo'] ?? '') !== '') { $latestPhoto = (string) $record['clock_in_photo']; break; } }
?>
<div class="dashboard-hero panel">
    <div class="dashboard-title"><span class="eyebrow">Human Resource</span><h1>Employee Profiles</h1><p class="muted">View staff profiles, attendance history, and all documents kept for each employee.</p></div>
</div>
<?php if ($registrationResult !== null): ?><div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>"><?= h($registrationResult['message']) ?></div><?php endif; ?>
<div class="employee-profiles-layout">
    <aside class="admin-box employee-profile-directory">
        <label class="form-label" for="profileSearch">Find employee</label><input class="form-control mb-3" id="profileSearch" type="search" placeholder="Name or employee number">
        <div class="profile-directory-list">
            <?php foreach ($employees as $employee): ?><a class="profile-directory-item <?= $profileEmployee !== null && $employee['employee_number'] === $profileEmployee['employee_number'] ? 'active' : '' ?>" data-profile-search="<?= h(strtolower($employee['employee_name'] . ' ' . $employee['employee_number'])) ?>" href="employee_profiles.php?employee=<?= h(urlencode($employee['employee_number'])) ?>"><strong><?= h($employee['employee_name']) ?></strong><small><?= h($employee['employee_number']) ?> · <?= h($employee['department_name'] ?: 'Unassigned') ?></small></a><?php endforeach; ?>
        </div>
    </aside>
    <section>
        <?php if ($profileEmployee === null): ?><div class="empty">No employees have been registered yet.</div><?php else: ?>
            <section class="admin-box employee-profile-card">
                <div class="employee-profile-heading"><div class="employee-profile-avatar"><?php if ($latestPhoto !== ''): ?><img src="<?= h($latestPhoto) ?>" alt="Latest clock-in photo"><?php else: ?><span><?= h(strtoupper(substr($profileEmployee['employee_name'], 0, 1))) ?></span><?php endif; ?></div><div><span class="eyebrow">Employee Profile</span><h2><?= h($profileEmployee['employee_name']) ?></h2><p class="muted mb-0"><?= h($profileEmployee['position'] ?: 'Position not recorded') ?></p></div></div>
                <dl class="employee-profile-details"><div><dt>Employee ID</dt><dd><?= h($profileEmployee['employee_number']) ?></dd></div><div><dt>Department</dt><dd><?= h($profileEmployee['department_name'] ?: 'Unassigned') ?></dd></div><div><dt>Registered</dt><dd><?= h(date('M j, Y', strtotime($profileEmployee['registered_at'] ?? 'now'))) ?></dd></div><div><dt>Attendance</dt><dd><?= $completedCount ?> complete / <?= count($profileRecords) ?> records</dd></div></dl>
                <?php if (current_user_role() === 'admin'): ?><div class="employee-profile-actions d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="employee_attendance_export.php?employee=<?= h(urlencode($profileEmployee['employee_number'])) ?>&format=csv">Download CSV</a><a class="btn btn-outline-secondary" href="employee_attendance_export.php?employee=<?= h(urlencode($profileEmployee['employee_number'])) ?>&format=pdf">Download PDF</a></div><?php endif; ?>
                <details class="employee-profile-edit"><summary>Edit profile</summary><form method="post" class="row g-3 mt-1"><input type="hidden" name="admin_action" value="update_employee_profile"><input type="hidden" name="employee_number" value="<?= h($profileEmployee['employee_number']) ?>"><div class="col-12 col-md-6"><label class="form-label" for="profile_employee_name">Full name</label><input class="form-control" id="profile_employee_name" name="employee_name" value="<?= h($profileEmployee['employee_name']) ?>" required></div><div class="col-12 col-md-6"><label class="form-label" for="profile_position">Position/Title</label><input class="form-control" id="profile_position" name="position" value="<?= h($profileEmployee['position'] ?? '') ?>"></div><div class="col-12 col-md-6"><label class="form-label" for="profile_department">Department</label><select class="form-select" id="profile_department" name="department_id"><option value="">Unassigned</option><?php foreach ($departments as $department): ?><option value="<?= h($department['department_id']) ?>" <?= ($profileEmployee['department_id'] ?? '') === $department['department_id'] ? 'selected' : '' ?>><?= h($department['department_name']) ?></option><?php endforeach; ?></select></div><div class="col-12 col-md-6 d-flex align-items-end"><button class="btn btn-primary" type="submit">Save Profile Changes</button></div></form></details>
            </section>
            <section class="admin-box employee-document-card">
                <div class="section-heading"><div><span class="eyebrow">Document Vault</span><h2>Employee Documents</h2></div><p class="muted">PDF, image, Word, and Excel files up to 10 MB.</p></div>
                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end mb-4"><input type="hidden" name="admin_action" value="upload_employee_document"><input type="hidden" name="employee_number" value="<?= h($profileEmployee['employee_number']) ?>"><div class="col-12 col-md-4"><label class="form-label" for="document_label">Document title</label><input class="form-control" id="document_label" name="document_label" placeholder="Example: Employment letter"></div><div class="col-12 col-md-5"><label class="form-label" for="employee_document">Choose file</label><input class="form-control" id="employee_document" name="employee_document" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required></div><div class="col-12 col-md-3"><button class="btn btn-primary w-100" type="submit">Upload Document</button></div></form>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Document</th><th>File</th><th>Uploaded</th><th>Size</th><th>Actions</th></tr></thead><tbody><?php foreach ($profileDocuments as $document): ?><tr><td><strong><?= h($document['label']) ?></strong></td><td><?= h($document['original_name']) ?></td><td><?= h(date('M j, Y H:i', strtotime($document['uploaded_at']))) ?></td><td><?= h(number_format(((int) $document['size']) / 1024, 1)) ?> KB</td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="employee_document.php?id=<?= h(urlencode($document['document_id'])) ?>" target="_blank" rel="noopener">View</a><form method="post" onsubmit="return confirm('Delete this document permanently?');"><input type="hidden" name="admin_action" value="delete_employee_document"><input type="hidden" name="employee_number" value="<?= h($profileEmployee['employee_number']) ?>"><input type="hidden" name="document_id" value="<?= h($document['document_id']) ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?><?php if (count($profileDocuments) === 0): ?><tr><td class="text-center text-muted py-4" colspan="5">No documents have been uploaded for this employee.</td></tr><?php endif; ?></tbody></table></div>
            </section>
            <section class="admin-box employee-history-card"><h2>Recent Attendance</h2><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Status</th></tr></thead><tbody><?php foreach (array_slice($profileRecords, 0, 12) as $record): ?><tr><td><?= h($record['date'] ?? '-') ?></td><td><?= h($record['clock_in'] ?: '-') ?></td><td><?= h($record['clock_out'] ?: '-') ?></td><td><?= h($record['status'] ?? 'Incomplete') ?></td></tr><?php endforeach; ?><?php if (count($profileRecords) === 0): ?><tr><td class="text-center text-muted py-4" colspan="4">No attendance records yet.</td></tr><?php endif; ?></tbody></table></div></section>
        <?php endif; ?>
    </section>
</div>
<script>const profileSearch = document.getElementById('profileSearch'); profileSearch?.addEventListener('input', () => { const value = profileSearch.value.trim().toLowerCase(); document.querySelectorAll('[data-profile-search]').forEach((item) => { item.hidden = value !== '' && !item.dataset.profileSearch.includes(value); }); });</script>
<?php require_once __DIR__ . '/admin_shell_end.php'; ?>
