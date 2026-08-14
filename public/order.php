<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Auth; use App\Database; use App\Services\Provider\MarketerumServiceType; use App\Services\OrderService; use App\Services\Provider\ProviderFactory;
$userId=Auth::requireLogin(); $pdo=Database::connection(); $error=null; $success=null;
$services=$pdo->query('SELECT * FROM services WHERE status=1 ORDER BY name')->fetchAll();
$selectedId=(int)($_GET['service'] ?? $_POST['service_id'] ?? 0); $selected=null;
foreach($services as $s){if((int)$s['id']===$selectedId){$selected=$s;break;}}
if($_SERVER['REQUEST_METHOD']==='POST'){
    Auth::verifyCsrf($_POST['_csrf'] ?? null);
    if(!$selected){$error='Please select a service.';} else {
        $fields=[]; foreach(['link','quantity','runs','interval','keywords','comments','usernames','hashtags','hashtag','username','media','min','max','posts','old_posts','delay','expiry','answer_number','groups'] as $key){if(isset($_POST[$key]) && trim((string)$_POST[$key])!==''){$fields[$key]=trim((string)$_POST[$key]);}}
        if(isset($fields['quantity'])){$fields['quantity']=(int)$fields['quantity'];}
        try {$orderId=(new OrderService(ProviderFactory::marketerum()))->place($userId,$selectedId,$fields); $success='Order #'.$orderId.' was submitted successfully.';} catch(Throwable $e){$error=$e->getMessage();}
    }
}
$type=$selected ? strtolower(trim((string)$selected['provider_type'])) : '';
$fields=$selected ? MarketerumServiceType::fields($selected['provider_type']) : [];
$labels=['link'=>'Link / URL','quantity'=>'Quantity','comments'=>'Comments (one per line)','keywords'=>'Keywords (one per line)','usernames'=>'Usernames (one per line)','hashtags'=>'Hashtags (one per line)','username'=>'Username','media'=>'Media URL','hashtag'=>'Hashtag','groups'=>'Groups (one per line)','answer_number'=>'Poll answer number'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>New Order | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="/dashboard.php">SMM Panel</a></div></nav><main class="container py-4"><div class="row justify-content-center"><div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="card-body p-4"><h2>New Order</h2><?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Auth::csrfToken())?>"><div class="mb-3"><label class="form-label">Service</label><select class="form-select" name="service_id" onchange="location.href='?service='+this.value" required><option value="">Select service</option><?php foreach($services as $s): ?><option value="<?= (int)$s['id']?>" <?=$selectedId===(int)$s['id']?'selected':''?>><?=htmlspecialchars(($s['category'] ?? '').' — '.$s['name'].' ('.$s['provider_type'].')')?></option><?php endforeach;?></select></div><?php if($selected): ?><div class="alert alert-light border"><strong><?=htmlspecialchars($selected['name'])?></strong><br><small>Minimum <?= (int)$selected['min_quantity']?> · Maximum <?= (int)$selected['max_quantity']?></small></div><?php foreach($fields as $field): $isArea=in_array($field,['comments','keywords','usernames','hashtags','groups'],true); ?><div class="mb-3"><label class="form-label"><?=htmlspecialchars($labels[$field] ?? ucfirst(str_replace('_',' ',$field)))?></label><?php if($isArea): ?><textarea class="form-control" name="<?=htmlspecialchars($field)?>" rows="5" required></textarea><?php else: ?><input class="form-control" name="<?=htmlspecialchars($field)?>" type="<?= $field==='quantity'?'number':'text' ?>" <?= $field==='quantity'?'min="'.(int)$selected['min_quantity'].'" max="'.(int)$selected['max_quantity'].'"':'' ?> required><?php endif;?></div><?php endforeach; ?><button class="btn btn-primary w-100">Place Order</button><?php endif;?></form></div></div></div></div></main></body></html>
