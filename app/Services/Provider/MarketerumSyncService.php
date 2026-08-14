<?php

declare(strict_types=1);

namespace App\Services\Provider;

use App\Database;
use App\Services\PricingService;
use Throwable;

final class MarketerumSyncService
{
    public function __construct(private readonly MarketerumProvider $provider) {}

    public function sync(): array
    {
        $services=$this->provider->getServices();
        $pdo=Database::connection();
        $inserted=0;$updated=0;$disabled=0;
        $seen=[];
        $pdo->beginTransaction();
        try {
            $categoryCache=[];
            foreach($services as $service){
                if(!is_array($service) || !isset($service['service'])) continue;
                $providerId=(string)$service['service'];$seen[$providerId]=true;
                $categoryName=trim((string)($service['category']??'Uncategorized')) ?: 'Uncategorized';
                if(!isset($categoryCache[$categoryName])){
                    $slug=preg_replace('/[^a-z0-9]+/','-',strtolower($categoryName));$slug=trim((string)$slug,'-') ?: 'uncategorized';
                    $stmt=$pdo->prepare('SELECT id FROM categories WHERE slug=:slug LIMIT 1');$stmt->execute([':slug'=>$slug]);$categoryId=$stmt->fetchColumn();
                    if($categoryId===false){$ins=$pdo->prepare('INSERT INTO categories(name,slug) VALUES(:name,:slug)');$ins->execute([':name'=>$categoryName,':slug'=>$slug]);$categoryId=$pdo->lastInsertId();}
                    $categoryCache[$categoryName]=(int)$categoryId;
                }
                $providerRate=(float)($service['rate']??0);$defaultSelling=PricingService::sellingRate($providerRate);
                $existing=$pdo->prepare('SELECT id,selling_rate,markup_percent FROM services WHERE provider="marketerum" AND provider_service_id=:pid LIMIT 1');$existing->execute([':pid'=>$providerId]);$row=$existing->fetch();
                if($row){
                    $markup=$row['markup_percent'];
                    $selling=$markup!==null ? PricingService::sellingRate($providerRate,(float)$markup) : (float)$row['selling_rate'];
                    $up=$pdo->prepare('UPDATE services SET category_id=:category,name=:name,provider_type=:type,provider_rate=:rate,selling_rate=:selling,min_quantity=:min,max_quantity=:max,refill=:refill,cancel=:cancel,status=1,provider_raw=:raw WHERE id=:id');
                    $up->execute([':category'=>$categoryCache[$categoryName],':name'=>(string)($service['name']??'Service'),':type'=>(string)($service['type']??'Default'),':rate'=>$providerRate,':selling'=>$selling,':min'=>(int)($service['min']??1),':max'=>(int)($service['max']??1),':refill'=>(int)!empty($service['refill']),':cancel'=>(int)!empty($service['cancel']),':raw'=>json_encode($service,JSON_THROW_ON_ERROR),':id'=>$row['id']]);$updated++;
                } else {
                    $ins=$pdo->prepare('INSERT INTO services(category_id,provider,provider_service_id,name,provider_type,provider_rate,selling_rate,min_quantity,max_quantity,refill,cancel,status,provider_raw) VALUES(:category,"marketerum",:pid,:name,:type,:rate,:selling,:min,:max,:refill,:cancel,1,:raw)');
                    $ins->execute([':category'=>$categoryCache[$categoryName],':pid'=>$providerId,':name'=>(string)($service['name']??'Service'),':type'=>(string)($service['type']??'Default'),':rate'=>$providerRate,':selling'=>$defaultSelling,':min'=>(int)($service['min']??1),':max'=>(int)($service['max']??1),':refill'=>(int)!empty($service['refill']),':cancel'=>(int)!empty($service['cancel']),':raw'=>json_encode($service,JSON_THROW_ON_ERROR)]);$inserted++;
                }
            }
            if($seen!==[]){$ids=implode(',',array_fill(0,count($seen),'?'));$params=array_keys($seen);$stmt=$pdo->prepare("UPDATE services SET status=0 WHERE provider='marketerum' AND provider_service_id NOT IN ($ids)");$stmt->execute($params);$disabled=$stmt->rowCount();}
            $pdo->commit();
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['provider_services'=>count($seen),'inserted'=>$inserted,'updated'=>$updated,'disabled'=>$disabled];
    }
}
