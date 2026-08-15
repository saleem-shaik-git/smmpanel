<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\Provider\ProviderFactory;
use RuntimeException;

final class AdminOrderReconciliationService
{
    public static function summary(): array
    {
        $pdo = Database::connection();

        return [
            'active_orders' => (int) $pdo->query(
                "SELECT COUNT(*) FROM orders
                 WHERE provider = 'marketerum'
                   AND status NOT IN ('completed','complete','cancelled','canceled','failed')"
            )->fetchColumn(),
            'retry_backlog' => (int) $pdo->query(
                'SELECT COUNT(*) FROM order_sync_retries'
            )->fetchColumn(),
            'retry_due' => (int) $pdo->query(
                "SELECT COUNT(*) FROM order_sync_retries
                 WHERE next_attempt_at IS NULL OR next_attempt_at <= NOW()"
            )->fetchColumn(),
            'retry_exhausted' => (int) $pdo->query(
                'SELECT COUNT(*) FROM order_sync_retries WHERE attempts >= 6'
            )->fetchColumn(),
            'failed_jobs_24h' => (int) $pdo->query(
                "SELECT COUNT(*) FROM job_runs
                 WHERE status = 'failed'
                   AND started_at >= NOW() - INTERVAL 24 HOUR"
            )->fetchColumn(),
            'refunds_24h' => (float) $pdo->query(
                "SELECT COALESCE(SUM(amount),0) FROM refund_events
                 WHERE status = 'completed'
                   AND created_at >= NOW() - INTERVAL 24 HOUR"
            )->fetchColumn(),
            'refund_exceptions' => (int) $pdo->query(
                "SELECT COUNT(*) FROM refund_events WHERE status <> 'completed'"
            )->fetchColumn(),
        ];
    }

    public static function retryQueue(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $pdo = Database::connection();
        $stmt = $pdo->query(
            "SELECT r.order_id, r.attempts, r.next_attempt_at,
                    r.last_error, r.last_attempt_at,
                    o.provider_order_id, o.status, o.charge, o.remains,
                    o.created_at, u.name AS customer_name, u.email
             FROM order_sync_retries r
             JOIN orders o ON o.id = r.order_id
             JOIN users u ON u.id = o.user_id
             ORDER BY
                CASE WHEN r.next_attempt_at IS NULL OR r.next_attempt_at <= NOW() THEN 0 ELSE 1 END,
                r.next_attempt_at ASC,
                r.order_id ASC
             LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public static function activeOrders(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $pdo = Database::connection();
        $stmt = $pdo->query(
            "SELECT o.id, o.provider_order_id, o.status, o.quantity,
                    o.remains, o.charge, o.profit, o.updated_at,
                    u.name AS customer_name, u.email,
                    r.attempts, r.next_attempt_at, r.last_error
             FROM orders o
             JOIN users u ON u.id = o.user_id
             LEFT JOIN order_sync_retries r ON r.order_id = o.id
             WHERE o.provider = 'marketerum'
               AND o.status NOT IN ('completed','complete','cancelled','canceled','failed')
             ORDER BY o.updated_at ASC, o.id ASC
             LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public static function recentRefunds(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $pdo = Database::connection();
        $stmt = $pdo->query(
            "SELECT r.id, r.order_id, r.user_id, r.reason, r.amount,
                    r.reference, r.status, r.created_at,
                    u.name AS customer_name, o.status AS order_status
             FROM refund_events r
             JOIN users u ON u.id = r.user_id
             JOIN orders o ON o.id = r.order_id
             ORDER BY r.id DESC
             LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public static function recentJobs(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $pdo = Database::connection();
        $stmt = $pdo->query(
            "SELECT * FROM job_runs
             ORDER BY started_at DESC
             LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public static function recentAudit(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $pdo = Database::connection();
        $stmt = $pdo->query(
            "SELECT a.id, a.order_id, a.actor_type, a.action,
                    a.old_status, a.new_status, a.metadata, a.created_at,
                    u.name AS customer_name
             FROM order_audit_logs a
             JOIN orders o ON o.id = a.order_id
             JOIN users u ON u.id = o.user_id
             ORDER BY a.id DESC
             LIMIT {$limit}"
        );

        return $stmt->fetchAll();
    }

    public static function reconcile(int $orderId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT o.id, o.provider, o.provider_order_id, o.status,
                    o.quantity, o.charge, o.remains, o.start_count,
                    o.profit, o.updated_at, u.name AS customer_name, u.email
             FROM orders o
             JOIN users u ON u.id = o.user_id
             WHERE o.id = :id
               AND o.provider = 'marketerum'
             LIMIT 1"
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();

        if (!$order || empty($order['provider_order_id'])) {
            throw new RuntimeException('Marketerum provider order was not found.');
        }

        $provider = ProviderFactory::marketerum();
        $remote = $provider->getMultipleOrderStatuses([
            (string) $order['provider_order_id'],
        ]);
        $status = $remote[(string) $order['provider_order_id']] ?? null;

        if (!is_array($status) || isset($status['error'])) {
            throw new RuntimeException(
                is_array($status) && isset($status['error'])
                    ? (string) $status['error']
                    : 'Provider status was not returned.'
            );
        }

        $localStatus = self::normalizeStatus((string) $order['status']);
        $providerStatus = self::normalizeStatus((string) ($status['status'] ?? ''));

        return [
            'order' => $order,
            'provider' => $status,
            'local_status' => $localStatus,
            'provider_status' => $providerStatus,
            'mismatch' => $providerStatus !== '' && $localStatus !== $providerStatus,
        ];
    }

    public static function applyReconciliation(int $orderId, int $adminId): array
    {
        $before = self::reconcile($orderId);
        $provider = ProviderFactory::marketerum();

        (new OrderLifecycleService($provider))->reconcileProviderStatus(
            $orderId,
            $before['provider']
        );

        $after = self::reconcile($orderId);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "INSERT INTO order_audit_logs
                (order_id, actor_type, action, old_status, new_status, metadata)
             VALUES
                (:order_id, 'admin', 'manual_reconciliation', :old_status, :new_status, :metadata)"
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':old_status' => $before['local_status'],
            ':new_status' => $after['local_status'],
            ':metadata' => json_encode([
                'admin_id' => $adminId,
                'provider_status' => $after['provider_status'],
                'mismatch_before' => $before['mismatch'],
                'mismatch_after' => $after['mismatch'],
            ], JSON_THROW_ON_ERROR),
        ]);

        return $after;
    }

    private static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'in progress', 'in-progress' => 'in_progress',
            'complete', 'completed' => 'completed',
            'canceled', 'cancelled' => 'cancelled',
            default => str_replace(' ', '_', $status),
        };
    }
}
