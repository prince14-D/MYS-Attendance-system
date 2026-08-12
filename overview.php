<?php
declare(strict_types=1);

$_GET['page'] = 'home';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Overview';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';

require_once __DIR__ . '/admin_shell_start.php';
?>
<div class="dashboard-hero panel">
	<div class="dashboard-title">
		<span class="eyebrow">Admin Dashboard</span>
		<h1>Overview</h1>
		<p class="muted">Quick summary of today’s attendance and shortcuts to the main admin pages.</p>
	</div>
	<div class="overview-hero-meta">
		<div class="overview-hero-chip">Date: <?= h(date('M j, Y', strtotime($selectedDate))) ?></div>
		<div class="overview-hero-chip">Department: <?= h($activeFilterLabel) ?></div>
	</div>
</div>

<?php if ($registrationResult !== null): ?>
	<div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>">
		<?= h($registrationResult['message']) ?>
	</div>
<?php endif; ?>

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
</div>

<div class="dashboard-section bootstrap-overview-surface">
	<div class="row g-3 mb-1" aria-label="Daily performance indicators">
		<div class="col-12 col-lg-6">
			<section class="admin-box bootstrap-overview-card h-100">
				<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
					<h2 class="mb-0">Daily Completion</h2>
					<span class="badge text-bg-primary"><?= $completeRecords ?>/<?= count($records) ?></span>
				</div>
				<p class="muted mb-0">Employees who have complete clock-in and clock-out records for today.</p>
			</section>
		</div>
		<div class="col-12 col-lg-6">
			<section class="admin-box bootstrap-overview-card h-100">
				<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
					<h2 class="mb-0">Open Shifts</h2>
					<span class="badge text-bg-warning"><?= $incompleteRecords ?></span>
				</div>
				<p class="muted mb-0">Records missing clock-out values. Review from the Attendance Log page.</p>
			</section>
		</div>
	</div>

	<div class="section-heading">
		<div>
			<span class="eyebrow">Quick Access</span>
			<h2>Admin Pages</h2>
		</div>
		<p class="muted">Jump straight to the section you need.</p>
	</div>

	<div class="row g-3">
		<div class="col-12 col-md-6 col-xl-4">
			<a class="admin-box overview-link-card bootstrap-overview-card" href="attendance_log.php?date=<?= h(urlencode($selectedDate)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">
				<span class="eyebrow">Open Page</span>
				<h2>Attendance Log</h2>
				<p class="muted">Review, filter, print, and export the daily records.</p>
			</a>
		</div>
		<div class="col-12 col-md-6 col-xl-4">
			<a class="admin-box overview-link-card bootstrap-overview-card" href="monthly_report.php?month=<?= h(urlencode($selectedMonth)) ?>&department=<?= h(urlencode($selectedDepartment)) ?>">
				<span class="eyebrow">Open Page</span>
				<h2>Monthly Report</h2>
				<p class="muted">See trend charts and export monthly summaries.</p>
			</a>
		</div>
		<div class="col-12 col-md-6 col-xl-4">
			<a class="admin-box overview-link-card bootstrap-overview-card" href="employees.php">
				<span class="eyebrow">Open Page</span>
				<h2>Employees</h2>
				<p class="muted">Edit staff details, departments, and profile previews.</p>
			</a>
		</div>
		<div class="col-12 col-md-6 col-xl-4">
			<a class="admin-box overview-link-card bootstrap-overview-card" href="register_employee.php">
				<span class="eyebrow">Open Page</span>
				<h2>Register Employee</h2>
				<p class="muted">Create departments, add new staff, and import employee files.</p>
			</a>
		</div>
		<div class="col-12 col-md-6 col-xl-4">
			<a class="admin-box overview-link-card bootstrap-overview-card" href="create_department.php">
				<span class="eyebrow">Open Page</span>
				<h2>Create Department</h2>
				<p class="muted">Add or manage department names for cleaner reporting.</p>
			</a>
		</div>
		<div class="col-12 col-md-6 col-xl-4">
			<a class="admin-box overview-link-card bootstrap-overview-card" href="backup_restore.php">
				<span class="eyebrow">Open Page</span>
				<h2>Backup &amp; Restore</h2>
				<p class="muted">Download backups and restore records when needed.</p>
			</a>
		</div>
	</div>
</div>
<?php
require_once __DIR__ . '/admin_shell_end.php';