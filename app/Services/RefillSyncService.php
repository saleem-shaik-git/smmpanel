<?php

declare(strict_types=1);
namespace App\Services;
use App\Database;
use App\Services\Provider\SmmProviderInterface;
final class RefillSyncService
{
    public function __construct(private readonly SmmProviderInterface $provider) {}
    public function sync(int $limit=100): array
    {
        $pdo=Database::connection();$limit=max(1,min(100,$limit));
        $stmt=$pdo->query("SELECT id,order_id,provider_refill_id,status FROM refill_events WHERE status NOT IN ('completed','rejected') ORDER BY id ASC LIMIT {$limit}");
        $events=$stmt->fetchAll();if(!$events)return ['checked'=>0,'updated'=>0,'failed'=>0];
        $remote=$this->provider->getMultipleRefillStatuses(array_column($events,'provider_refill_id'));$updated=0;$failed=0;
        foreach($events as $event){$rid=(string)$event['provider_refill_id'];$data=$remote[$rid]??null;if(!is_array($data)||isset($data['error'])){$failed++;continue;}$status=strtolower(trim((string)($data['status']??$event['status'])));$up=$pdo->prepare('UPDATE refill_events SET status=:status,provider_raw=:raw WHERE id=:id');$up->execute([':status'=>$status,':raw'=>json_encode($data,JSON_THROW_ON_ERROR),':id'=>$event['id']]);$pdo->prepare('UPDATE orders SET refill_status=:status WHERE id=:id')->execute([':status'=>$status,':id'=>$event['order_id']]);$updated++;}
        return ['checked'=>count($events),'updated'=>$updated,'failed'=>$failed];
    }
}
