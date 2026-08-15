<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\Provider\SmmProviderInterface;
use RuntimeException;

final class OrderLifecycleService
{
    public function __construct(private readonly SmmProviderInterface $provider) {}

    public function reconcileProviderStatus(int $orderId, array $providerStatus): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT o.*, u.balance
                 FROM orders o
                 JOIN users u ON u.id = o.user_id
                 WHERE o.id = :id
                 FOR UPDATE'
            );
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new RuntimeException('Order not found.');
            }

            $newStatus = $this->normalizeStatus((string) ($providerStatus['status'] ?? $order['status']));
            $oldStatus = $this->normalizeStatus((string) $order['status']);
            $startCount = isset($providerStatus['start_count'])
                ? (int) $providerStatus['start_count']
                : ($order['start_count'] !== null ? (int) $order['start_count'] : null);
            $remains = isset($providerStatus['remains'])
                ? (int) $providerStatus['remains']
                : ($order['remains'] !== null ? (int) $order['remains'] : null);

            $shouldProcessRefund = in_array($newStatus, ['partial', 'cancelled', 'failed'], true)
                && $oldStatus !== $newStatus;

            $refundAmount = $shouldProcessRefund
                ? $this->calculateRefund($pdo, $order, $newStatus, $remains)
                : 0.0;

            if ($refundAmount > 0) {
                $pdo->commit();

                RefundService::refundOrder(
                    $orderId,
                    $refundAmount,
                    $this->refundReason($newStatus)
                );

                $pdo = Database::connection();
                $pdo->beginTransaction();
            }

            $profit = round(
                (float) $order['charge']
                - $this->totalRefunded($pdo, $orderId)
                - (float) $order['provider_cost'],
                4
            );

            $up = $pdo->prepare(
                'UPDATE orders
                 SET status = :status,
                     start_count = :start_count,
                     remains = :remains,
                     profit = :profit,
                     provider_raw = :raw,
                     last_synced_at = NOW(),
                     status_updated_at = CASE WHEN status <> :status_compare THEN NOW() ELSE status_updated_at END
                 WHERE id = :id'
            );
            $up->execute([
                ':status' => $newStatus,
                ':start_count' => $startCount,
                ':remains' => $remains,
                ':profit' => $profit,
                ':raw' => json_encode($providerStatus, JSON_THROW_ON_ERROR),
                ':status_compare' => $newStatus,
                ':id' => $orderId,
            ]);

            $pdo->prepare(
                'INSERT INTO order_status_history
                    (order_id, status, start_count, remains, provider_raw, source)
                 VALUES
                    (:order_id, :status, :start_count, :remains, :provider_raw, :source)'
            )->execute([
                ':order_id' => $orderId,
                ':status' => $newStatus,
                ':start_count' => $startCount,
                ':remains' => $remains,
                ':provider_raw' => json_encode($providerStatus, JSON_THROW_ON_ERROR),
                ':source' => 'provider_sync',
            ]);

            $pdo->prepare(
                'INSERT INTO order_audit_logs
                    (order_id, actor_type, action, old_status, new_status, metadata)
                 VALUES
                    (:order_id, \'system\', \'provider_status_sync\', :old_status, :new_status, :metadata)'
            )->execute([
                ':order_id' => $orderId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
                ':metadata' => json_encode([
                    'start_count' => $startCount,
                    'remains' => $remains,
                    'refund_amount' => $refundAmount,
                ], JSON_THROW_ON_ERROR),
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancel(int $orderId): array
    {
        $order = $this->getActionableOrder($orderId);
        $result = $this->provider->cancelOrder((string) $order['provider_order_id']);

        if ($this->providerActionSucceeded($result)) {
            $this->reconcileProviderStatus($orderId, [
                'status' => 'Canceled',
                'remains' => (int) $order['quantity'],
            ]);
        }

        return $result;
    }

    public function refill(int $orderId): array
    {
        $order = $this->getActionableOrder($orderId);
        $result = $this->provider->refillOrder((string) $order['provider_order_id']);
        $refillId = $result['refill'] ?? null;

        if ($refillId !== null) {
            $stmt = Database::connection()->prepare(
                'UPDATE orders SET refill_id = :refill WHERE id = :id'
            );
            $stmt->execute([
                ':refill' => (string) $refillId,
                ':id' => $orderId,
            ]);
        }

        return $result;
    }

    private function calculateRefund(\PDO $pdo, array $order, string $status, ?int $remains): float
    {
        $alreadyRefunded = $this->totalRefunded($pdo, (int) $order['id']);
        $available = max(0.0, round((float) $order['charge'] - $alreadyRefunded, 4));

        if ($available <= 0) {
            return 0.0;
        }

        if ($status === 'partial') {
            $quantity = max(1, (int) $order['quantity']);
            $remaining = max(0, $remains ?? 0);
            return min(
                $available,
                round(((float) $order['charge'] * $remaining) / $quantity, 4)
            );
        }

        return $available;
    }

    private function totalRefunded(\PDO $pdo, int $orderId): float
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM refund_events
             WHERE order_id = :order_id
               AND status = 'completed'"
        );
        $stmt->execute([':order_id' => $orderId]);
        return round((float) $stmt->fetchColumn(), 4);
    }

    private function refundReason(string $status): string
    {
        return match ($status) {
            'partial' => 'partial',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
            default => $status,
        };
    }

    private function getActionableOrder(int $id): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT *
             FROM orders
             WHERE id = :id
               AND provider = 'marketerum'
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();

        if (!$order || empty($order['provider_order_id'])) {
            throw new RuntimeException('Provider order is unavailable.');
        }

        return $order;
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'in progress', 'in-progress' => 'in_progress',
            'complete', 'completed' => 'completed',
            'canceled', 'cancelled' => 'cancelled',
            default => str_replace(' ', '_', $status),
        };
    }

    private function providerActionSucceeded(array $result): bool
    {
        if (isset($result['cancel'])) {
            return !is_array($result['cancel']);
        }

        return false;
    }
}
