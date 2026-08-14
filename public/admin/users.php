<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/config/bootstrap.php';
use App\Database; use App\Services\AdminService;
AdminService::requireAdmin();
$users=Database::connection()->query("SELECT u.id,u.name,u.email,u.balance,u.status,u.created_at,COUNT(o.id) orders FROM users u LEFT JOIN orders o ON o.user_id=u.id WHERE u.role='user' GROUP BY u.id ORDER BY u.id DESC LIMIT 200")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Customers | Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="/admin/">SMM Admin</a></div></nav><main class="container-fluid py-4"><h2>Customers</h2><div class="table-responsive"><table class="table bg-white"><thead><tr><th>#</th><th>Name</th><th>Email</th><th>Balance</th><th>Orders</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach($users as $u):?><tr><td><?= (int)$u['id']?></td><td><?=htmlspecialchars($u['name'])?></td><td><?=htmlspecialchars($u['email'])?></td><td>₦<?=number_format((float)$u['balance'],2)?></td><td><?= (int)$u['orders']?></td><td><?=htmlspecialchars($u['status'])?></td><td><?=htmlspecialchars($u['created_at'])?></td></tr><?php endforeach;?></tbody></table></div></main></body></html>
