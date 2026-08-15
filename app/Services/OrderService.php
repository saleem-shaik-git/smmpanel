<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\Provider\SmmProviderInterface;
use RuntimeException;

final class OrderService
{
    public function __construct(private readonly SmmProviderInterface $provider) {}

    public function place(int $userId, int $serviceId, array $providerFields): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $u=$pdo->prepare('SELECT id,balance,status FROM users WHERE id=:id FOR UPDATE');
            $u->execute([':id'=>$userId]); $user=$u->fetch();
            if(!$user || $user['status']!=='active') throw new RuntimeException('User account is not active.');

            $s=$pdo->prepare('SELECT * FROM services WHERE id=:id AND status=1 LIMIT 1');
            $s->execute([':id'=>$serviceId]); $service=$s->fetch();
            if(!$service) throw new RuntimeException('Service is unavailable.');

            $quantity=isset($providerFields['quantity'])?(int)$providerFields['quantity']:null;
            if($quantity!==null && ($quantity<(int)$service['min_quantity'] || $quantity>(int)$service['max_quantity'])) {
                throw new RuntimeException('Quantity is outside the service limits.');
            }

            $customerCurrency=strtoupper((string)($service['customer_currency'] ?? env('CURRENCY','NGN')));
            $providerCurrency=strtoupper((string)($service['provider_currency'] ?? env('MARKETERUM_CURRENCY','USD')));
            $fxRate=(float)($service['fx_rate'] ?? 0);
            if($fxRate<=0) throw new RuntimeException('Service has no valid FX rate. Ask an administrator to resync pricing.');

            $charge=$quantity!==null ? PricingService::orderCharge((float)$service['selling_rate'],$quantity) : (float)$service['selling_rate'];
            $providerCost=$quantity!==null ? round((float)$service['provider_rate']*$fxRate*$quantity/1000,4) : round((float)$service['provider_rate']*$fxRate,4);
            $charge=round($charge,4);
            if((float)$user['balance']<$charge) throw new RuntimeException('Insufficient wallet balance.');

            $link=(string)($providerFields['link']??$providerFields['username']??$providerFields['media']??'');
            if($link==='') throw new RuntimeException('A valid target link, username, or media URL is required.');
            if(!filter_var($link,FILTER_VALIDATE_URL) && !preg_match('/^[@#A-Za-z0-9_.-]{2,}$/',$link)) throw new RuntimeException('The target link or username format is invalid.');

            $o=$pdo->prepare("INSERT INTO orders(user_id,service_id,provider,link,quantity,charge,provider_cost,provider_currency,customer_currency,fx_rate,profit,status) VALUES(:uid,:sid,'marketerum',:link,:quantity,:charge,:cost,:provider_currency,:customer_currency,:fx_rate,:profit,'pending')");
            $o->execute([
                ':uid'=>$userId,':sid'=>$serviceId,':link'=>$link,':quantity'=>$quantity??0,
                ':charge'=>$charge,':cost'=>$providerCost,':provider_currency'=>$providerCurrency,
                ':customer_currency'=>$customerCurrency,':fx_rate'=>$fxRate,':profit'=>round($charge-$providerCost,4)
            ]);
            $orderId=(int)$pdo->lastInsertId(); $pdo->commit();
            OrderWalletService::reserve($orderId,$userId,$charge);
        } catch(\Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }

        try {
            $response=$this->provider->addOrder(array_merge(['service'=>(string)$service['provider_service_id']],$providerFields));
            $providerOrderId=$response['order']??null;
            if($providerOrderId===null) throw new RuntimeException('Provider did not return an order ID.');
            $stmt=$pdo->prepare("UPDATE orders SET provider_order_id=:provider_order_id,provider_raw=:raw,status='processing' WHERE id=:id AND status='pending'");
            $stmt->execute([':provider_order_id'=>(string)$providerOrderId,':raw'=>json_encode($response,JSON_THROW_ON_ERROR),':id'=>$orderId]);
            if($stmt->rowCount()!==1) throw new RuntimeException('Order state changed before provider confirmation.');
            $pdo->prepare("UPDATE order_wallet_reservations SET status='captured' WHERE order_id=:id AND status='reserved'")->execute([':id'=>$orderId]);
            return $orderId;
        } catch(\Throwable $e) {
            try { OrderWalletService::release($orderId,$e->getMessage()); $pdo->prepare("UPDATE orders SET status='failed' WHERE id=:id AND status='pending'")->execute([':id'=>$orderId]); }
            catch(\Throwable $refundError) { throw new RuntimeException('Provider order failed and wallet compensation also failed; reconcile order #'.$orderId.' manually.',0,$refundError); }
            throw $e;
        }
    }
}
