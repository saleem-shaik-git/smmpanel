<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/config/bootstrap.php';

use App\Auth;
use App\Database;
use App\Services\AdminService;
use App\Services\MarketerumServiceSync;
use App\Services\Provider\MarketerumProvider;

AdminService::requireAdmin();
$pdo=Database::connection(); $message=null; $error=null; $sync=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
    Auth::verifyCsrf($_POST['_csrf']??null);
    try {
        $action=$_POST['action']??'';
        if($action==='sync'){
            $provider=new MarketerumProvider((string)env('MARKETERUM_API_URL','https://marketerum.com/api/v2'),(string)env('MARKETERUM_API_KEY',''),(int)env('MARKETERUM_TIMEOUT',30));
            $sync=(new MarketerumServiceSync($provider))->sync();
            $message=sprintf('Sync complete: %d created, %d updated, %d disabled, %d categories.', $sync['created'],$sync['updated'],$sync['disabled'],$sync['categories']);
        } elseif($action==='pricing') {
            $id=(int)$_POST['id']; $rate=(float)$_POST['selling_rate']; $markup=($_POST['markup_percent']??'')===''?null:(float)$_POST['markup_percent'];
            AdminService::updateServicePricing($id,$rate,$markup); $message='Pricing updated.';
        } elseif($action==='status') {
            $id=(int)$_POST['id']; $active=(int)($_POST['status']??0)===1;
            AdminService::setServiceStatus($id,$active); $message=$active?'Service activated.':'Service disabled.';
        }
    } catch(Throwable $e) { $error=$e->getMessage(); }
}

$services=$pdo->query('SELECT s.*,c.name category FROM services s LEFT JOIN categories c ON c.id=s.category_id ORDER BY c.name,s.id')->fetchAll();
$active=(int)$pdo->query('SELECT COUNT(*) FROM services WHERE status=1')->fetchColumn();
$disabled=(int)$pdo->query('SELECT COUNT(*) FROM services WHERE status=0')->fetchColumn();
$categories=(int)$pdo->query('SELECT COUNT(*) FROM categories WHERE status=1')->fetchColumn();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Services | Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="/admin/">SMM Admin</a><a class="btn btn-outline-light btn-sm" href="/services.php">Customer Catalogue</a></div></nav>
<main class="container-fluid py-4">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h2 class="mb-1">Service Management</h2><div class="text-muted">Marketerum catalogue, pricing and availability</div></div><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Auth::csrfToken())?>"><input type="hidden" name="action" value="sync"><button class="btn btn-primary">Sync Marketerum Services</button></form></div>
<?php if($message):?><div class="alert alert-success"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><strong>Operation failed:</strong> <?=htmlspecialchars($error)?></div><?php endif;?>
<div class="row g-3 mb-4"><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Active services</div><div class="fs-3 fw-bold"><?=$active?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Disabled services</div><div class="fs-3 fw-bold"><?=$disabled?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="text-muted">Active categories</div><div class="fs-3 fw-bold"><?=$categories?></div></div></div></div></div>
<div class="card shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>ID</th><th>Provider ID</th><th>Category</th><th>Service</th><th>Provider Rate / 1K</th><th>Selling / 1K</th><th>Markup %</th><th>Limits</th><th>Refill</th><th>Cancel</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($services as $s):?>
<tr>
<td><?= (int)$s['id']?></td><td><code><?=htmlspecialchars($s['provider_service_id'])?></code></td><td><?=htmlspecialchars($s['category']??'Uncategorized')?></td><td><?=htmlspecialchars($s['name'])?><div class="small text-muted"><?=htmlspecialchars($s['provider_type'])?></div></td><td><?=number_format((float)$s['provider_rate'],6)?></td>
<td><form class="row g-1" method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Auth::csrfToken())?>"><input type="hidden" name="action" value="pricing"><input type="hidden" name="id" value="<?= (int)$s['id']?>"><div class="col-auto"><input class="form-control form-control-sm" name="selling_rate" type="number" step="0.000001" min="0" value="<?=htmlspecialchars((string)$s['selling_rate'])?>"></div><div class="col-auto"><button class="btn btn-sm btn-outline-primary">Save</button></div></form></td>
<td><form class="d-flex gap-1" method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Auth::csrfToken())?>"><input type="hidden" name="action" value="pricing"><input type="hidden" name="id" value="<?= (int)$s['id']?>"><input class="form-control form-control-sm" style="width:85px" name="markup_percent" type="number" step="0.01" min="0" value="<?=htmlspecialchars((string)($s['markup_percent']??''))?>"><input type="hidden" name="selling_rate" value="<?=htmlspecialchars((string)$s['selling_rate'])?>"><button class="btn btn-sm btn-outline-secondary">Apply</button></form></td>
<td><?= (int)$s['min_quantity']?>–<?= (int)$s['max_quantity']?></td><td><?=((int)$s['refill']===1)?'Yes':'No'?></td><td><?=((int)$s['cancel']===1)?'Yes':'No'?></td>
<td><?php if((int)$s['status']===1):?><span class="badge text-bg-success">Active</span><?php else:?><span class="badge text-bg-secondary">Disabled</span><?php endif;?></td>
<td><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Auth::csrfToken())?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$s['id']?>"><input type="hidden" name="status" value="<?=((int)$s['status']===1)?0:1?>"><button class="btn btn-sm <?=((int)$s['status']===1)?'btn-outline-danger':'btn-outline-success'?>"><?=((int)$s['status']===1)?'Disable':'Activate'?></button></form></td>
</tr>
<?php endforeach;?>
</tbody></table></div></div></div>
</main></body></html>
