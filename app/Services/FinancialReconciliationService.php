<?php

declare(strict_types=1);
namespace App\Services;
use App\Database;
final class FinancialReconciliationService
{
    public static function summary(int $days=30):array{$days=max(1,min(365,$days));$pdo=Database::connection();$payments=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payment_intents WHERE status='paid' AND paid_at>=NOW()-INTERVAL {$days} DAY")->fetchColumn();$deposits=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='completed' AND created_at>=NOW()-INTERVAL {$days} DAY")->fetchColumn();$ledger=(float)$pdo->query("SELECT COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END),0) FROM wallet_ledger WHERE created_at>=NOW()-INTERVAL {$days} DAY")->fetchColumn();$refunds=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM refund_events WHERE status='completed' AND created_at>=NOW()-INTERVAL {$days} DAY")->fetchColumn();$balance=(float)$pdo->query("SELECT COALESCE(SUM(balance),0) FROM users WHERE role='user'")->fetchColumn();return ['days'=>$days,'paid_payments'=>$payments,'completed_deposits'=>$deposits,'ledger_net'=>$ledger,'refunds'=>$refunds,'customer_wallet_balance'=>$balance,'payment_transaction_difference'=>round($payments-$deposits,4),'ledger_transaction_difference'=>round($deposits-$ledger,4)];}
    public static function exceptions(int $limit=100):array{$limit=max(1,min(500,$limit));$pdo=Database::connection();$q=$pdo->query("SELECT pi.reference,pi.user_id,pi.amount,pi.status,pi.paid_at,t.reference transaction_reference,t.status transaction_status FROM payment_intents pi LEFT JOIN transactions t ON t.reference=CONCAT('PAY-',pi.reference) WHERE pi.status='paid' AND t.id IS NULL ORDER BY pi.paid_at DESC LIMIT {$limit}");return $q->fetchAll();}
}
