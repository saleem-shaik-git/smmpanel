<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/config/bootstrap.php';
use App\Auth; use App\Services\Provider\ProviderFactory; use App\Services\Provider\MarketerumSyncService; use App\Services\Provider\OrderSyncService; use App\Services\Database;
App\Services\AdminService::requireAdmin();
$message=null;$error=null;$balance=null;$sync=null;$orderSync=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 Auth::verifyCsrf($_POST['_csrf']??null);$action=$_POST['action']??'';
 try{
  $provider=ProviderFactory::marketerum();
  if($action==='balance')$balance=$provider->getBalance();
  elseif($action==='sync_services')$sync=(new MarketerumSyncService($provider))->sync();
  elseif($action==='sync_orders')$orderSync=(new OrderSyncService($provider))->sync(100);
  $message='Operation completed.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Marketerum Provider | Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="/admin/">SMM Admin</a></div></nav><main class="container py-4"><h2>Marketerum Provider</h2><?php if($message):?><div class="alert alert-success"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?><div class="row g-3"><div class="col-md-4"><div class="card"><div class="card-body"><h5>Provider Balance</h5><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Auth::csrfToken())?>"><input type="hidden" name="action" value="balance"><button class="btn btn-primary">Check Balance</button></form><?php if($balance):?><hr><strong><?=htmlspecialchars((string)($balance['balance']??'-'))?> <?=htmlspecialchars((string)($balance['currency']??''))?></strong><?php endif;?></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><h5>Service Catalog</h5><p class="text-muted">Import new services, update provider rates and disable removed services.</p><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Auth::csrfToken())?>"><input type="hidden" name="action" value="sync_services"><button class="btn btn-primary">Sync Services</button></form><?php if($sync):?><hr><small><?=htmlspecialchars(json_encode($sync))?></small><?php endif;?></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><h5>Order Status</h5><p class="text-muted">Refresh up to 100 active provider orders at once.</p><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Auth::csrfToken())?>"><input type="hidden" name="action" value="sync_orders"><button class="btn btn-primary">Sync Orders</button></form><?php if($orderSync):?><hr><small><?=htmlspecialchars(json_encode($orderSync))?></small><?php endif;?></div></div></div></div></main></body></html>
