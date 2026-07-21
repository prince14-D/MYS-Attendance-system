<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

$result = null;
$employeeDirectory = all_employees();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $result = employee_attendance_action(
        $_POST['employee_number'] ?? '',
        $_POST['action'] ?? '',
        $_POST['clock_in_photo'] ?? ''
    );
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#123f7a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="MYS Attendance">
    <title><?= h(APP_NAME) ?></title>
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" href="assets/app-icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="assets/app-icon.svg">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <main class="page">
        <header class="topbar">
            <div class="brand"><?= h(APP_NAME) ?></div>
            <nav class="nav-links" aria-label="Main navigation">
                <a href="admin.php">Admin Login</a>
            </nav>
        </header>

        <section class="hero">
            <div class="clock-panel">
                <div class="time-area">
                    <div class="date-label" id="currentDate"><?= h(date('l, F j, Y')) ?></div>
                    <div class="live-time" id="currentTime"><?= h(date('H:i:s')) ?></div>
                    <div class="time-note">Employee attendance clock</div>
                </div>

                <div class="form-area">
                    <?php if ($result !== null && $result['ok']): ?>
                        <div class="confirmation-screen">
                            <div class="confirmation-label"><?= h($result['action'] === 'clock_in' ? 'Clock In' : 'Clock Out') ?></div>
                            <h1><?= h($result['title']) ?></h1>
                            <p><?= h($result['body']) ?></p>
                            <dl>
                                <div>
                                    <dt>Employee</dt>
                                    <dd><?= h($result['employee_name']) ?></dd>
                                </div>
                                <div>
                                    <dt>Number</dt>
                                    <dd><?= h($result['employee_number']) ?></dd>
                                </div>
                                <div>
                                    <dt>Time</dt>
                                    <dd><?= h($result['time']) ?></dd>
                                </div>
                            </dl>
                            <a class="button primary full-button" href="index.php">Done</a>
                        </div>
                    <?php else: ?>
                        <h1>Clock In / Out</h1>
                        <p class="muted">Enter your registered employee number. Clock in requires a quick photo before submitting.</p>
                        <div class="offline-status" id="offlineStatus">
                            <span class="status-dot" id="statusDot"></span>
                            <span id="statusText">Checking connection...</span>
                            <strong id="queueCount"></strong>
                        </div>
                        <div class="install-app-card" id="installAppCard" hidden>
                            <span>Install this attendance app on this phone.</span>
                            <button class="button secondary" type="button" id="installAppButton">Install App</button>
                        </div>

                        <?php if ($result !== null): ?>
                            <div class="alert error">
                                <?= h($result['message']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" autocomplete="off" id="attendanceForm">
                            <label for="employee_number">Employee Number</label>
                            <input id="employee_number" name="employee_number" type="text" inputmode="text" placeholder="Example: EMP001" required autofocus>
                            <input id="clockInPhoto" name="clock_in_photo" type="hidden">

                            <div class="camera-card" id="cameraCard" hidden>
                                <div class="camera-header">
                                    <div>
                                        <strong>Clock-in photo</strong>
                                        <span>Take your photo before signing in.</span>
                                    </div>
                                    <button class="link-button small-button" type="button" id="retakePhoto" hidden>Retake</button>
                                </div>
                                <div class="camera-frame">
                                    <video id="cameraStream" autoplay playsinline muted></video>
                                    <canvas id="photoCanvas" hidden></canvas>
                                    <img id="photoPreview" alt="Captured clock-in photo" hidden>
                                </div>
                                <button class="button secondary full-button" type="button" id="capturePhoto">Take Photo</button>
                                <p class="camera-message" id="cameraMessage" role="status"></p>
                            </div>

                            <div class="actions">
                                <button class="button primary" type="submit" name="action" value="clock_in" id="clockInButton">Clock In</button>
                                <button class="button secondary" type="submit" name="action" value="clock_out">Clock Out</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script>
        const dateNode = document.getElementById('currentDate');
        const timeNode = document.getElementById('currentTime');
        const attendanceForm = document.getElementById('attendanceForm');
        const employeeInput = document.getElementById('employee_number');
        const cameraCard = document.getElementById('cameraCard');
        const cameraStream = document.getElementById('cameraStream');
        const photoCanvas = document.getElementById('photoCanvas');
        const photoPreview = document.getElementById('photoPreview');
        const photoInput = document.getElementById('clockInPhoto');
        const capturePhoto = document.getElementById('capturePhoto');
        const retakePhoto = document.getElementById('retakePhoto');
        const cameraMessage = document.getElementById('cameraMessage');
        const offlineStatus = document.getElementById('offlineStatus');
        const statusDot = document.getElementById('statusDot');
        const statusText = document.getElementById('statusText');
        const queueCount = document.getElementById('queueCount');
        const installAppCard = document.getElementById('installAppCard');
        const installAppButton = document.getElementById('installAppButton');
        const employees = <?= json_encode($employeeDirectory, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const employeesByNumber = employees.reduce((map, employee) => {
            map[String(employee.employee_number).toUpperCase()] = employee;
            return map;
        }, {});
        const offlineQueueKey = 'mys_attendance_offline_queue';
        let stream = null;
        let pendingInstallPrompt = null;

        function refreshClock() {
            const now = new Date();
            dateNode.textContent = now.toLocaleDateString(undefined, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            timeNode.textContent = now.toLocaleTimeString(undefined, { hour12: false });
        }

        refreshClock();
        setInterval(refreshClock, 1000);

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(() => {});
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            pendingInstallPrompt = event;

            if (installAppCard && !window.matchMedia('(display-mode: standalone)').matches) {
                installAppCard.hidden = false;
            }
        });

        installAppButton?.addEventListener('click', async () => {
            if (!pendingInstallPrompt) {
                return;
            }

            pendingInstallPrompt.prompt();
            await pendingInstallPrompt.userChoice;
            pendingInstallPrompt = null;
            installAppCard.hidden = true;
        });

        function readQueue() {
            try {
                const queue = JSON.parse(localStorage.getItem(offlineQueueKey) || '[]');
                return Array.isArray(queue) ? queue : [];
            } catch (error) {
                return [];
            }
        }

        function writeQueue(queue) {
            localStorage.setItem(offlineQueueKey, JSON.stringify(queue));
            updateOfflineStatus();
        }

        function updateOfflineStatus() {
            if (!offlineStatus) {
                return;
            }

            const queue = readQueue();
            const online = navigator.onLine;
            offlineStatus.classList.toggle('offline', !online);
            offlineStatus.classList.toggle('has-queue', queue.length > 0);
            statusDot.setAttribute('aria-label', online ? 'Online' : 'Offline');
            statusText.textContent = online ? 'Online mode' : 'Offline mode';
            queueCount.textContent = queue.length > 0 ? `${queue.length} waiting to sync` : '';
        }

        function showLocalConfirmation(record, employee) {
            const actionLabel = record.action === 'clock_in' ? 'Clock In' : 'Clock Out';
            const title = record.action === 'clock_in' ? 'Saved offline' : 'Saved offline';
            const body = record.action === 'clock_in'
                ? 'Your clock in was saved on this device and will sync when the connection returns.'
                : 'Your clock out was saved on this device and will sync when the connection returns.';
            const time = new Date(record.recorded_at).toLocaleTimeString(undefined, { hour12: false });

            document.querySelector('.form-area').innerHTML = `
                <div class="confirmation-screen">
                    <div class="confirmation-label">${actionLabel}</div>
                    <h1>${title}</h1>
                    <p>${body}</p>
                    <dl>
                        <div>
                            <dt>Employee</dt>
                            <dd>${escapeHtml(employee.employee_name)}</dd>
                        </div>
                        <div>
                            <dt>Number</dt>
                            <dd>${escapeHtml(record.employee_number)}</dd>
                        </div>
                        <div>
                            <dt>Time</dt>
                            <dd>${escapeHtml(time)}</dd>
                        </div>
                    </dl>
                    <a class="button primary full-button" href="index.php">Done</a>
                </div>
            `;
        }

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function saveOfflineRecord(action) {
            const employeeNumber = employeeInput.value.trim().toUpperCase();
            const employee = employeesByNumber[employeeNumber];

            if (!employee) {
                cameraMessage.textContent = 'This employee number is not registered on this device.';
                return false;
            }

            if (action === 'clock_in' && photoInput.value === '') {
                cameraCard.hidden = false;
                cameraMessage.textContent = 'Please take your photo before clocking in.';
                openCamera();
                return false;
            }

            const record = {
                id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                employee_number: employeeNumber,
                employee_name: employee.employee_name,
                action,
                clock_in_photo: action === 'clock_in' ? photoInput.value : '',
                recorded_at: new Date().toISOString()
            };
            const queue = readQueue();
            queue.push(record);
            writeQueue(queue);
            showLocalConfirmation(record, employee);

            return true;
        }

        async function syncOfflineQueue() {
            if (!navigator.onLine) {
                updateOfflineStatus();
                return;
            }

            const queue = readQueue();

            if (queue.length === 0) {
                updateOfflineStatus();
                return;
            }

            try {
                const response = await fetch('sync.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ records: queue })
                });

                if (!response.ok) {
                    throw new Error('Sync failed');
                }

                const payload = await response.json();
                const completed = new Set((payload.results || [])
                    .filter((result) => result.ok)
                    .map((result) => result.id));
                const remaining = queue.filter((record) => !completed.has(record.id));
                writeQueue(remaining);
            } catch (error) {
                updateOfflineStatus();
            }
        }

        async function openCamera() {
            if (!cameraCard || stream || employeeInput.value.trim().length < 2) {
                return;
            }

            cameraCard.hidden = false;
            cameraMessage.textContent = 'Opening camera...';

            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                    audio: false
                });
                cameraStream.srcObject = stream;
                cameraMessage.textContent = 'Camera ready.';
            } catch (error) {
                cameraMessage.textContent = 'Camera access is needed before clocking in.';
            }
        }

        function showCameraStream() {
            photoInput.value = '';
            photoPreview.hidden = true;
            cameraStream.hidden = false;
            capturePhoto.hidden = false;
            retakePhoto.hidden = true;
            cameraMessage.textContent = stream ? 'Camera ready.' : 'Opening camera...';
            openCamera();
        }

        employeeInput?.addEventListener('input', () => {
            if (employeeInput.value.trim().length >= 2) {
                openCamera();
            }
        });

        employeeInput?.addEventListener('blur', openCamera);

        capturePhoto?.addEventListener('click', () => {
            if (!stream || cameraStream.videoWidth === 0) {
                cameraMessage.textContent = 'Camera is not ready yet.';
                openCamera();
                return;
            }

            const size = Math.min(cameraStream.videoWidth, cameraStream.videoHeight);
            const sx = (cameraStream.videoWidth - size) / 2;
            const sy = (cameraStream.videoHeight - size) / 2;
            photoCanvas.width = 640;
            photoCanvas.height = 640;
            photoCanvas.getContext('2d').drawImage(cameraStream, sx, sy, size, size, 0, 0, 640, 640);

            const photoData = photoCanvas.toDataURL('image/jpeg', 0.82);
            photoInput.value = photoData;
            photoPreview.src = photoData;
            photoPreview.hidden = false;
            cameraStream.hidden = true;
            capturePhoto.hidden = true;
            retakePhoto.hidden = false;
            cameraMessage.textContent = 'Photo captured.';
        });

        retakePhoto?.addEventListener('click', showCameraStream);

        attendanceForm?.addEventListener('submit', (event) => {
            const action = event.submitter?.value;

            if (action === 'clock_in' && photoInput.value === '') {
                event.preventDefault();
                cameraCard.hidden = false;
                cameraMessage.textContent = 'Please take your photo before clocking in.';
                openCamera();
                return;
            }

            if (!navigator.onLine) {
                event.preventDefault();
                saveOfflineRecord(action);
            }
        });

        window.addEventListener('online', () => {
            updateOfflineStatus();
            syncOfflineQueue();
        });
        window.addEventListener('offline', updateOfflineStatus);
        updateOfflineStatus();
        syncOfflineQueue();
    </script>
</body>
</html>
