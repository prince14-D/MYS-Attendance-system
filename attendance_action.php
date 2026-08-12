<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST requests only.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid request payload.']);
    exit;
}

$result = employee_attendance_action(
    (string) ($payload['employee_number'] ?? ''),
    (string) ($payload['action'] ?? ''),
    (string) ($payload['clock_in_photo'] ?? ''),
    is_array($payload['clock_in_location'] ?? null) ? $payload['clock_in_location'] : null
);

echo json_encode($result);