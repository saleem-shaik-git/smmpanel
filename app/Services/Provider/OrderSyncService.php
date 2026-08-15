<?php

declare(strict_types=1);

namespace App\Services\Provider;

use App\Database;
use App\Services\OrderLifecycleService;
use Throwable;

final class OrderSyncService
{
    public function __construct(private readonly MarketerumProvider $provider) {}

    public function sync(int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $pdo = Database::connection();

        $stmt = $pdo->query(
            "SELECT o.id, o.provider_order_id, o.status
             FROM orders o
             LEFT JOIN order_sync_retries r ON r.order_id = o.id
             WHERE o.provider = 'marketerum'
               AND o.provider_order_id IS NOT NULL
               AND o.status NOT IN ('completed', 'complete', 'cancelled', 'canceled', 'failed')
               AND (r.next_attempt_at IS NULL OR r.next_attempt_at <= NOW())
             ORDER BY o.id ASC
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

        try {
            $remote = $this->provider->getMultipleOrderStatuses($ids);
        } catch (Throwable $e) {
            foreach ($orders as $order) {
                $this->recordFailure((int) $order['id'], $e->getMessage());
            }

            return [
                'checked' => count($orders),
                'updated' => 0,
                'failed' => count($orders),
                'refunds' => 0,
            ];
        }

        $lifecycle = new OrderLifecycleService($this->provider);
        $updated = 0;
        $failed = 0;
        $refunds = 0;

        foreach ($orders as $order) {
            $orderId = (int) $order['id'];
            $providerOrderId = (string) $order['provider_order_id'];
            $data = $remote[$providerOrderId] ?? null;

            if (!is_array($data) || isset($data['error'])) {
                $this->recordFailure(
                    $orderId,
                    is_array($data) && isset($data['error'])
                        ? (string) $data['error']
                        : 'Provider status was not returned.'
                );
                $failed++;
                continue;
            }

            try {
                $beforeRefund = $this->refundCount($orderId);

                $lifecycle->reconcileProviderStatus($orderId, $data);

                $afterRefund = $this->refundCount($orderId);
                if ($afterRefund > $beforeRefund) {
                    $refunds += $afterRefund - $beforeRefund;
                }

                $this->clearFailure($orderId);
                $updated++;
            } catch (Throwable $e) {
                $this->recordFailure($orderId, $e->getMessage());
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

    private function recordFailure(int $orderId, string $error): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO order_sync_retries
                (order_id, attempts, next_attempt_at, last_error, last_attempt_at)
             VALUES
                (:order_id, 1, DATE_ADD(NOW(), INTERVAL 1 MINUTE), :error, NOW())
             ON DUPLICATE KEY UPDATE
                attempts = attempts + 1,
                next_attempt_at = DATE_ADD(
                    NOW(),
                    INTERVAL LEAST(60, POW(2, LEAST(attempts, 6))) MINUTE
                ),
                last_error = VALUES(last_error),
                last_attempt_at = NOW()"
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':error' => mb_substr($error, 0, 1000),
        ]);
    }

    private function clearFailure(int $orderId): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM order_sync_retries WHERE order_id = :order_id'
        );
        $stmt->execute([':order_id' => $orderId]);
    }

    private function refundCount(int $orderId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM refund_events
             WHERE order_id = :order_id
               AND status = 'completed'"
        );
        $stmt->execute([':order_id' => $orderId]);
        return (int) $stmt->fetchColumn();
    }
}
