<?php
declare(strict_types=1);

/*
 * Produces a full mysqldump-equivalent .sql file (schema + data) using the
 * app's own PDO connection, for cases where the mysqldump/mysql CLI client
 * can't authenticate but the PHP app can.
 *
 * Usage: php database/export_full_sql.php > database/backups/mys_attendance-full.sql
 */

require __DIR__ . '/config.mysql.php';

$pdo = mysql_connection();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

fwrite(STDERR, 'Exporting ' . count($tables) . ' tables...' . PHP_EOL);

echo "-- MYS Attendance System full export (schema + data)\n";
echo "-- Generated: " . date('c') . "\n";
echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $table) {
    $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
    $createSql = $createRow['Create Table'] ?? '';

    echo "-- ----------------------------\n";
    echo "-- Table structure for `$table`\n";
    echo "-- ----------------------------\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $createSql . ";\n\n";

    $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
    $rowCount = (int) $countStmt->fetchColumn();

    if ($rowCount === 0) {
        continue;
    }

    fwrite(STDERR, "  $table: $rowCount rows\n");

    echo "-- ----------------------------\n";
    echo "-- Data for `$table`\n";
    echo "-- ----------------------------\n";

    $columnsStmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $columns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $columnList = '`' . implode('`, `', $columns) . '`';

    $batchSize = 200;
    $offset = 0;

    while ($offset < $rowCount) {
        $rows = $pdo->query("SELECT * FROM `$table` LIMIT $batchSize OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            break;
        }

        $valueGroups = [];

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $value = $row[$column];

                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (is_int($value) || is_float($value)) {
                    $values[] = (string) $value;
                } else {
                    $values[] = $pdo->quote((string) $value);
                }
            }

            $valueGroups[] = '(' . implode(', ', $values) . ')';
        }

        echo "INSERT INTO `$table` ($columnList) VALUES\n" . implode(",\n", $valueGroups) . ";\n";

        $offset += $batchSize;
    }

    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";

fwrite(STDERR, "Done.\n");
