<?php
declare(strict_types=1);

$_GET['page'] = 'create_department';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Create Department';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';

require_once __DIR__ . '/admin_shell_start.php';
?>
<div class="dashboard-hero panel">
	<div class="dashboard-title">
		<span class="eyebrow">Setup</span>
		<h1>Create Department</h1>
		<p class="muted">Add and manage department names before assigning staff.</p>
	</div>
</div>

<?php if ($registrationResult !== null): ?>
	<div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>">
		<?= h($registrationResult['message']) ?>
	</div>
<?php endif; ?>

<div class="register-snapshot-grid" aria-label="Department summary">
	<div class="stat-card register-mini-stat">
		<span>Total Departments</span>
		<strong><?= count($departments) ?></strong>
		<small>Available for assignment</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Total Employees</span>
		<strong><?= count($employees) ?></strong>
		<small>Can be mapped to departments</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Action</span>
		<strong>Create</strong>
		<small>Add or rename departments below</small>
	</div>
</div>

<div class="dashboard-section bootstrap-department-surface">
	<div class="row g-3">
	<section class="col-12 col-xl-4 admin-box bootstrap-department-card" data-admin-page="create_department">
		<h2>Create Department</h2>
		<p class="muted">Create departments before assigning employees.</p>

		<form method="post" class="stacked-form">
			<input type="hidden" name="admin_action" value="create_department">
			<label for="department_name">Department Name</label>
			<input class="form-control" id="department_name" name="department_name" type="text" placeholder="Example: Administration" autocomplete="organization" required>
			<p class="field-hint">Use a clear and consistent name used by HR and reports.</p>
			<button class="button secondary full-button" type="submit">Save Department</button>
		</form>
	</section>

	<section class="col-12 col-xl-8 admin-box bootstrap-department-card" data-admin-page="create_department">
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
				<table class="editable-table table table-hover table-striped align-middle mb-0">
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
										<input class="form-control form-control-sm" name="department_name" type="text" value="<?= h($department['department_name']) ?>" required>
										<button class="btn btn-sm btn-primary" type="submit">Save</button>
									</form>
								</td>
								<td>
									<form method="post" class="inline-form" onsubmit="return confirm('Delete this department? Assigned employees will become unassigned.');">
										<input type="hidden" name="admin_action" value="delete_department">
										<input type="hidden" name="department_id" value="<?= h($department['department_id']) ?>">
										<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
</div>
<?php
require_once __DIR__ . '/admin_shell_end.php';