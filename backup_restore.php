<?php
declare(strict_types=1);

$_GET['page'] = 'backup_restore';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Backup & Restore';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';

require_once __DIR__ . '/admin_shell_start.php';
?>
<div class="dashboard-hero panel">
	<div class="dashboard-title">
		<span class="eyebrow">Backup</span>
		<h1>Backup & Restore</h1>
		<p class="muted">Download a full JSON snapshot or restore the live data from a backup file.</p>
	</div>
</div>

<?php if ($registrationResult !== null): ?>
	<div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>">
		<?= h($registrationResult['message']) ?>
	</div>
<?php endif; ?>

<div class="register-snapshot-grid" aria-label="Backup summary">
	<div class="stat-card register-mini-stat">
		<span>Data Coverage</span>
		<strong>Full</strong>
		<small>Attendance, employees, departments, and settings</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Restore Mode</span>
		<strong>Replace</strong>
		<small>Current live data will be overwritten</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Recommended</span>
		<strong>Backup</strong>
		<small>Download a snapshot before restoring</small>
	</div>
</div>

<div class="dashboard-section bootstrap-backup-surface">
	<div class="row g-3">
	<section class="col-12 admin-box backup-box bootstrap-backup-card">
		<h2>Backup and Restore</h2>
		<p class="muted">Download a full JSON backup or restore data from a previous backup snapshot.</p>

		<div class="backup-actions bootstrap-backup-actions">
			<form method="post" class="inline-form">
				<input type="hidden" name="admin_action" value="download_backup">
				<button class="btn btn-primary btn-lg w-100" type="submit">Download JSON Backup</button>
			</form>

			<form method="post" enctype="multipart/form-data" class="stacked-form" onsubmit="return confirm('Restore this backup? Current data will be replaced.');">
				<input type="hidden" name="admin_action" value="restore_backup">
				<label for="backup_file">Restore Backup File</label>
				<input class="form-control" id="backup_file" name="backup_file" type="file" accept=".json,application/json" required>
				<p class="field-hint warning-hint">Restoring will replace current data immediately.</p>
				<button class="btn btn-outline-danger btn-lg w-100" type="submit">Restore Backup</button>
			</form>
		</div>
	</section>
</div>
</div>
<?php
require_once __DIR__ . '/admin_shell_end.php';