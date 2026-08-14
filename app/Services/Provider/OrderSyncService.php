<?php

declare(strict_types=1);
namespace App\Services\Provider;
use App\Database;
use Throwable;
final class OrderSyncService
{
    public function __construct(private readonly MarketerumProvider $provider) {}
    public function sync(int $limit=100): array
    {
        $pdo=Database::connection();
        $limit=max(1,min(100,$limit));
        $stmt=$pdo->query("SELECT id,provider_order_id,status FROM orders WHERE provider='marketerum' AND provider_order_id IS NOT NULL AND status NOT IN ('completed','complete','cancelled','canceled','failed') ORDER BY id ASC LIMIT {$limit}");
        $orders=$stmt->fetchAll(); if(!$orders)return ['checked'=>0,'updated'=>0,'failed'=>0];
        $ids=array_column($orders,'provider_order_id');$remote=$this->provider->getMultipleOrderStatuses($ids);$updated=0;$failed=0;
        foreach($orders as $order){
            $pid=(string)$order['provider_order_id'];$data=$remote[$pid]??null;
            if(!is_array($data) || isset($data['error'])){$failed++;continue;}
            $status=strtolower(trim((string)($data['status']??$order['status'])));
            $status=str_replace(' ','_', $status);
            $allowed=['pending','in_progress','processing','completed','complete','partial','canceled','cancelled','failed','refunded'];
            if(!in_array($status,$allowed,true))$status='processing';
            $up=$pdo->prepare('UPDATE orders SET status=:status,start_count=:start_count,remains=:remains,provider_raw=:raw WHERE id=:id');
            $up->execute([':status'=>$status,':start_count'=>isset($data['start_count'])?(int)$data['start_count']:null,':remains'=>isset($data['remains'])?(int)$data['remains']:null,':raw'=>json_encode($data,JSON_THROW_ON_ERROR),':id'=>(int)$order['id']]);$updated++;
        }
        return ['checked'=>count($orders),'updated'=>$updated,'failed'=>$failed];
    }
}
