<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';
require_roles(['admin', 'hr', 'supervisor', 'viewer']);
$activity = [];
foreach (read_attendance() as $attendanceDate => $dayRecords) foreach ($dayRecords as $record) { $clockIn = (string) ($record['clock_in'] ?? ''); $clockOut = (string) ($record['clock_out'] ?? ''); $time = $clockOut !== '' ? $clockOut : $clockIn; if ($time !== '') $activity[] = ['employee_name' => (string) ($record['employee_name'] ?? $record['employee_number'] ?? 'Employee'), 'department_name' => (string) ($record['department_name'] ?? 'Unassigned'), 'date' => (string) ($record['date'] ?? $attendanceDate), 'clock_in' => $clockIn, 'clock_out' => $clockOut, 'sort' => (string) ($record['date'] ?? $attendanceDate) . ' ' . $time]; }
usort($activity, static fn (array $a, array $b): int => strcmp($b['sort'], $a['sort']));
header('Content-Type: application/json; charset=utf-8');
echo json_encode(array_map(static function (array $item): array { unset($item['sort']); return $item; }, array_slice($activity, 0, 6)));
