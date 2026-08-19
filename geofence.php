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

			<label for="geofence_latitude">Primary Latitude</label>
			<input class="form-control" id="geofence_latitude" name="geofence_latitude" type="number" step="0.000001" min="-90" max="90" placeholder="Example: 6.300000" value="<?= h((string) (($geofenceSettings['latitude'] ?? '') === null ? '' : $geofenceSettings['latitude'])) ?>">

			<label for="geofence_longitude">Primary Longitude</label>
			<input class="form-control" id="geofence_longitude" name="geofence_longitude" type="number" step="0.000001" min="-180" max="180" placeholder="Example: -10.800000" value="<?= h((string) (($geofenceSettings['longitude'] ?? '') === null ? '' : $geofenceSettings['longitude'])) ?>">

			<div class="geofence-tools">
				<button class="btn btn-outline-primary" type="button" id="useCurrentGeofenceLocation">Use My Current Location</button>
				<span class="geofence-status" id="geofenceAutoStatus" role="status" aria-live="polite"></span>
			</div>

			<label for="geofence_radius_meters">Allowed Radius (meters)</label>
			<input class="form-control" id="geofence_radius_meters" name="geofence_radius_meters" type="number" min="20" max="5000" step="1" value="<?= h((string) ($geofenceSettings['radius_meters'] ?? 150)) ?>" required>
			<p class="field-hint">Recommended range: 100m to 300m for office compounds.</p>

			<label for="geofence_locations">Multiple allowed locations (JSON)</label>
			<textarea class="form-control" id="geofence_locations" name="geofence_locations" rows="8" spellcheck="false" placeholder='[{"name":"Head Office","latitude":6.300000,"longitude":-10.800000,"radius_meters":150},{"name":"Branch Office","latitude":6.340000,"longitude":-10.790000,"radius_meters":200}]'><?= h(json_encode($geofenceSettings['locations'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></textarea>
			<p class="field-hint">Use this for multiple approved attendance locations. Leave empty to use the primary location only.</p>

			<button class="btn btn-primary btn-lg w-100" type="submit">Save Geofence</button>
		</form>
	</section>

	<section class="col-12 admin-box wide-admin-box backup-box bootstrap-geofence-card">
		<h2>Register Phone / Device</h2>
		<p class="muted">Associate a PWA-installed phone with one approved attendance location.</p>

		<form method="post" class="stacked-form" id="deviceRegistrationForm">
			<input type="hidden" name="admin_action" id="device_admin_action" value="register_device">
			<input type="hidden" name="original_device_id" id="original_device_id" value="">

			<label for="device_name">Device Name</label>
			<input class="form-control" id="device_name" name="device_name" type="text" placeholder="Example: Office Phone Main" value="Phone <?= h(date('Ymd')) ?>">

			<label for="device_id">Device ID</label>
			<input class="form-control" id="device_id" name="device_id" type="text" placeholder="Use the generated ID or enter a custom one">

			<label for="device_location_name">Assigned Location</label>
			<select class="form-select" id="device_location_name" name="device_location_name">
				<option value="">Select a location</option>
			</select>

			<div class="geofence-tools">
				<button class="btn btn-outline-primary" type="button" id="generateDeviceIdButton">Generate Device ID</button>
			</div>

			<div class="alert muted mt-3 mb-3">
				<strong>Phone setup:</strong> open the attendance app on the device, copy the generated device ID above, and paste it into the phone when prompted. The phone must use the same ID to be allowed at this location.
			</div>

			<button class="btn btn-primary btn-lg w-100" id="saveDeviceButton" type="submit">Register Device</button>
		</form>
	</section>

	<section class="col-12 admin-box wide-admin-box backup-box bootstrap-geofence-card">
		<h2>Registered Devices</h2>
		<p class="muted">Approved phones currently available for attendance at your configured locations.</p>

		<div class="mb-3">
			<label for="device_location_filter">Filter by location</label>
			<select class="form-select" id="device_location_filter">
				<option value="">All locations</option>
			</select>
		</div>

		<?php $registeredDevices = read_devices(); ?>
		<?php if ($registeredDevices === []): ?>
			<div class="alert muted">No devices have been registered yet.</div>
		<?php else: ?>
			<div class="table-responsive">
				<table class="table table-striped table-sm align-middle mb-0">
					<thead>
						<tr>
							<th>Device Name</th>
							<th>Device ID</th>
							<th>Assigned Location</th>
							<th>Updated</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($registeredDevices as $registeredDevice): ?>
						<?php if (!is_array($registeredDevice)) { continue; } ?>
						<tr class="device-table-row" data-location="<?= h((string) ($registeredDevice['location_name'] ?? '')) ?>">
							<td><?= h((string) ($registeredDevice['device_name'] ?? 'Unnamed Device')) ?></td>
							<td><?= h((string) ($registeredDevice['device_id'] ?? '')) ?></td>
							<td><?= h((string) ($registeredDevice['location_name'] ?? 'Unassigned')) ?></td>
							<td><?= h((string) ($registeredDevice['updated_at'] ?? ($registeredDevice['registered_at'] ?? ''))) ?></td>
							<td>
								<div class="d-flex flex-wrap gap-2">
									<button class="btn btn-sm btn-outline-secondary copy-device-id" type="button" data-device-id="<?= h((string) ($registeredDevice['device_id'] ?? '')) ?>">Copy ID</button>
									<button class="btn btn-sm btn-outline-primary edit-device-button" type="button" data-device-id="<?= h((string) ($registeredDevice['device_id'] ?? '')) ?>" data-device-name="<?= h((string) ($registeredDevice['device_name'] ?? '')) ?>" data-device-location="<?= h((string) ($registeredDevice['location_name'] ?? '')) ?>">Edit</button>
									<form method="post" class="d-inline">
										<input type="hidden" name="admin_action" value="delete_device">
										<input type="hidden" name="device_id" value="<?= h((string) ($registeredDevice['device_id'] ?? '')) ?>">
										<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
									</form>
								</div>
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
<script>
    const locationTextarea = document.getElementById('geofence_locations');
    const locationSelect = document.getElementById('device_location_name');
    const deviceLocationFilter = document.getElementById('device_location_filter');
    const deviceIdInput = document.getElementById('device_id');
    const deviceNameInput = document.getElementById('device_name');
    const originalDeviceInput = document.getElementById('original_device_id');
    const deviceAdminActionInput = document.getElementById('device_admin_action');
    const saveDeviceButton = document.getElementById('saveDeviceButton');
    const generateButton = document.getElementById('generateDeviceIdButton');

    function parseLocations() {
        const raw = locationTextarea ? (locationTextarea.value || '[]') : '[]';
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function populateLocationNames() {
        const locations = parseLocations();
        const options = [{ value: '', label: 'Select a location' }];
        const filterOptions = [{ value: '', label: 'All locations' }];

        locations.forEach((location) => {
            if (location && location.name) {
                const label = String(location.name);
                options.push({ value: label, label });
                filterOptions.push({ value: label, label });
            }
        });

        if (locationSelect) {
            locationSelect.innerHTML = options.map((option) => '<option value="' + option.value + '">' + option.label + '</option>').join('');
        }

        if (deviceLocationFilter) {
            deviceLocationFilter.innerHTML = filterOptions.map((option) => '<option value="' + option.value + '">' + option.label + '</option>').join('');
        }
    }

    function resetDeviceForm() {
        if (deviceAdminActionInput) {
            deviceAdminActionInput.value = 'register_device';
        }

        if (originalDeviceInput) {
            originalDeviceInput.value = '';
        }

        if (saveDeviceButton) {
            saveDeviceButton.textContent = 'Register Device';
        }
    }

    function filterDeviceRows() {
        if (!deviceLocationFilter) {
            return;
        }

        const filterValue = deviceLocationFilter.value;
        document.querySelectorAll('.device-table-row').forEach((row) => {
            const rowMatches = (!filterValue) || (row.dataset.location || '') === filterValue;
            row.hidden = !rowMatches;
        });
    }

    function copyDeviceId(deviceId) {
        if (!deviceId) {
            return;
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(deviceId).catch(() => {});
            return;
        }

        const tempInput = document.createElement('input');
        tempInput.value = deviceId;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
    }

    if (locationTextarea && locationSelect) {
        locationTextarea.addEventListener('input', populateLocationNames);
        populateLocationNames();
    }

    if (deviceLocationFilter) {
        deviceLocationFilter.addEventListener('change', filterDeviceRows);
        filterDeviceRows();
    }

    document.querySelectorAll('.copy-device-id').forEach((button) => {
        button.addEventListener('click', () => copyDeviceId(button.dataset.deviceId || ''));
    });

    document.querySelectorAll('.edit-device-button').forEach((button) => {
        button.addEventListener('click', () => {
            if (deviceAdminActionInput) {
                deviceAdminActionInput.value = 'update_device';
            }

            if (originalDeviceInput) {
                originalDeviceInput.value = button.dataset.deviceId || '';
            }

            if (deviceIdInput) {
                deviceIdInput.value = button.dataset.deviceId || '';
            }

            if (deviceNameInput) {
                deviceNameInput.value = button.dataset.deviceName || '';
            }

            if (locationSelect) {
                locationSelect.value = button.dataset.deviceLocation || '';
            }

            if (saveDeviceButton) {
                saveDeviceButton.textContent = 'Update Device';
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    if (generateButton && deviceIdInput) {
        generateButton.addEventListener('click', () => {
            const value = (window.crypto && crypto.randomUUID)
                ? crypto.randomUUID()
                : 'device-' + Date.now() + '-' + Math.random().toString(16).slice(2, 8);
            deviceIdInput.value = String(value).toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 64);
        });
    }

    resetDeviceForm();
</script>
<?php
require_once __DIR__ . '/admin_shell_end.php';