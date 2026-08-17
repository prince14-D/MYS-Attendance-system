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

	<?php $quickDescriptions = ['home' => 'Your attendance snapshot and permitted shortcuts.', 'attendance_log' => 'Review the daily attendance records.', 'monthly_report' => 'Review monthly attendance and exports.', 'excuse_form' => 'Prepare and review employee excuse forms.', 'employees' => 'Manage employee records and details.', 'register_employee' => 'Register employees and import staff data.', 'create_department' => 'Create and manage departments.', 'backup_restore' => 'Download backups and restore records.', 'employee_profiles' => 'View employee profiles and documents.', 'tally_sheet' => 'Review the attendance completion tally.', 'user_management' => 'Create users and manage account access.']; ?>
	<div class="row g-3">
		<?php foreach ($adminPageLinks as $pageKey => $pageLink): ?>
			<?php if ($pageKey === 'home') { continue; } ?>
			<div class="col-12 col-md-6 col-xl-4"><a class="admin-box overview-link-card bootstrap-overview-card" href="<?= h($pageLink['href']) ?>"><span class="eyebrow"><?= h(ucfirst(current_user_role())) ?> Access</span><h2><?= h($pageLink['label']) ?></h2><p class="muted"><?= h($quickDescriptions[$pageKey] ?? 'Open this workspace page.') ?></p></a></div>
		<?php endforeach; ?>
	</div>
</div>
<?php
require_once __DIR__ . '/admin_shell_end.php';
