<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Auth; use App\Database;
$userId=Auth::requireLogin();
$stmt=Database::connection()->prepare('SELECT type,amount,balance_before,balance_after,reference,description,status,created_at FROM transactions WHERE user_id=:id ORDER BY id DESC LIMIT 100');$stmt->execute([':id'=>$userId]);$transactions=$stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Transactions | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="/dashboard.php">SMM Panel</a></div></nav><main class="container py-4"><h2>Transactions</h2><div class="table-responsive"><table class="table bg-white"><thead><tr><th>Type</th><th>Amount</th><th>Balance After</th><th>Reference</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach($transactions as $t): ?><tr><td><?=htmlspecialchars($t['type'])?></td><td>₦<?=number_format((float)$t['amount'],2)?></td><td>₦<?=number_format((float)$t['balance_after'],2)?></td><td><?=htmlspecialchars($t['reference'])?></td><td><?=htmlspecialchars($t['status'])?></td><td><?=htmlspecialchars($t['created_at'])?></td></tr><?php endforeach;?></tbody></table></div></main></body></html>
