<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/config/bootstrap.php';
use App\Database; use App\Services\AdminService;
AdminService::requireAdmin();
$stmt=Database::connection()->query('SELECT o.id,o.status,o.quantity,o.charge,o.provider_order_id,o.profit,o.created_at,u.email,s.name service FROM orders o JOIN users u ON u.id=o.user_id JOIN services s ON s.id=o.service_id ORDER BY o.id DESC LIMIT 200');$orders=$stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Orders | Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="/admin/">SMM Admin</a></div></nav><main class="container-fluid py-4"><h2>Orders</h2><div class="table-responsive"><table class="table bg-white align-middle"><thead><tr><th>#</th><th>Customer</th><th>Service</th><th>Provider ID</th><th>Qty</th><th>Charge</th><th>Profit</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach($orders as $o):?><tr><td><?= (int)$o['id']?></td><td><?=htmlspecialchars($o['email'])?></td><td><?=htmlspecialchars($o['service'])?></td><td><?=htmlspecialchars((string)($o['provider_order_id']??'-'))?></td><td><?=number_format((int)$o['quantity'])?></td><td>₦<?=number_format((float)$o['charge'],2)?></td><td>₦<?=number_format((float)$o['profit'],2)?></td><td><span class="badge text-bg-secondary"><?=htmlspecialchars($o['status'])?></span></td><td><?=htmlspecialchars($o['created_at'])?></td></tr><?php endforeach;?></tbody></table></div></main></body></html>
