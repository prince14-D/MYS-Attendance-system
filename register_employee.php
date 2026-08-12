<?php
declare(strict_types=1);

$_GET['page'] = 'register_employee';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Register Employee';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';

$employeeDirectory = [];

foreach ($employees as $employee) {
	$employeeNumber = normalize_employee_number((string) ($employee['employee_number'] ?? ''));

	if ($employeeNumber === '') {
		continue;
	}

	$employeeDirectory[$employeeNumber] = [
		'employee_number' => (string) ($employee['employee_number'] ?? ''),
		'employee_name' => (string) ($employee['employee_name'] ?? ''),
		'department_name' => (string) ($employee['department_name'] ?? 'Unassigned'),
		'position' => (string) ($employee['position'] ?? ''),
	];
}

require_once __DIR__ . '/admin_shell_start.php';
?>
<div class="dashboard-hero panel">
	<div class="dashboard-title">
		<span class="eyebrow">Setup</span>
		<h1>Register Employee</h1>
		<p class="muted">Create departments, add staff, and import records from spreadsheet files.</p>
	</div>
</div>

<?php if ($registrationResult !== null): ?>
	<div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>">
		<?= h($registrationResult['message']) ?>
	</div>
<?php endif; ?>

<div class="register-snapshot-grid" aria-label="Registration summary">
	<div class="stat-card register-mini-stat">
		<span>Departments</span>
		<strong><?= count($departments) ?></strong>
		<small><?= count($departments) > 0 ? 'Ready for assignment' : 'Create one to start registration' ?></small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Employees</span>
		<strong><?= count($employees) ?></strong>
		<small>Current staff in register</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Imports</span>
		<strong>2</strong>
		<small>Employee register and attendance files</small>
	</div>
</div>

<div class="dashboard-section setup-section bootstrap-register-surface">
	<div class="section-heading">
		<div>
			<span class="eyebrow">Setup</span>
			<h2>Directory Management</h2>
		</div>
		<p class="muted">Create departments, register employees, and import lists.</p>
	</div>

	<div class="row g-3">
		<section class="col-12 col-xl-4 admin-box bootstrap-register-card" data-admin-page="create_department">
			<h2>Create Department</h2>
			<p class="muted">Create departments before assigning employees.</p>

			<form method="post" class="stacked-form">
				<input type="hidden" name="admin_action" value="create_department">
				<label for="department_name">Department Name</label>
				<input id="department_name" name="department_name" type="text" placeholder="Example: Administration" autocomplete="organization" required>
				<p class="field-hint">Use clear department names so reports and filters stay consistent.</p>
				<button class="button secondary full-button" type="submit">Save Department</button>
			</form>
		</section>

		<section class="col-12 col-xl-8 admin-box primary-admin-box bootstrap-register-card" data-admin-page="register_employee">
			<h2>Register Employee</h2>
			<p class="muted">Add the employee name and number before they use the clock screen.</p>

			<form method="post" class="stacked-form">
				<input type="hidden" name="admin_action" value="register_employee">
				<label for="register_employee_number">Employee Number</label>
				<input id="register_employee_number" name="employee_number" type="text" placeholder="Example: EMP001" autocomplete="off" required>
				<p class="field-hint">Use the official payroll or internal HR identifier.</p>
				<div id="employeeDuplicateFeedback" class="alert alert-warning register-inline-alert" role="alert" hidden>
					<div class="fw-semibold mb-1">Duplicate employee number detected.</div>
					<div>This employee number already exists in your records.</div>
				</div>

				<div id="duplicateEmployeeCard" class="card border-warning-subtle bg-warning-subtle" hidden>
					<div class="card-body p-3">
						<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
							<strong class="text-warning-emphasis">Existing Employee Record</strong>
							<span class="badge text-bg-warning">Already Registered</span>
						</div>
						<div class="small">
							<div><strong>Number:</strong> <span id="duplicateEmployeeNumber">-</span></div>
							<div><strong>Name:</strong> <span id="duplicateEmployeeName">-</span></div>
							<div><strong>Department:</strong> <span id="duplicateEmployeeDepartment">-</span></div>
							<div><strong>Position:</strong> <span id="duplicateEmployeePosition">-</span></div>
						</div>
					</div>
				</div>

				<label for="register_employee_name">Employee Name</label>
				<input id="register_employee_name" name="employee_name" type="text" placeholder="Example: Mary Johnson" autocomplete="name" required>

				<label for="register_position">Position</label>
				<input id="register_position" name="position" type="text" placeholder="Example: Program Officer" autocomplete="organization-title">

				<label for="register_department_id">Department</label>
				<select id="register_department_id" name="department_id" required>
					<option value="">Select Department</option>
					<?php foreach ($departments as $department): ?>
						<option value="<?= h($department['department_id']) ?>"><?= h($department['department_name']) ?></option>
					<?php endforeach; ?>
				</select>
				<?php if (count($departments) === 0): ?>
					<p class="field-hint warning-hint">No department available yet. Create one first to enable registration.</p>
				<?php endif; ?>

				<button id="registerEmployeeSubmit" class="button primary full-button" type="submit" <?= count($departments) === 0 ? 'disabled' : '' ?>>Save Employee</button>
			</form>
		</section>

		<section class="col-12 col-lg-6 admin-box import-box bootstrap-register-card" data-admin-page="register_employee">
			<h2>Import Employees</h2>
			<p class="muted">Upload Employee Number, Employee Name, Position, and Department columns.</p>

			<form method="post" enctype="multipart/form-data" class="stacked-form">
				<input type="hidden" name="admin_action" value="import_employees">
				<label for="employee_file">Employee Register</label>
				<input class="import-file-input" id="employee_file" name="employee_file" type="file" accept=".xlsx,.xls,.csv" required>
				<p class="field-hint">Accepted: .xlsx, .xls, .csv</p>
				<button class="button secondary full-button" type="submit">Import Employees</button>
			</form>
		</section>

		<section class="col-12 col-lg-6 admin-box import-box bootstrap-register-card" data-admin-page="register_employee">
			<h2>Import Attendance</h2>
			<p class="muted">Upload Employee Number, Date, Position, Department, Clock In, and Clock Out columns.</p>

			<form method="post" enctype="multipart/form-data" class="stacked-form">
				<input type="hidden" name="admin_action" value="import_attendance">
				<label for="attendance_file">Excel Sheet</label>
				<input class="import-file-input" id="attendance_file" name="attendance_file" type="file" accept=".xlsx,.xls,.csv" required>
				<p class="field-hint">Include Date, Clock In, and Clock Out values in each row.</p>
				<button class="button secondary full-button" type="submit">Import Records</button>
			</form>
		</section>
	</div>
</div>

<script>
	(() => {
		const directory = <?= json_encode($employeeDirectory, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
		const numberInput = document.getElementById('register_employee_number');
		const submitButton = document.getElementById('registerEmployeeSubmit');
		const feedback = document.getElementById('employeeDuplicateFeedback');
		const duplicateCard = document.getElementById('duplicateEmployeeCard');
		const dupNumber = document.getElementById('duplicateEmployeeNumber');
		const dupName = document.getElementById('duplicateEmployeeName');
		const dupDepartment = document.getElementById('duplicateEmployeeDepartment');
		const dupPosition = document.getElementById('duplicateEmployeePosition');

		if (!numberInput || !submitButton) {
			return;
		}

		function normalizeEmployeeNumber(value) {
			return String(value || '').trim().toUpperCase();
		}

		function setDuplicateState(record) {
			const hasDuplicate = Boolean(record);

			numberInput.classList.toggle('is-invalid', hasDuplicate);
			numberInput.setAttribute('aria-invalid', hasDuplicate ? 'true' : 'false');
			submitButton.disabled = hasDuplicate || <?= count($departments) === 0 ? 'true' : 'false' ?>;

			if (!feedback || !duplicateCard) {
				return;
			}

			feedback.hidden = !hasDuplicate;
			duplicateCard.hidden = !hasDuplicate;

			if (!hasDuplicate) {
				return;
			}

			dupNumber.textContent = record.employee_number || '-';
			dupName.textContent = record.employee_name || '-';
			dupDepartment.textContent = record.department_name || 'Unassigned';
			dupPosition.textContent = record.position || '-';
		}

		function runDuplicateCheck() {
			const key = normalizeEmployeeNumber(numberInput.value);
			const match = key !== '' ? (directory[key] || null) : null;
			setDuplicateState(match);
		}

		numberInput.addEventListener('input', runDuplicateCheck);
		numberInput.addEventListener('blur', runDuplicateCheck);
		runDuplicateCheck();
	})();
</script>
<?php
require_once __DIR__ . '/admin_shell_end.php';