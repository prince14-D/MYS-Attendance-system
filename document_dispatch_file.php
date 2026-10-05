<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_roles(['admin', 'hr', 'supervisor']);

$dispatch = find_document_dispatch((string) ($_GET['dispatch_id'] ?? ''));
$filename = $dispatch !== null ? basename((string) ($dispatch['filename'] ?? '')) : '';
$path = DISPATCH_DOCUMENTS_DIR . '/' . $filename;
if ($dispatch === null || $filename === '' || !is_file($path)) { http_response_code(404); exit('Supporting document not found.'); }
header('Content-Type: ' . ($dispatch['mime_type'] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) ($dispatch['original_name'] ?? $filename)) . '"');
readfile($path);
