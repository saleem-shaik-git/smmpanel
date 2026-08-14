<?php

declare(strict_types=1);
namespace App\Services;
use App\Database;
use App\Services\Provider\SmmProviderInterface;
use RuntimeException;
final class OrderService
{
    public function __construct(private readonly SmmProviderInterface $provider) {}
    public function place(int $userId,int $serviceId,array $providerFields):int
    {
        $pdo=Database::connection();$pdo->beginTransaction();
        try{$u=$pdo->prepare('SELECT id,balance,status FROM users WHERE id=:id FOR UPDATE');$u->execute([':id'=>$userId]);$user=$u->fetch();if(!$user||$user['status']!=='active')throw new RuntimeException('User account is not active.');$s=$pdo->prepare('SELECT * FROM services WHERE id=:id AND status=1 LIMIT 1');$s->execute([':id'=>$serviceId]);$service=$s->fetch();if(!$service)throw new RuntimeException('Service is unavailable.');$quantity=isset($providerFields['quantity'])?(int)$providerFields['quantity']:null;if($quantity!==null&&($quantity<(int)$service['min_quantity']||$quantity>(int)$service['max_quantity']))throw new RuntimeException('Quantity is outside the service limits.');$charge=$quantity!==null?((float)$service['selling_rate']*$quantity/1000):(float)$service['selling_rate'];$providerCost=$quantity!==null?((float)$service['provider_rate']*$quantity/1000):(float)$service['provider_rate'];if((float)$user['balance']<$charge)throw new RuntimeException('Insufficient wallet balance.');$link=(string)($providerFields['link']??$providerFields['username']??$providerFields['media']??'');$o=$pdo->prepare("INSERT INTO orders(user_id,service_id,provider,link,quantity,charge,provider_cost,profit,status) VALUES(:uid,:sid,'marketerum',:link,:quantity,:charge,:cost,:profit,'pending')");$o->execute([':uid'=>$userId,':sid'=>$serviceId,':link'=>$link,':quantity'=>$quantity??0,':charge'=>$charge,':cost'=>$providerCost,':profit'=>$charge-$providerCost]);$orderId=(int)$pdo->lastInsertId();$pdo->commit();\App\Services\OrderWalletService::reserve($orderId,$userId,$charge);}
        catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        try{$response=$this->provider->addOrder(array_merge(['service'=>(string)$service['provider_service_id']],$providerFields));$providerOrderId=$response['order']??null;if($providerOrderId===null)throw new RuntimeException('Provider did not return an order ID.');$stmt=$pdo->prepare("UPDATE orders SET provider_order_id=:provider_order_id,provider_raw=:raw,status='processing' WHERE id=:id AND status='pending'");$stmt->execute([':provider_order_id'=>(string)$providerOrderId,':raw'=>json_encode($response,JSON_THROW_ON_ERROR),':id'=>$orderId]);if($stmt->rowCount()!==1)throw new RuntimeException('Order state changed before provider confirmation.');$pdo->prepare("UPDATE order_wallet_reservations SET status='captured' WHERE order_id=:id AND status='reserved'")->execute([':id'=>$orderId]);return $orderId;}catch(\Throwable $e){try{OrderWalletService::release($orderId,$e->getMessage());$pdo->prepare("UPDATE orders SET status='failed' WHERE id=:id AND status='pending'")->execute([':id'=>$orderId]);}catch(\Throwable $refundError){throw new RuntimeException('Provider order failed and wallet compensation also failed; reconcile order #'.$orderId.' manually.',0,$refundError);}throw $e;}
    }
}
