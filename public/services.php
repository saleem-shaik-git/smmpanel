<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Auth; use App\Database;
Auth::requireLogin();
$stmt=Database::connection()->query('SELECT s.id,s.name,s.provider_type,s.selling_rate,s.min_quantity,s.max_quantity,s.refill,s.cancel,c.name category FROM services s LEFT JOIN categories c ON c.id=s.category_id WHERE s.status=1 ORDER BY c.name,s.id');
$services=$stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Services | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="/dashboard.php">SMM Panel</a></div></nav><main class="container py-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2>Services</h2><a class="btn btn-primary" href="/order.php">New Order</a></div><div class="table-responsive"><table class="table table-hover bg-white align-middle"><thead><tr><th>ID</th><th>Category</th><th>Service</th><th>Type</th><th>Rate / 1K</th><th>Limits</th><th></th></tr></thead><tbody><?php foreach($services as $s): ?><tr><td><?= (int)$s['id'] ?></td><td><?= htmlspecialchars($s['category'] ?? 'Uncategorized') ?></td><td><?= htmlspecialchars($s['name']) ?></td><td><?= htmlspecialchars($s['provider_type']) ?></td><td>₦<?= number_format((float)$s['selling_rate'],4) ?></td><td><?= (int)$s['min_quantity'] ?>–<?= (int)$s['max_quantity'] ?></td><td><a class="btn btn-sm btn-outline-primary" href="/order.php?service=<?= (int)$s['id'] ?>">Order</a></td></tr><?php endforeach; ?></tbody></table></div></main></body></html>
