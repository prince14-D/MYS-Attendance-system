<?php
declare(strict_types=1);

// Lightweight polling endpoint used by index.php to auto-refresh the
// employee directory and today's clock status (e.g. newly registered staff)
// without requiring a full page reload.
require_once __DIR__ . '/storage.php';

header('Content-Type: application/json; charset=utf-8');

$date = date('Y-m-d');

echo json_encode([
    'ok' => true,
    'date' => $date,
    'employees' => all_employees(),
    'status' => attendance_status_map_for_date($date),
]);
