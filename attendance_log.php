<?php
declare(strict_types=1);

$_GET['page'] = 'attendance_log';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Attendance Log';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';

$selectedEmployeeType = normalize_employee_type_filter((string) ($_GET['employee_type'] ?? ''));
$selectedEmployeeNumber = normalize_employee_number((string) ($_GET['employee_number'] ?? ''));
$selectedStatus = normalize_attendance_status_filter((string) ($_GET['status'] ?? 'all'));

$employeeOptions = [];

foreach ($employees as $employee) {
	$employeeNumber = normalize_employee_number((string) ($employee['employee_number'] ?? ''));

	if ($employeeNumber === '') {
		continue;
	}

	$employeeOptions[$employeeNumber] = [
		'employee_number' => $employeeNumber,
		'employee_name' => trim((string) ($employee['employee_name'] ?? '')),
		'employee_type' => (string) ($employee['employee_type'] ?? 'Employee'),
		'department_name' => trim((string) ($employee['department_name'] ?? 'Unassigned')),
	];
}

ksort($employeeOptions);

if ($selectedEmployeeNumber !== '' && !isset($employeeOptions[$selectedEmployeeNumber])) {
	$selectedEmployeeNumber = '';
}

$records = filter_attendance_records($records, $selectedEmployeeType, $selectedEmployeeNumber, $selectedStatus);

$completeRecords = count(array_filter($records, static fn (array $record): bool => ($record['status'] ?? '') === 'Complete'));
$incompleteRecords = count($records) - $completeRecords;
$clockedInRecords = count(array_filter($records, static fn (array $record): bool => ($record['clock_in'] ?? '') !== ''));
$totalWorkedMinutes = 0;
$completeWorkedMinutes = 0;

foreach ($records as $record) {
	$worked = worked_hours($record);

	if ($worked === '') {
		continue;
	}

	[$hours, $minutes] = array_map('intval', explode(':', $worked));
	$minutesWorked = ($hours * 60) + $minutes;
	$totalWorkedMinutes += $minutesWorked;

	if (($record['status'] ?? '') === 'Complete') {
		$completeWorkedMinutes += $minutesWorked;
	}
}

$averageWorkedMinutes = $completeRecords > 0 ? (int) floor($completeWorkedMinutes / $completeRecords) : 0;

$subsetParts = [];

if ($selectedEmployeeType !== '') {
	$subsetParts[] = $selectedEmployeeType;
}

if ($selectedEmployeeNumber !== '') {
	$selectedEmployeeData = $employeeOptions[$selectedEmployeeNumber] ?? null;
	$subsetParts[] = $selectedEmployeeData !== null
		? ((string) ($selectedEmployeeData['employee_name'] ?: $selectedEmployeeNumber))
		: $selectedEmployeeNumber;
}

if ($selectedStatus !== 'all') {
	$subsetParts[] = ucfirst($selectedStatus);
}

if ($subsetParts !== []) {
	$activeFilterLabel .= ' | ' . implode(' | ', $subsetParts);
}

require_once __DIR__ . '/admin_shell_start.php';
?>
<div class="dashboard-hero panel">
	<div class="dashboard-title">
		<span class="eyebrow">Records</span>
		<h1>Attendance Log</h1>
		<p class="muted">Review, filter, print, and export the attendance entries for the selected date.</p>
	</div>
</div>

<div class="stats-grid" aria-label="Attendance summary">
	<div class="stat-card">
		<span>Today Records</span>
		<strong><?= count($records) ?></strong>
		<small><?= h($activeFilterLabel) ?></small>
	</div>
	<div class="stat-card">
		<span>Clocked In</span>
		<strong><?= $clockedInRecords ?></strong>
		<small><?= count($employees) ?> registered employees</small>
	</div>
	<div class="stat-card">
		<span>Complete</span>
		<strong><?= $completeRecords ?></strong>
		<small><?= $incompleteRecords ?> incomplete</small>
	</div>
	<div class="stat-card">
		<span>Total Worked</span>
		<strong><?= h($formatWorkedMinutes($totalWorkedMinutes)) ?></strong>
		<small>Across today's records</small>
	</div>
	<div class="stat-card">
		<span>Average Time</span>
		<strong><?= h($formatWorkedMinutes($averageWorkedMinutes)) ?></strong>
		<small>Per completed shift</small>
	</div>
	<div class="stat-card">
		<span>Departments</span>
		<strong><?= count($departments) ?></strong>
		<small>Available filters</small>
	</div>
</div>

<div class="records-panel panel bootstrap-attendance-surface">
	<form method="get" class="row g-2 align-items-end mb-3" aria-label="Refresh attendance records">
		<input type="hidden" name="page" value="attendance_log">
		<div class="col-12 col-md-4 col-lg-3">
			<label class="form-label" for="refresh_date">Date</label>
			<input class="form-control" id="refresh_date" name="date" type="date" value="<?= h($selectedDate) ?>" required>
		</div>
		<div class="col-12 col-md-4 col-lg-3">
			<label class="form-label" for="refresh_department">Department</label>
			<select class="form-select" id="refresh_department" name="department" <?= $isDepartmentScoped ? 'disabled' : '' ?>>
				<option value="">All Departments</option>
				<?php foreach ($departments as $department): ?>
					<option value="<?= h($department['department_id']) ?>" <?= $selectedDepartment === $department['department_id'] ? 'selected' : '' ?>>
						<?= h($department['department_name']) ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-12 col-md-4 col-lg-3">
			<label class="form-label" for="refresh_employee_type">Employee Type</label>
			<select class="form-select" id="refresh_employee_type" name="employee_type">
				<option value="">All Types</option>
				<option value="Employee" <?= $selectedEmployeeType === 'Employee' ? 'selected' : '' ?>>Employee</option>
				<option value="Contractor" <?= $selectedEmployeeType === 'Contractor' ? 'selected' : '' ?>>Contractor</option>
				<option value="Volunteer" <?= $selectedEmployeeType === 'Volunteer' ? 'selected' : '' ?>>Volunteer</option>
			</select>
		</div>
		<div class="col-12 col-md-6 col-lg-4">
			<label class="form-label" for="refresh_employee_number">Employee</label>
			<select class="form-select" id="refresh_employee_number" name="employee_number">
				<option value="">All Employees</option>
				<?php foreach ($employeeOptions as $employeeOption): ?>
					<?php
						$employeeLabel = ($employeeOption['employee_name'] !== '' ? $employeeOption['employee_name'] : $employeeOption['employee_number'])
							. ' (' . $employeeOption['employee_number'] . ')'
							. ' - ' . ($employeeOption['employee_type'] !== '' ? $employeeOption['employee_type'] : 'Employee');
					?>
					<option value="<?= h($employeeOption['employee_number']) ?>" <?= $selectedEmployeeNumber === $employeeOption['employee_number'] ? 'selected' : '' ?>>
						<?= h($employeeLabel) ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-12 col-md-3 col-lg-2">
			<label class="form-label" for="refresh_status">Status</label>
			<select class="form-select" id="refresh_status" name="status">
				<option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>All</option>
				<option value="complete" <?= $selectedStatus === 'complete' ? 'selected' : '' ?>>Complete</option>
				<option value="incomplete" <?= $selectedStatus === 'incomplete' ? 'selected' : '' ?>>Incomplete</option>
			</select>
		</div>
		<div class="col-12 col-lg-12 d-flex flex-wrap gap-2">
			<button class="button secondary" type="submit">Refresh Records</button>
			<button class="button" type="submit" name="print" value="1">Refresh + Print</button>
		</div>
	</form>

	<div class="toolbar records-toolbar">
		<div>
			<span class="eyebrow">Records</span>
			<h2>Attendance Log</h2>
			<p class="muted"><?= count($records) ?> employee record<?= count($records) === 1 ? '' : 's' ?> for <?= h($selectedDate) ?></p>
		</div>
		<div class="records-filter-bar bootstrap-filter-bar" aria-label="Attendance filters">
			<div class="records-filter-item search-item">
				<label for="recordSearch">Quick Search</label>
				<input class="form-control" id="recordSearch" type="search" placeholder="Search number, name, position, department">
			</div>
			<div class="records-filter-item status-item">
				<label for="recordStatusFilter">Status</label>
				<select class="form-select" id="recordStatusFilter">
					<option value="all">All</option>
					<option value="complete">Complete</option>
					<option value="incomplete">Incomplete</option>
				</select>
			</div>
		</div>
		<div class="export-links">
			<a class="link-button" href="export.php?format=csv&date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>&employee_type=<?= h(urlencode($selectedEmployeeType)) ?>&employee_number=<?= h(urlencode($selectedEmployeeNumber)) ?>&status=<?= h(urlencode($selectedStatus)) ?>">CSV</a>
			<a class="link-button" href="export.php?format=xls&date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>&employee_type=<?= h(urlencode($selectedEmployeeType)) ?>&employee_number=<?= h(urlencode($selectedEmployeeNumber)) ?>&status=<?= h(urlencode($selectedStatus)) ?>">Excel</a>
			<a class="link-button" href="export.php?format=pdf&date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>&employee_type=<?= h(urlencode($selectedEmployeeType)) ?>&employee_number=<?= h(urlencode($selectedEmployeeNumber)) ?>&status=<?= h(urlencode($selectedStatus)) ?>">PDF</a>
		</div>
	</div>

	<?php if (count($records) === 0): ?>
		<div class="empty">No attendance records found for this date.</div>
	<?php else: ?>
		<div class="records-filter-summary" id="recordsFilterSummary" role="status" aria-live="polite"></div>
		<div class="table-wrap table-responsive">
			<table class="table table-hover table-striped align-middle mb-0">
				<thead>
					<tr>
						<th>Employee Number</th>
						<th>Employee Name</th>
						<th>Position</th>
						<th>Department</th>
						<th>Date</th>
						<th>Clock In</th>
						<th>Photo</th>
						<th>Clock Out</th>
						<th>Worked Hours</th>
						<th>Flags</th>
						<th>Status</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($records as $recordIndex => $record): ?>
						<?php
							$rowSearch = implode(' ', [
								(string) ($record['employee_number'] ?? ''),
								(string) ($record['employee_name'] ?? ''),
								(string) ($record['position'] ?? ''),
								(string) ($record['department_name'] ?? ''),
								(string) ($record['date'] ?? ''),
								(string) ($record['clock_in'] ?? ''),
								(string) ($record['clock_out'] ?? ''),
								(string) ($record['status'] ?? ''),
							]);
							$attendanceFormId = 'attendance_' . $safe_form_id((string) $record['date'] . '_' . (string) $record['employee_number']) . '_' . $recordIndex;
						?>
						<tr data-record-row data-record-search="<?= h(strtolower($rowSearch)) ?>" data-record-status="<?= h(strtolower((string) ($record['status'] ?? 'incomplete'))) ?>">
							<td>
								<form id="<?= h($attendanceFormId) ?>_edit" method="post" class="inline-form">
									<input type="hidden" name="admin_action" value="update_attendance">
									<input type="hidden" name="original_date" value="<?= h($record['date']) ?>">
									<input type="hidden" name="original_employee_number" value="<?= h($record['employee_number']) ?>">
								</form>
								<input class="form-control form-control-sm" form="<?= h($attendanceFormId) ?>_edit" name="employee_number" type="text" value="<?= h($record['employee_number']) ?>" required>
							</td>
							<td><input class="form-control form-control-sm" form="<?= h($attendanceFormId) ?>_edit" name="employee_name" type="text" value="<?= h($record['employee_name'] ?? '') ?>" required></td>
							<td><input class="form-control form-control-sm" form="<?= h($attendanceFormId) ?>_edit" name="position" type="text" value="<?= h($record['position'] ?? '') ?>"></td>
							<td>
								<select class="form-select form-select-sm" form="<?= h($attendanceFormId) ?>_edit" name="department_id">
									<option value="">Unassigned</option>
									<?php foreach ($departments as $department): ?>
										<option value="<?= h($department['department_id']) ?>" <?= ($record['department_id'] ?? '') === $department['department_id'] ? 'selected' : '' ?>>
											<?= h($department['department_name']) ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input class="form-control form-control-sm" form="<?= h($attendanceFormId) ?>_edit" name="date" type="date" value="<?= h($record['date']) ?>" required></td>
							<td><input class="form-control form-control-sm" form="<?= h($attendanceFormId) ?>_edit" name="clock_in" type="time" step="1" value="<?= h($record['clock_in'] ?? '') ?>"></td>
							<td>
								<?php if (($record['clock_in_photo'] ?? '') !== ''): ?>
									<a class="photo-link" href="<?= h($record['clock_in_photo']) ?>" target="_blank" rel="noopener">
										<img src="<?= h($record['clock_in_photo']) ?>" alt="Clock-in photo for <?= h($record['employee_number']) ?>">
									</a>
								<?php else: ?>
									-
								<?php endif; ?>
							</td>
							<td><input class="form-control form-control-sm" form="<?= h($attendanceFormId) ?>_edit" name="clock_out" type="time" step="1" value="<?= h($record['clock_out'] ?? '') ?>"></td>
							<td><?= h(worked_hours($record) ?: '-') ?></td>
							<td>
								<?php
									$flags = is_array($record['flags'] ?? null) ? $record['flags'] : [];
									$flagLabels = [];

									if (($flags['late'] ?? false) === true) {
										$flagLabels[] = 'Late ' . (int) ($flags['late_minutes'] ?? 0) . 'm';
									}

									if (($flags['early_out'] ?? false) === true) {
										$flagLabels[] = 'Early Out ' . (int) ($flags['early_out_minutes'] ?? 0) . 'm';
									}
								?>
								<?php if (count($flagLabels) === 0): ?>
									-
								<?php else: ?>
									<span class="flag-badge"><?= h(implode(' | ', $flagLabels)) ?></span>
								<?php endif; ?>
							</td>
							<td>
								<span class="badge <?= $record['status'] === 'Complete' ? 'complete' : 'incomplete' ?>">
									<?= h($record['status']) ?>
								</span>
							</td>
							<td class="action-cell">
								<button form="<?= h($attendanceFormId) ?>_edit" class="btn btn-sm btn-primary" type="submit">Save</button>
								<form method="post" class="inline-form" onsubmit="return confirm('Delete this attendance record?');">
									<input type="hidden" name="admin_action" value="delete_attendance">
									<input type="hidden" name="date" value="<?= h($record['date']) ?>">
									<input type="hidden" name="employee_number" value="<?= h($record['employee_number']) ?>">
									<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="empty small-empty" id="recordsFilterEmpty" hidden>No records match your search/filter.</div>
	<?php endif; ?>
</div>
<?php
require_once __DIR__ . '/admin_shell_end.php';

if ($autoPrint):
?>
<script>
	window.addEventListener('load', () => {
		window.print();
	});
</script>
<?php endif; ?>