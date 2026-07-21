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

if (!is_array($payload) || !isset($payload['records']) || !is_array($payload['records'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid sync payload.']);
    exit;
}

$records = array_slice($payload['records'], 0, 50);
$results = sync_offline_attendance($records);
$synced = count(array_filter($results, static fn (array $result): bool => $result['ok']));

echo json_encode([
    'ok' => true,
    'synced' => $synced,
    'results' => $results,
]);
