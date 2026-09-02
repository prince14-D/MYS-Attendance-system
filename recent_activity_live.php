<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_roles(['admin', 'hr', 'supervisor', 'viewer']);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(recent_attendance_activity(6));
