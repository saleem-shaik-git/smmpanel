<?php

declare(strict_types=1);
namespace App\Services;
use App\Database;
use RuntimeException;
final class RefundService
{
    public static function refundOrder(int $orderId,float $amount,string $reason): bool
    {
        $amount=round($amount,4);if($amount<=0)return false;$pdo=Database::connection();$pdo->beginTransaction();
        try{$stmt=$pdo->prepare('SELECT o.*,u.balance FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=:id FOR UPDATE');$stmt->execute([':id'=>$orderId]);$o=$stmt->fetch();if(!$o)throw new RuntimeException('Order not found.');$ref='REFUND-ORDER-'.$orderId.'-'.$reason;$check=$pdo->prepare('SELECT id FROM refund_events WHERE reference=:ref OR (order_id=:order_id AND reason=:reason) LIMIT 1');$check->execute([':ref'=>$ref,':order_id'=>$orderId,':reason'=>$reason]);if($check->fetchColumn()!==false){$pdo->rollBack();return false;}$before=(float)$o['balance'];$after=$before+$amount;$pdo->prepare('UPDATE users SET balance=:balance WHERE id=:id')->execute([':balance'=>$after,':id'=>$o['user_id']]);$pdo->prepare("INSERT INTO refund_events(order_id,user_id,reason,amount,reference,status) VALUES(:order_id,:user_id,:reason,:amount,:reference,'completed')")->execute([':order_id'=>$orderId,':user_id'=>$o['user_id'],':reason'=>$reason,':amount'=>$amount,':reference'=>$ref]);$pdo->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,reference,description,status) VALUES(:user_id,'refund',:amount,:before,:after,:reference,:description,'completed')")->execute([':user_id'=>$o['user_id'],':amount'=>$amount,':before'=>$before,':after'=>$after,':reference'=>$ref,':description'=>'Refund for order #'.$orderId.' ('.$reason.')']);$newProfit=round((float)$o['charge']-$amount-(float)$o['provider_cost'],4);$pdo->prepare('UPDATE orders SET profit=:profit WHERE id=:id')->execute([':profit'=>$newProfit,':id'=>$orderId]);$pdo->prepare('INSERT INTO order_audit_logs(order_id,actor_type,action,old_status,new_status,metadata) VALUES(:order_id,\'system\',\'refund\',:old_status,:new_status,:metadata)')->execute([':order_id'=>$orderId,':old_status'=>$o['status'],':new_status'=>$o['status'],':metadata'=>json_encode(['amount'=>$amount,'reason'=>$reason,'reference'=>$ref],JSON_THROW_ON_ERROR)]);$pdo->commit();return true;}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
