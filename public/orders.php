<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Auth; use App\Database;
$userId=Auth::requireLogin();
$stmt=Database::connection()->prepare('SELECT o.*,s.name service FROM orders o JOIN services s ON s.id=o.service_id WHERE o.user_id=:id ORDER BY o.id DESC LIMIT 100');
$stmt->execute([':id'=>$userId]); $orders=$stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Orders | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="/dashboard.php">SMM Panel</a><a class="btn btn-primary btn-sm" href="/order.php">New Order</a></div></nav><main class="container py-4"><h2>My Orders</h2><div class="table-responsive"><table class="table bg-white align-middle"><thead><tr><th>#</th><th>Service</th><th>Provider</th><th>Quantity</th><th>Charge</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach($orders as $o): ?><tr><td><?= (int)$o['id']?></td><td><?=htmlspecialchars($o['service'])?></td><td><?=htmlspecialchars((string)($o['provider_order_id'] ?? 'Pending'))?></td><td><?=number_format((int)$o['quantity'])?></td><td>₦<?=number_format((float)$o['charge'],2)?></td><td><span class="badge text-bg-secondary"><?=htmlspecialchars($o['status'])?></span></td><td><?=htmlspecialchars($o['created_at'])?></td></tr><?php endforeach;?></tbody></table></div></main></body></html>
