<?php
declare(strict_types=1);

$_GET['page'] = 'employees';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Employees';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';

require_once __DIR__ . '/admin_shell_start.php';
?>
<div class="dashboard-hero panel">
	<div class="dashboard-title">
		<span class="eyebrow">People</span>
		<h1>Employees</h1>
		<p class="muted">Manage department names, edit staff records, and review recent attendance profiles.</p>
	</div>
</div>

<?php if ($registrationResult !== null): ?>
	<div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>">
		<?= h($registrationResult['message']) ?>
	</div>
<?php endif; ?>

<div class="register-snapshot-grid" aria-label="Employees summary">
	<div class="stat-card register-mini-stat">
		<span>Total Employees</span>
		<strong><?= count($employees) ?></strong>
		<small>Active records</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Departments</span>
		<strong><?= count($departments) ?></strong>
		<small>Organization groups</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Profile Samples</span>
		<strong><?= count($profileReviewData) ?></strong>
		<small>Recent attendance previews</small>
	</div>
</div>

<div class="dashboard-section bootstrap-employees-surface">
	<div class="row g-3">
		<section class="col-12 col-xl-4 admin-box bootstrap-employees-card" data-admin-page="employees">
			<div class="toolbar compact-toolbar">
				<div>
					<span class="eyebrow">Departments</span>
					<h2>Department List</h2>
					<p class="muted"><?= count($departments) ?> department<?= count($departments) === 1 ? '' : 's' ?></p>
				</div>
			</div>

			<?php if (count($departments) === 0): ?>
				<div class="empty small-empty">No departments created yet.</div>
			<?php else: ?>
				<div class="mini-table-wrap table-responsive">
					<table class="editable-table table table-sm table-hover align-middle mb-0">
						<thead>
							<tr>
								<th>Department</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($departments as $department): ?>
								<tr>
									<td>
										<form method="post" class="row-form">
											<input type="hidden" name="admin_action" value="update_department">
											<input type="hidden" name="department_id" value="<?= h($department['department_id']) ?>">
											<input name="department_name" type="text" value="<?= h($department['department_name']) ?>" required>
											<button class="link-button small-button" type="submit">Save</button>
										</form>
									</td>
									<td>
										<form method="post" class="inline-form" onsubmit="return confirm('Delete this department? Assigned employees will become unassigned.');">
											<input type="hidden" name="admin_action" value="delete_department">
											<input type="hidden" name="department_id" value="<?= h($department['department_id']) ?>">
											<button class="link-button danger-link" type="submit">Delete</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>

		<section class="col-12 col-xl-8 admin-box employees-box bootstrap-employees-card" data-admin-page="employees">
			<div class="toolbar compact-toolbar">
				<div>
					<span class="eyebrow">Employees</span>
					<h2>Registered Employees</h2>
					<p class="muted"><?= count($employees) ?> employee<?= count($employees) === 1 ? '' : 's' ?></p>
				</div>
				<div class="d-flex flex-wrap gap-2">
					<a class="button secondary" href="export.php?report=employees&format=pdf">Download PDF</a>
					<a class="button" href="export.php?report=employees&format=xls">Download Excel</a>
				</div>
			</div>

			<div class="records-filter-bar staff-filter-bar bootstrap-filter-bar" aria-label="Staff search">
				<div class="records-filter-item search-item bootstrap-search-item">
					<label for="employeeSearch">Search Staff</label>
					<input class="form-control" id="employeeSearch" type="search" placeholder="Search number, name, position, department">
				</div>
			</div>
			<div class="records-filter-summary" id="employeeFilterSummary" role="status" aria-live="polite"></div>

			<?php if (count($employees) === 0): ?>
				<div class="empty small-empty">No employees registered yet.</div>
			<?php else: ?>
				<div class="mini-table-wrap table-responsive">
					<table class="table table-hover table-striped align-middle mb-0">
						<thead>
							<tr>
								<th>Employee Number</th>
								<th>Name</th>
								<th>Position</th>
								<th>Department</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($employees as $employeeIndex => $employee): ?>
								<?php
									$employeeSearch = strtolower(implode(' ', [
										(string) ($employee['employee_number'] ?? ''),
										(string) ($employee['employee_name'] ?? ''),
										(string) ($employee['position'] ?? ''),
										(string) ($employee['department_name'] ?? ''),
									]));
									$employeeFormId = 'employee_' . $safe_form_id((string) $employee['employee_number']) . '_' . $employeeIndex . '_edit';
								?>
								<tr data-employee-row data-employee-search="<?= h($employeeSearch) ?>">
									<td>
										<form id="<?= h($employeeFormId) ?>" method="post" class="inline-form">
											<input type="hidden" name="admin_action" value="update_employee">
											<input type="hidden" name="original_employee_number" value="<?= h($employee['employee_number']) ?>">
										</form>
										<input class="form-control form-control-sm" form="<?= h($employeeFormId) ?>" name="employee_number" type="text" value="<?= h($employee['employee_number']) ?>" required>
									</td>
									<td><input class="form-control form-control-sm" form="<?= h($employeeFormId) ?>" name="employee_name" type="text" value="<?= h($employee['employee_name']) ?>" required></td>
									<td><input class="form-control form-control-sm" form="<?= h($employeeFormId) ?>" name="position" type="text" value="<?= h($employee['position'] ?? '') ?>"></td>
									<td>
										<select class="form-select form-select-sm" form="<?= h($employeeFormId) ?>" name="department_id">
											<option value="">Unassigned</option>
											<?php foreach ($departments as $department): ?>
												<option value="<?= h($department['department_id']) ?>" <?= ($employee['department_id'] ?? '') === $department['department_id'] ? 'selected' : '' ?>>
													<?= h($department['department_name']) ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td class="action-cell">
										<button form="<?= h($employeeFormId) ?>" class="btn btn-sm btn-primary" type="submit">Save</button>
										<form method="post" class="inline-form" onsubmit="return confirm('Delete this employee? Attendance history will remain.');">
											<input type="hidden" name="admin_action" value="delete_employee">
											<input type="hidden" name="employee_number" value="<?= h($employee['employee_number']) ?>">
											<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="empty small-empty" id="employeeFilterEmpty" hidden>No staff match your search.</div>
			<?php endif; ?>
		</section>
	</div>
</div>

<div class="dashboard-section">
	<div class="section-heading">
		<div>
			<span class="eyebrow">Review</span>
			<h2>Clock-In Profiles</h2>
		</div>
		<p class="muted">Select an employee name to preview the full attendance profile in an overlay.</p>
	</div>

	<?php if (count($profileReviews) === 0): ?>
		<div class="empty">No attendance records found for this date.</div>
	<?php else: ?>
		<div class="profile-review-shell">
			<div class="profile-name-list" role="list" aria-label="Profile review list">
				<?php foreach ($profileReviewData as $index => $profile): ?>
					<button class="profile-name-button" type="button" data-profile-index="<?= $index ?>" role="listitem"><?= h($profile['employee_name']) ?></button>
				<?php endforeach; ?>
			</div>
			<p class="muted profile-review-note">Showing <?= count($profileReviewData) ?> recent record<?= count($profileReviewData) === 1 ? '' : 's' ?> for this date.</p>
		</div>
	<?php endif; ?>
</div>
<?php
require_once __DIR__ . '/admin_shell_end.php';