<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use RuntimeException;

final class AdminService
{
    public static function requireAdmin(): int
    {
        $id = \App\Auth::requireLogin();
        $stmt = Database::connection()->prepare('SELECT role FROM users WHERE id=:id LIMIT 1');
        $stmt->execute([':id'=>$id]);
        if (($stmt->fetchColumn() ?: '') !== 'admin') {
            http_response_code(403);
            exit('Forbidden');
        }
        return $id;
    }

    public static function dashboard(): array
    {
        $pdo=Database::connection();
        return [
            'customers'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
            'orders'=>(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
            'pending_orders'=>(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing','in progress')")->fetchColumn(),
            'revenue'=>(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='completed'")->fetchColumn(),
            'profit'=>(float)$pdo->query("SELECT COALESCE(SUM(profit),0) FROM orders WHERE status NOT IN ('cancelled','failed')")->fetchColumn(),
            'balances'=>(float)$pdo->query("SELECT COALESCE(SUM(balance),0) FROM users WHERE role='user'")->fetchColumn(),
            'services'=>(int)$pdo->query('SELECT COUNT(*) FROM services WHERE status=1')->fetchColumn(),
        ];
    }

    public static function updateServicePricing(int $serviceId, float $sellingRate, ?float $markupPercent): void
    {
        if ($sellingRate < 0 || ($markupPercent !== null && $markupPercent < 0)) throw new RuntimeException('Invalid pricing.');
        $stmt=Database::connection()->prepare('UPDATE services SET selling_rate=:rate, markup_percent=:markup WHERE id=:id');
        $stmt->execute([':rate'=>$sellingRate, ':markup'=>$markupPercent, ':id'=>$serviceId]);
    }

    public static function setServiceStatus(int $serviceId, bool $active): void
    {
        $stmt = Database::connection()->prepare("UPDATE services SET status=:status WHERE id=:id");
        $stmt->execute([':status'=>$active ? 1 : 0, ':id'=>$serviceId]);
        if ($stmt->rowCount() === 0) {
            $exists = Database::connection()->prepare('SELECT id FROM services WHERE id=:id');
            $exists->execute([':id'=>$serviceId]);
            if ($exists->fetchColumn() === false) throw new RuntimeException('Service not found.');
        }
    }
}
