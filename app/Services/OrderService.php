<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\Provider\SmmProviderInterface;
use RuntimeException;

final class OrderService
{
    public function __construct(private readonly SmmProviderInterface $provider) {}

    /** Reserve funds locally, call the provider, then finalize or refund. */
    public function place(int $userId, int $serviceId, array $providerFields): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $userStmt = $pdo->prepare('SELECT id, balance, status FROM users WHERE id = :id FOR UPDATE');
            $userStmt->execute([':id' => $userId]);
            $user = $userStmt->fetch();
            if (!$user || $user['status'] !== 'active') {
                throw new RuntimeException('User account is not active.');
            }

            $serviceStmt = $pdo->prepare('SELECT * FROM services WHERE id = :id AND status = 1 LIMIT 1');
            $serviceStmt->execute([':id' => $serviceId]);
            $service = $serviceStmt->fetch();
            if (!$service) {
                throw new RuntimeException('Service is unavailable.');
            }

            $quantity = isset($providerFields['quantity']) ? (int) $providerFields['quantity'] : null;
            if ($quantity !== null && ($quantity < (int) $service['min_quantity'] || $quantity > (int) $service['max_quantity'])) {
                throw new RuntimeException('Quantity is outside the service limits.');
            }

            $charge = $quantity !== null ? ((float) $service['selling_rate'] * $quantity / 1000) : (float) $service['selling_rate'];
            $providerCost = $quantity !== null ? ((float) $service['provider_rate'] * $quantity / 1000) : (float) $service['provider_rate'];
            if ((float) $user['balance'] < $charge) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $newBalance = (float) $user['balance'] - $charge;
            $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id')
                ->execute([':balance' => $newBalance, ':id' => $userId]);

            $link = (string) ($providerFields['link'] ?? $providerFields['username'] ?? $providerFields['media'] ?? '');
            $orderStmt = $pdo->prepare(
                "INSERT INTO orders (user_id, service_id, provider, link, quantity, charge, provider_cost, profit, status) "
                . "VALUES (:user_id, :service_id, 'marketerum', :link, :quantity, :charge, :provider_cost, :profit, 'pending')"
            );
            $orderStmt->execute([
                ':user_id' => $userId,
                ':service_id' => $serviceId,
                ':link' => $link,
                ':quantity' => $quantity ?? 0,
                ':charge' => $charge,
                ':provider_cost' => $providerCost,
                ':profit' => $charge - $providerCost,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                "INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, reference, description, status) "
                . "VALUES (:user_id, 'order', :amount, :before, :after, :reference, :description, 'completed')"
            )->execute([
                ':user_id' => $userId,
                ':amount' => -$charge,
                ':before' => $user['balance'],
                ':after' => $newBalance,
                ':reference' => 'ORDER-' . $orderId,
                ':description' => 'SMM order #' . $orderId,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        try {
            $providerResponse = $this->provider->addOrder(array_merge([
                'service' => (string) $service['provider_service_id'],
            ], $providerFields));
            $providerOrderId = $providerResponse['order'] ?? null;
            if ($providerOrderId === null) {
                throw new RuntimeException('Provider did not return an order ID.');
            }

            $pdo->prepare(
                "UPDATE orders SET provider_order_id = :provider_order_id, provider_raw = :provider_raw WHERE id = :id"
            )->execute([
                ':provider_order_id' => (string) $providerOrderId,
                ':provider_raw' => json_encode($providerResponse, JSON_THROW_ON_ERROR),
                ':id' => $orderId,
            ]);

            return $orderId;
        } catch (\Throwable $e) {
            $this->refundFailedReservation($orderId, $userId, $charge, $e->getMessage());
            throw $e;
        }
    }

    private function refundFailedReservation(int $orderId, int $userId, float $amount, string $reason): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $balance = (float) $stmt->fetchColumn();
            $newBalance = $balance + $amount;

            $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id')
                ->execute([':balance' => $newBalance, ':id' => $userId]);
            $pdo->prepare("UPDATE orders SET status = 'failed' WHERE id = :id")
                ->execute([':id' => $orderId]);
            $pdo->prepare(
                "INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, reference, description, status) "
                . "VALUES (:user_id, 'refund', :amount, :before, :after, :reference, :description, 'completed')"
            )->execute([
                ':user_id' => $userId,
                ':amount' => $amount,
                ':before' => $balance,
                ':after' => $newBalance,
                ':reference' => 'REFUND-ORDER-' . $orderId,
                ':description' => 'Provider order failed: ' . mb_substr($reason, 0, 180),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Order failed and automatic refund could not be completed; reconcile order #' . $orderId . ' manually.', 0, $e);
        }
    }
}
