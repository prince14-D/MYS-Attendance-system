<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';
header('Content-Type: application/json; charset=utf-8');
$employee = find_employee((string) ($_GET['employee_number'] ?? ''));
if ($employee === null) {
    echo json_encode(['found' => false]);
    exit;
}
echo json_encode(['found' => true, 'name' => $employee['employee_name'] ?? '', 'department' => $employee['department_name'] ?? 'Unassigned']);
