<?php
declare(strict_types=1);

/*
 * Moves attendance rows older than a cutoff (default 2 years) into
 * attendance_archive, keeping the hot `attendance` table small as
 * history grows. Safe to re-run; processes in small batches so it
 * doesn't hold long locks on shared hosting.
 *
 * Usage (from the project root):
 *   php database/archive_old_attendance.php [years] [batch_size]
 *   php database/archive_old_attendance.php 2 500
 */

require_once __DIR__ . '/config.mysql.php';

$years = isset($argv[1]) ? max(1, (int) $argv[1]) : 2;
$batchSize = isset($argv[2]) ? max(1, (int) $argv[2]) : 500;

$pdo = mysql_connection();
$cutoffDate = (new DateTimeImmutable("-{$years} years"))->format('Y-m-d');

echo "Archiving attendance rows older than {$cutoffDate}...\n";

$totalArchived = 0;

$selectStmt = $pdo->prepare(
    'SELECT attendance_id FROM attendance WHERE attendance_date < :cutoff ORDER BY attendance_id LIMIT :batch_size'
);
$selectStmt->bindValue('cutoff', $cutoffDate);
$selectStmt->bindValue('batch_size', $batchSize, PDO::PARAM_INT);

while (true) {
    $selectStmt->execute();
    $ids = array_column($selectStmt->fetchAll(), 'attendance_id');

    if ($ids === []) {
        break;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            "INSERT IGNORE INTO attendance_archive
                (attendance_id, employee_number, employee_name, position, department_id, department_name,
                 attendance_date, clock_in, clock_out, clock_in_photo, clock_in_latitude, clock_in_longitude,
                 clock_in_accuracy_m, status, late, late_minutes, early_out, early_out_minutes, created_at, updated_at)
             SELECT
                attendance_id, employee_number, employee_name, position, department_id, department_name,
                attendance_date, clock_in, clock_out, clock_in_photo, clock_in_latitude, clock_in_longitude,
                clock_in_accuracy_m, status, late, late_minutes, early_out, early_out_minutes, created_at, updated_at
             FROM attendance
             WHERE attendance_id IN ($placeholders)"
        )->execute($ids);

        $pdo->prepare("DELETE FROM attendance WHERE attendance_id IN ($placeholders)")->execute($ids);

        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    $totalArchived += count($ids);
    echo "Archived {$totalArchived} rows so far...\n";
}

echo "Done. Archived {$totalArchived} attendance row(s) older than {$cutoffDate}.\n";
