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
        $pdo=Database::connection();$pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare('SELECT o.*,u.balance FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=:id FOR UPDATE');$stmt->execute([':id'=>$orderId]);$order=$stmt->fetch();
            if(!$order)throw new RuntimeException('Order not found.');
            $newStatus=$this->normalizeStatus((string)($providerStatus['status']??$order['status']));
            $start=isset($providerStatus['start_count'])?(int)$providerStatus['start_count']:$order['start_count'];
            $remains=isset($providerStatus['remains'])?(int)$providerStatus['remains']:$order['remains'];
            $oldStatus=$this->normalizeStatus((string)$order['status']);
            $refund=0.0;
            if($remains!==null && $remains>0 && in_array($newStatus,['partial','cancelled','canceled'],true) && !in_array($oldStatus,['partial','cancelled','canceled'],true)){
                $refund=min((float)$order['charge'],round(((float)$order['charge']*(float)$remains)/max(1,(int)$order['quantity']),4));
            }
            if($refund>0){
                $balance=(float)$order['balance'];$after=$balance+$refund;
                $pdo->prepare('UPDATE users SET balance=:balance WHERE id=:id')->execute([':balance'=>$after,':id'=>$order['user_id']]);
                $pdo->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,reference,description,status) VALUES(:uid,'refund',:amount,:before,:after,:ref,:desc,'completed')")->execute([':uid'=>$order['user_id'],':amount'=>$refund,':before'=>$balance,':after'=>$after,':ref'=>'REFUND-ORDER-'.$orderId.'-'.$newStatus,':desc'=>'Automatic refund for '.$newStatus.' order #'.$orderId]);
                $newProfit=(float)$order['charge']-$refund-(float)$order['provider_cost'];
                $pdo->prepare('UPDATE orders SET profit=:profit WHERE id=:id')->execute([':profit'=>$newProfit,':id'=>$orderId]);
            }
            $pdo->prepare('UPDATE orders SET status=:status,start_count=:start_count,remains=:remains,provider_raw=:raw WHERE id=:id')->execute([':status'=>$newStatus,':start_count'=>$start,':remains'=>$remains,':raw'=>json_encode($providerStatus,JSON_THROW_ON_ERROR),':id'=>$orderId]);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function cancel(int $orderId): array
    {
        $order=$this->getActionableOrder($orderId);$result=$this->provider->cancelOrder((string)$order['provider_order_id']);
        $cancelled=$this->providerActionSucceeded($result);
        if($cancelled)$this->reconcileProviderStatus($orderId,['status'=>'Canceled','remains'=>(int)$order['quantity']]);
        return $result;
    }

    public function refill(int $orderId): array
    {
        $order=$this->getActionableOrder($orderId);$result=$this->provider->refillOrder((string)$order['provider_order_id']);
        $refillId=$result['refill']??null;
        if($refillId!==null){$stmt=Database::connection()->prepare('UPDATE orders SET refill_id=:refill WHERE id=:id');$stmt->execute([':refill'=>(string)$refillId,':id'=>$orderId]);}
        return $result;
    }

    private function getActionableOrder(int $id):array{$stmt=Database::connection()->prepare("SELECT * FROM orders WHERE id=:id AND provider='marketerum' LIMIT 1");$stmt->execute([':id'=>$id]);$o=$stmt->fetch();if(!$o||empty($o['provider_order_id']))throw new RuntimeException('Provider order is unavailable.');return $o;}
    private function normalizeStatus(string $status):string{$s=strtolower(trim($status));return match($s){'in progress'=>'in_progress','complete'=>'completed','canceled'=>'cancelled',default=>$s};}
    private function providerActionSucceeded(array $result):bool{return isset($result['cancel']) ? !is_array($result['cancel']) : false;}
}
