<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_roles(['admin', 'hr']);

$document = find_employee_document((string) ($_GET['id'] ?? ''));
$filename = $document !== null ? basename((string) ($document['filename'] ?? '')) : '';
$path = EMPLOYEE_DOCUMENTS_DIR . '/' . $filename;
if ($document === null || $filename === '' || !is_file($path)) { http_response_code(404); exit('Document not found.'); }
header('Content-Type: ' . ($document['mime_type'] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', (string) ($document['original_name'] ?? $filename)) . '"');
readfile($path);
