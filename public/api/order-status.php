<?php

declare(strict_types=1);
require dirname(__DIR__,2) . '/config/bootstrap.php';
use App\Auth; use App\Database; use App\Services\Provider\ProviderFactory;
$userId=Auth::requireLogin(); header('Content-Type: application/json; charset=utf-8');
$orderId=(int)($_GET['order'] ?? 0);
$stmt=Database::connection()->prepare('SELECT o.id,o.provider_order_id,o.status,o.charge,o.start_count,o.remains,o.refill_id,s.name service FROM orders o JOIN services s ON s.id=o.service_id WHERE o.id=:id AND o.user_id=:user LIMIT 1');
$stmt->execute([':id'=>$orderId,':user'=>$userId]); $order=$stmt->fetch();
if(!$order){http_response_code(404); echo json_encode(['error'=>'Order not found']); exit;}
if($order['provider_order_id'] && in_array(strtolower($order['status']),['pending','processing','in progress','in_progress'],true)){
    try{$remote=ProviderFactory::marketerum()->getOrderStatus((string)$order['provider_order_id']); if(isset($remote['status'])){$up=Database::connection()->prepare('UPDATE orders SET status=:status,charge=:charge,start_count=:start_count,remains=:remains,provider_raw=:raw WHERE id=:id');$up->execute([':status'=>strtolower((string)$remote['status']),':charge'=>(float)($remote['charge']??$order['charge']),':start_count'=>isset($remote['start_count'])?(int)$remote['start_count']:null,':remains'=>isset($remote['remains'])?(int)$remote['remains']:null,':raw'=>json_encode($remote,JSON_THROW_ON_ERROR),':id'=>$orderId]);$order=array_merge($order,['status'=>strtolower((string)$remote['status']),'charge'=>(float)($remote['charge']??$order['charge']),'start_count'=>$remote['start_count']??null,'remains'=>$remote['remains']??null]);}}catch(Throwable $e){}
}
echo json_encode(['data'=>$order],JSON_THROW_ON_ERROR);
