<?php
declare(strict_types=1);

$_GET['page'] = 'home';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Overview';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';

require_once __DIR__ . '/admin_shell_start.php';
$completionRate = count($records) > 0 ? (int) round(($completeRecords / count($records)) * 100) : 0;
$primaryDashboardLink = $adminPageLinks['attendance_log'] ?? ($adminPageLinks['monthly_report'] ?? ($adminPageLinks['tally_sheet'] ?? null));
$recentActivity = [];
foreach (read_attendance() as $attendanceDate => $dayRecords) {
	foreach ($dayRecords as $record) {
		$time = (string) (($record['clock_out'] ?? '') !== '' ? $record['clock_out'] : ($record['clock_in'] ?? ''));
		if ($time !== '') $recentActivity[] = ['employee_name' => (string) ($record['employee_name'] ?? $record['employee_number'] ?? 'Employee'), 'department_name' => (string) ($record['department_name'] ?? 'Unassigned'), 'date' => (string) ($record['date'] ?? $attendanceDate), 'clock_in' => (string) ($record['clock_in'] ?? ''), 'clock_out' => (string) ($record['clock_out'] ?? ''), 'sort' => (string) ($record['date'] ?? $attendanceDate) . ' ' . $time];
	}
}
usort($recentActivity, static fn (array $a, array $b): int => strcmp($b['sort'], $a['sort']));
$recentActivity = array_slice($recentActivity, 0, 6);
?>
<div class="dashboard-hero panel modern-overview-hero professional-dashboard-hero">
	<div class="dashboard-title">
		<span class="eyebrow"><?= h(ucfirst(current_user_role())) ?> workspace</span>
		<h1>Good <?= (int) date('H') < 12 ? 'morning' : ((int) date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= h(current_username()) ?>.</h1>
		<p class="muted">Here is today’s attendance pulse and your available workspace shortcuts.</p>
		<div class="overview-hero-actions"><?php if ($primaryDashboardLink !== null): ?><a class="overview-primary-action" href="<?= h($primaryDashboardLink['href']) ?>">Open <?= h($primaryDashboardLink['label']) ?></a><?php endif; ?><span class="overview-live-indicator"><i></i> Live workspace</span></div>
	</div>
	<div class="overview-hero-meta">
		<div class="overview-hero-chip">Date: <?= h(date('M j, Y', strtotime($selectedDate))) ?></div>
		<div class="overview-hero-chip">Department: <?= h($activeFilterLabel) ?></div>
		<div class="overview-hero-progress"><div><span>Daily completion</span><strong><?= $completionRate ?>%</strong></div><div class="overview-progress-track"><span style="width: <?= $completionRate ?>%"></span></div><small><?= $completeRecords ?> complete of <?= count($records) ?> records</small></div>
	</div>
</div>

<?php if ($registrationResult !== null): ?>
	<div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>">
		<?= h($registrationResult['message']) ?>
	</div>
<?php endif; ?>

<div class="stats-grid modern-stats-grid" aria-label="Attendance summary">
	<div class="stat-card modern-stat-card stat-records">
		<span>Today Records</span>
		<strong><?= count($records) ?></strong>
		<small><?= h($activeFilterLabel) ?></small>
	</div>
	<div class="stat-card modern-stat-card stat-clocked">
		<span>Clocked In</span>
		<strong><?= $clockedInRecords ?></strong>
		<small><?= count($employees) ?> registered employees</small>
	</div>
	<div class="stat-card modern-stat-card stat-complete">
		<span>Complete</span>
		<strong><?= $completeRecords ?></strong>
		<small><?= $incompleteRecords ?> incomplete</small>
	</div>
	<div class="stat-card modern-stat-card stat-worked">
		<span>Total Worked</span>
		<strong><?= h($formatWorkedMinutes($totalWorkedMinutes)) ?></strong>
		<small>Across today's records</small>
	</div>
</div>

<div class="dashboard-section bootstrap-overview-surface">
	<div class="row g-3 mb-4" aria-label="Daily performance indicators">
		<div class="col-12 col-lg-6">
			<section class="admin-box bootstrap-overview-card h-100 modern-insight-card">
				<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
					<h2 class="mb-0">Daily Completion</h2>
					<span class="badge text-bg-primary"><?= $completeRecords ?>/<?= count($records) ?></span>
				</div>
				<p class="muted mb-0">Employees who have completed both clock-in and clock-out today.</p>
			</section>
		</div>
		<div class="col-12 col-lg-6">
			<section class="admin-box bootstrap-overview-card h-100 modern-insight-card attention-card">
				<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
					<h2 class="mb-0">Open Shifts</h2>
					<span class="badge text-bg-warning"><?= $incompleteRecords ?></span>
				</div>
				<p class="muted mb-0">Records still missing a clock-out and needing attention.</p>
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
			<div class="col-12 col-md-6 col-xl-4"><a class="admin-box overview-link-card bootstrap-overview-card modern-quick-link" href="<?= h($pageLink['href']) ?>"><span class="eyebrow"><?= h(ucfirst(current_user_role())) ?> Access</span><h2><?= h($pageLink['label']) ?></h2><p class="muted"><?= h($quickDescriptions[$pageKey] ?? 'Open this workspace page.') ?></p><span class="quick-link-action">Open workspace</span></a></div>
		<?php endforeach; ?>
	</div>
	<section class="admin-box live-activity-card mt-4"><div class="section-heading"><div><span class="eyebrow">Live Attendance</span><h2>Recent Clock Activity</h2></div><p class="muted"><span class="live-dot"></span> Refreshes every 15 seconds</p></div><div class="live-activity-list" id="liveActivityList"><?php foreach ($recentActivity as $activity): ?><div class="live-activity-row"><div class="live-activity-avatar"><?= h(strtoupper(substr($activity['employee_name'], 0, 1))) ?></div><div class="live-activity-person"><strong><?= h($activity['employee_name']) ?></strong><small><?= h($activity['department_name']) ?> · <?= h($activity['date']) ?></small></div><div class="live-activity-time"><span class="<?= $activity['clock_out'] !== '' ? 'clock-out' : 'clock-in' ?>"><?= $activity['clock_out'] !== '' ? 'Clocked out' : 'Clocked in' ?></span><strong><?= h($activity['clock_out'] !== '' ? $activity['clock_out'] : $activity['clock_in']) ?></strong></div></div><?php endforeach; ?><?php if (count($recentActivity) === 0): ?><div class="empty">No clock activity recorded yet.</div><?php endif; ?></div></section>
</div>
<script>(() => { const list = document.getElementById('liveActivityList'); if (!list) return; const escapeHtml = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); const refresh = async () => { try { const response = await fetch('recent_activity_live.php', {cache:'no-store'}); const rows = await response.json(); list.innerHTML = rows.length ? rows.map(item => { const out = item.clock_out !== ''; return `<div class="live-activity-row"><div class="live-activity-avatar">${escapeHtml(item.employee_name.charAt(0).toUpperCase())}</div><div class="live-activity-person"><strong>${escapeHtml(item.employee_name)}</strong><small>${escapeHtml(item.department_name)} · ${escapeHtml(item.date)}</small></div><div class="live-activity-time"><span class="${out ? 'clock-out' : 'clock-in'}">${out ? 'Clocked out' : 'Clocked in'}</span><strong>${escapeHtml(out ? item.clock_out : item.clock_in)}</strong></div></div>`; }).join('') : '<div class="empty">No clock activity recorded yet.</div>'; } catch (_) {} }; setInterval(refresh, 15000); })();</script>
<?php
require_once __DIR__ . '/admin_shell_end.php';
