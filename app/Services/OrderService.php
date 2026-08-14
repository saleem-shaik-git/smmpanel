<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\Provider\SmmProviderInterface;
use PDO;
use RuntimeException;

final class OrderService
{
    public function __construct(private readonly SmmProviderInterface $provider) {}

    /**
     * Places an order only after validating the local service and reserving
     * the user's balance. Provider submission occurs before the transaction
     * is committed so a failed provider request cannot leave a charged order.
     */
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

            $serviceStmt = $pdo->prepare(
                'SELECT * FROM services WHERE id = :id AND status = 1 LIMIT 1 FOR UPDATE'
            );
            $serviceStmt->execute([':id' => $serviceId]);
            $service = $serviceStmt->fetch();

            if (!$service) {
                throw new RuntimeException('Service is unavailable.');
            }

            $quantity = isset($providerFields['quantity']) ? (int) $providerFields['quantity'] : null;
            if ($quantity !== null && ($quantity < (int) $service['min_quantity'] || $quantity > (int) $service['max_quantity'])) {
                throw new RuntimeException('Quantity is outside the service limits.');
            }

            $charge = $quantity !== null
                ? ((float) $service['selling_rate'] * $quantity / 1000)
                : (float) $service['selling_rate'];

            if ((float) $user['balance'] < $charge) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $payload = array_merge([
                'service' => (string) $service['provider_service_id'],
            ], $providerFields);

            $providerResponse = $this->provider->addOrder($payload);
            $providerOrderId = $providerResponse['order'] ?? null;
            if ($providerOrderId === null) {
                throw new RuntimeException('Provider did not return an order ID.');
            }

            $newBalance = (float) $user['balance'] - $charge;
            $updateBalance = $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id');
            $updateBalance->execute([':balance' => $newBalance, ':id' => $userId]);

            $orderStmt = $pdo->prepare(
                'INSERT INTO orders (user_id, service_id, provider, provider_order_id, link, quantity, charge, provider_cost, profit, status, provider_raw) '
                . 'VALUES (:user_id, :service_id, :provider, :provider_order_id, :link, :quantity, :charge, :provider_cost, :profit, :status, :provider_raw)'
            );
            $providerCost = $quantity !== null
                ? ((float) $service['provider_rate'] * $quantity / 1000)
                : (float) $service['provider_rate'];
            $link = (string) ($providerFields['link'] ?? $providerFields['username'] ?? $providerFields['media'] ?? '');
            $orderStatus = 'pending';

            $orderStmt->execute([
                ':user_id' => $userId,
                ':service_id' => $serviceId,
                ':provider' => 'marketerum',
                ':provider_order_id' => (string) $providerOrderId,
                ':link' => $link,
                ':quantity' => $quantity ?? 0,
                ':charge' => $charge,
                ':provider_cost' => $providerCost,
                ':profit' => $charge - $providerCost,
                ':status' => $orderStatus,
                ':provider_raw' => json_encode($providerResponse, JSON_THROW_ON_ERROR),
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $transactionStmt = $pdo->prepare(
                'INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, reference, description, status) '
                . 'VALUES (:user_id, \'order\', :amount, :before, :after, :reference, :description, \'completed\')'
            );
            $transactionStmt->execute([
                ':user_id' => $userId,
                ':amount' => -$charge,
                ':before' => $user['balance'],
                ':after' => $newBalance,
                ':reference' => 'ORDER-' . $orderId,
                ':description' => 'SMM order #' . $orderId,
            ]);

            $pdo->commit();
            return $orderId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
