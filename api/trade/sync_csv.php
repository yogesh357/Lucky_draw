<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requirePost();
requireRole('ADMIN');

if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
    errorResponse('CSV file is required under field name "csv"', 422);
}

$handle = fopen($_FILES['csv']['tmp_name'], 'rb');
if ($handle === false) {
    errorResponse('Unable to open uploaded CSV', 500);
}

$header = fgetcsv($handle);
if ($header === false) {
    fclose($handle);
    errorResponse('CSV is empty', 422);
}

$columns = array_map(static fn(string $value): string => strtolower(trim($value)), $header);
$required = ['order_id', 'user_id', 'lots', 'trade_date'];
foreach ($required as $column) {
    if (!in_array($column, $columns, true)) {
        fclose($handle);
        errorResponse('Missing required CSV column: ' . $column, 422);
    }
}

$processed = 0;
$duplicates = 0;
$rows = [];
$totalLots = 0.0;

while (($row = fgetcsv($handle)) !== false) {
    $mapped = array_combine($columns, $row);
    if ($mapped === false) {
        continue;
    }

    $rows[] = $mapped;
}

fclose($handle);

foreach ($rows as $row) {
    $user = findUserById((int) $row['user_id']);
    if (!$user) {
        continue;
    }

    $result = withTransaction(function (PDO $pdo) use ($row, $user): array {
        $lots = round((float) $row['lots'], 2);
        return processTradeCredit(
            $pdo,
            $user,
            trim((string) $row['order_id']),
            $lots,
            trim((string) $row['trade_date']),
            'CSV'
        );
    });

    if (!$result['duplicate']) {
        $processed++;
        $totalLots += (float) $row['lots'];
    } else {
        $duplicates++;
    }
}

$logStmt = db()->prepare(
    'INSERT INTO sync_logs (method, trades_processed, total_lots, duplicates_count, status, summary, created_at)
     VALUES (\'CSV\', :trades_processed, :total_lots, :duplicates_count, \'SUCCESS\', :summary, NOW())'
);
$logStmt->execute([
    'trades_processed' => $processed,
    'total_lots' => toDecimal($totalLots),
    'duplicates_count' => $duplicates,
    'summary' => sprintf('CSV sync completed with %d processed and %d duplicates', $processed, $duplicates),
]);

success([
    'message' => 'CSV sync complete',
    'processed' => $processed,
    'duplicates' => $duplicates,
    'total_rows' => count($rows),
]);
