<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\Provider\SmmProviderInterface;
use RuntimeException;

final class CustomerOrderTrackingService
{
    public function __construct(private readonly SmmProviderInterface $provider) {}

    public function getForCustomer(int $orderId, int $userId, bool $refresh = true): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT o.*, s.name AS service_name, s.provider_type, s.refill, s.cancel,
                    c.name AS category
             FROM orders o
             JOIN services s ON s.id = o.service_id
             LEFT JOIN categories c ON c.id = s.category_id
             WHERE o.id = :order_id AND o.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':order_id' => $orderId, ':user_id' => $userId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        if ($refresh && !empty($order['provider_order_id']) && $this->shouldRefresh($order)) {
            try {
                $remote = $this->provider->getOrderStatus((string) $order['provider_order_id']);
                if (isset($remote['status'])) {
                    (new OrderLifecycleService($this->provider))
                        ->reconcileProviderStatus($orderId, $remote);
                }
            } catch (\Throwable $e) {
                // Tracking remains useful when the provider is temporarily unavailable.
                // The next worker run will retry synchronization.
            }

            $stmt->execute([':order_id' => $orderId, ':user_id' => $userId]);
            $order = $stmt->fetch();
        }

        $historyStmt = $pdo->prepare(
            'SELECT status, start_count, remains, source, created_at
             FROM order_status_history
             WHERE order_id = :order_id
             ORDER BY id DESC
             LIMIT 20'
        );
        $historyStmt->execute([':order_id' => $orderId]);

        $order['history'] = $historyStmt->fetchAll();
        $order['charge'] = (float) $order['charge'];
        $order['quantity'] = (int) $order['quantity'];
        $order['start_count'] = $order['start_count'] !== null ? (int) $order['start_count'] : null;
        $order['remains'] = $order['remains'] !== null ? (int) $order['remains'] : null;

        return $order;
    }

    private function shouldRefresh(array $order): bool
    {
        $terminal = ['completed', 'partial', 'cancelled', 'failed', 'refunded'];
        if (in_array(strtolower((string) $order['status']), $terminal, true)) {
            return false;
        }

        if (empty($order['last_synced_at'])) {
            return true;
        }

        return strtotime((string) $order['last_synced_at']) <= time() - 8;
    }
}
