<?php
declare(strict_types=1);

// Lightweight polling endpoint so the Document Dispatch Register auto-refreshes
// for admin/hr/supervisor without a full page reload.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_roles(['admin', 'hr', 'supervisor']);

$canDelete = in_array(current_user_role(), ['admin', 'hr'], true);
$dispatches = read_document_dispatches();

$rows = array_map(static function (array $dispatch) use ($canDelete): array {
    return [
        'dispatch_id' => (string) $dispatch['dispatch_id'],
        'document_type' => (string) ($dispatch['document_type'] ?? 'Letter'),
        'subject' => (string) ($dispatch['subject'] ?? ''),
        'recipient_name' => (string) ($dispatch['recipient_name'] ?? ''),
        'recipient_agency' => (string) ($dispatch['recipient_agency'] ?? ''),
        'dispatched_by' => (string) ($dispatch['dispatched_by'] ?? 'Unknown'),
        'dispatched_at' => (string) ($dispatch['dispatched_at'] ?? ''),
        'has_document' => ($dispatch['filename'] ?? '') !== '',
        'can_delete' => $canDelete,
    ];
}, $dispatches);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'count' => count($rows),
    'dispatches' => $rows,
    'generated_at' => date('c'),
]);
