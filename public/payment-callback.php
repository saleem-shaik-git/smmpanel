<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Auth;
Auth::requireLogin();
$reference=trim((string)($_GET['reference'] ?? $_GET['trxref'] ?? ''));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="alert alert-info"><h4>Payment received</h4><p>Your payment is being verified. Reference: <strong><?=htmlspecialchars($reference)?></strong></p><a href="/dashboard.php" class="btn btn-primary">Return to dashboard</a></div></main></body></html>
