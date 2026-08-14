<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Database;
use App\Services\Provider\ProviderFactory;

$pdo = Database::connection();
$provider = ProviderFactory::marketerum();

$stmt = $pdo->query(
    "SELECT id, provider_order_id FROM orders "
    . "WHERE provider = 'marketerum' AND provider_order_id IS NOT NULL "
    . "AND status IN ('pending','processing','in progress','in_progress') "
    . "ORDER BY id LIMIT 100"
);
$orders = $stmt->fetchAll();

if ($orders === []) {
    exit(0);
}

$ids = array_column($orders, 'provider_order_id');
$statuses = $provider->getMultipleOrderStatuses($ids);

$update = $pdo->prepare(
    'UPDATE orders SET status = :status, charge = :charge, start_count = :start_count, '
    . 'remains = :remains, provider_raw = :provider_raw WHERE provider = \'marketerum\' '
    . 'AND provider_order_id = :provider_order_id'
);

foreach ($statuses as $providerOrderId => $status) {
    if (!is_array($status) || isset($status['error'])) {
        continue;
    }

    $update->execute([
        ':status' => strtolower((string) ($status['status'] ?? 'pending')),
        ':charge' => (float) ($status['charge'] ?? 0),
        ':start_count' => isset($status['start_count']) ? (int) $status['start_count'] : null,
        ':remains' => isset($status['remains']) ? (int) $status['remains'] : null,
        ':provider_raw' => json_encode($status, JSON_THROW_ON_ERROR),
        ':provider_order_id' => (string) $providerOrderId,
    ]);
}
