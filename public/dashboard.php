<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Auth; use App\Database;
$userId = Auth::requireLogin();
$stmt = Database::connection()->prepare('SELECT name, email, balance FROM users WHERE id = :id');
$stmt->execute([':id'=>$userId]); $user=$stmt->fetch();
$orderStmt=Database::connection()->prepare('SELECT COUNT(*) FROM orders WHERE user_id=:id'); $orderStmt->execute([':id'=>$userId]); $orders=(int)$orderStmt->fetchColumn();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="/dashboard.php">SMM Panel</a><div><a class="btn btn-primary btn-sm me-2" href="/wallet.php">Fund Wallet</a><a class="btn btn-outline-light btn-sm" href="/logout.php">Logout</a></div></div></nav><main class="container py-4"><h2>Welcome, <?= htmlspecialchars($user['name']) ?></h2><div class="row g-3 mt-2"><div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Wallet balance</small><h3>₦<?= number_format((float)$user['balance'],2) ?></h3><a href="/wallet.php" class="btn btn-primary btn-sm">Add funds</a></div></div></div><div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Orders</small><h3><?= $orders ?></h3><a href="/orders.php" class="btn btn-outline-primary btn-sm">View orders</a></div></div></div><div class="col-md-4"><a href="/order.php" class="card border-0 shadow-sm text-decoration-none"><div class="card-body"><small class="text-muted">Quick action</small><h3>Place new order →</h3></div></a></div></div><div class="card border-0 shadow-sm mt-4"><div class="card-body"><h5>Account</h5><p class="mb-1"><?= htmlspecialchars($user['email']) ?></p><a href="/services.php">Browse services</a></div></div></main></body></html>
