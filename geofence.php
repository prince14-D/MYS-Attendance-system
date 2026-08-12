<?php
declare(strict_types=1);

$_GET['page'] = 'geofence';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Setup Geofence';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';

require_once __DIR__ . '/admin_shell_start.php';
?>
<div class="dashboard-hero panel">
	<div class="dashboard-title">
		<span class="eyebrow">Location</span>
		<h1>Setup Geofence</h1>
		<p class="muted">Require staff to be inside a radius before clock-in and fill the location from your current GPS position.</p>
	</div>
</div>

<?php if ($registrationResult !== null): ?>
	<div class="alert <?= $registrationResult['ok'] ? 'success' : 'error' ?>">
		<?= h($registrationResult['message']) ?>
	</div>
<?php endif; ?>

<div class="register-snapshot-grid" aria-label="Geofence summary">
	<div class="stat-card register-mini-stat">
		<span>Geofence Status</span>
		<strong><?= ($geofenceSettings['enabled'] ?? false) ? 'On' : 'Off' ?></strong>
		<small><?= ($geofenceSettings['enabled'] ?? false) ? 'Clock-in location check enabled' : 'Validation currently disabled' ?></small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Radius</span>
		<strong><?= (int) ($geofenceSettings['radius_meters'] ?? 150) ?>m</strong>
		<small>Allowed distance from center point</small>
	</div>
	<div class="stat-card register-mini-stat">
		<span>Location Setup</span>
		<strong><?= (($geofenceSettings['latitude'] ?? null) !== null && ($geofenceSettings['longitude'] ?? null) !== null) ? 'Saved' : 'Missing' ?></strong>
		<small>Latitude and longitude coordinates</small>
	</div>
</div>

<div class="dashboard-section bootstrap-geofence-surface">
	<div class="row g-3">
	<section class="col-12 admin-box wide-admin-box backup-box bootstrap-geofence-card">
		<h2>Clock-in Geofence</h2>
		<p class="muted">Require staff to be inside a location radius before clock-in.</p>

		<form method="post" class="stacked-form">
			<input type="hidden" name="admin_action" value="update_geofence">

			<label class="checkbox-field" for="geofence_enabled">
				<input class="form-check-input" id="geofence_enabled" name="geofence_enabled" type="checkbox" value="1" <?= ($geofenceSettings['enabled'] ?? false) ? 'checked' : '' ?>>
				Enable geofence validation on clock-in
			</label>

			<label for="geofence_latitude">Latitude</label>
			<input class="form-control" id="geofence_latitude" name="geofence_latitude" type="number" step="0.000001" min="-90" max="90" placeholder="Example: 6.300000" value="<?= h((string) (($geofenceSettings['latitude'] ?? '') === null ? '' : $geofenceSettings['latitude'])) ?>">

			<label for="geofence_longitude">Longitude</label>
			<input class="form-control" id="geofence_longitude" name="geofence_longitude" type="number" step="0.000001" min="-180" max="180" placeholder="Example: -10.800000" value="<?= h((string) (($geofenceSettings['longitude'] ?? '') === null ? '' : $geofenceSettings['longitude'])) ?>">

			<div class="geofence-tools">
				<button class="btn btn-outline-primary" type="button" id="useCurrentGeofenceLocation">Use My Current Location</button>
				<span class="geofence-status" id="geofenceAutoStatus" role="status" aria-live="polite"></span>
			</div>

			<label for="geofence_radius_meters">Allowed Radius (meters)</label>
			<input class="form-control" id="geofence_radius_meters" name="geofence_radius_meters" type="number" min="20" max="5000" step="1" value="<?= h((string) ($geofenceSettings['radius_meters'] ?? 150)) ?>" required>
			<p class="field-hint">Recommended range: 100m to 300m for office compounds.</p>

			<button class="btn btn-primary btn-lg w-100" type="submit">Save Geofence</button>
		</form>
	</section>
</div>
</div>
<?php
require_once __DIR__ . '/admin_shell_end.php';