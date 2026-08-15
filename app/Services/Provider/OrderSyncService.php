<?php

declare(strict_types=1);

namespace App\Services\Provider;

use App\Services\OrderLifecycleService;
use Throwable;

final class OrderSyncService
{
    public function __construct(private readonly MarketerumProvider $provider) {}

    public function sync(int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $pdo = \App\Database::connection();

        $stmt = $pdo->query(
            "SELECT id, provider_order_id, status
             FROM orders
             WHERE provider = 'marketerum'
               AND provider_order_id IS NOT NULL
               AND status NOT IN ('completed', 'complete', 'cancelled', 'canceled', 'failed')
             ORDER BY id ASC
             LIMIT {$limit}"
        );
        $orders = $stmt->fetchAll();

        if (!$orders) {
            return [
                'checked' => 0,
                'updated' => 0,
                'failed' => 0,
                'refunds' => 0,
            ];
        }

        $ids = array_values(array_filter(array_map(
            static fn (array $order): string => (string) $order['provider_order_id'],
            $orders
        )));

        $remote = $this->provider->getMultipleOrderStatuses($ids);
        $lifecycle = new OrderLifecycleService($this->provider);

        $updated = 0;
        $failed = 0;
        $refunds = 0;

        foreach ($orders as $order) {
            $providerOrderId = (string) $order['provider_order_id'];
            $data = $remote[$providerOrderId] ?? null;

            if (!is_array($data) || isset($data['error'])) {
                $failed++;
                continue;
            }

            try {
                $beforeRefund = $this->refundCount((int) $order['id']);

                $lifecycle->reconcileProviderStatus(
                    (int) $order['id'],
                    $data
                );

                $afterRefund = $this->refundCount((int) $order['id']);
                if ($afterRefund > $beforeRefund) {
                    $refunds += $afterRefund - $beforeRefund;
                }

                $updated++;
            } catch (Throwable) {
                $failed++;
            }
        }

        return [
            'checked' => count($orders),
            'updated' => $updated,
            'failed' => $failed,
            'refunds' => $refunds,
        ];
    }

    private function refundCount(int $orderId): int
    {
        $stmt = \App\Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM refund_events
             WHERE order_id = :order_id
               AND status = 'completed'"
        );
        $stmt->execute([':order_id' => $orderId]);
        return (int) $stmt->fetchColumn();
    }
}
