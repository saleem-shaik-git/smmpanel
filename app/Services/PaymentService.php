<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use RuntimeException;

final class PaymentService
{
    public function creditWallet(int $userId, float $amount, string $reference, string $description = 'Wallet deposit'): int
    {
        if ($amount <= 0 || $amount > 100000000) {
            throw new RuntimeException('Invalid deposit amount.');
        }
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 190) {
            throw new RuntimeException('Invalid payment reference.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $existing = $pdo->prepare('SELECT id FROM transactions WHERE reference = :reference LIMIT 1');
            $existing->execute([':reference' => $reference]);
            $transactionId = $existing->fetchColumn();
            if ($transactionId !== false) {
                $pdo->commit();
                return (int) $transactionId;
            }

            $userStmt = $pdo->prepare('SELECT id, balance, status FROM users WHERE id = :id FOR UPDATE');
            $userStmt->execute([':id' => $userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || $user['status'] !== 'active') {
                throw new RuntimeException('User account is not active.');
            }

            $before = (float) $user['balance'];
            $after = $before + $amount;
            $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id')
                ->execute([':balance' => $after, ':id' => $userId]);

            $stmt = $pdo->prepare(
                "INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, reference, description, status) " .
                "VALUES (:user_id, 'deposit', :amount, :before, :after, :reference, :description, 'completed')"
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':amount' => $amount,
                ':before' => $before,
                ':after' => $after,
                ':reference' => $reference,
                ':description' => mb_substr($description, 0, 255),
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof \PDOException && isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
                $check = $pdo->prepare('SELECT id FROM transactions WHERE reference = :reference LIMIT 1');
                $check->execute([':reference' => $reference]);
                $id = $check->fetchColumn();
                if ($id !== false) return (int)$id;
            }
            throw $e;
        }
    }
}
