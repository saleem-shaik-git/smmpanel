<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Database;
use App\Services\AdminService;
use App\Services\FinancialReconciliationService;

AdminService::requireAdmin();

$pdo = Database::connection();
$days = max(1, min(90, (int) ($_GET['days'] ?? 1)));
$summary = FinancialReconciliationService::summary($days);
$exceptions = FinancialReconciliationService::exceptions(50);

$failedJobs = (int) $pdo->query(
    "SELECT COUNT(*) FROM job_runs
     WHERE status = 'failed'
       AND started_at >= NOW() - INTERVAL 24 HOUR"
)->fetchColumn();

$runningJobs = (int) $pdo->query(
    "SELECT COUNT(*) FROM job_runs WHERE status = 'running'"
)->fetchColumn();

$activeOrders = (int) $pdo->query(
    "SELECT COUNT(*) FROM orders
     WHERE status NOT IN ('completed','cancelled','canceled','failed')"
)->fetchColumn();

$retryCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM order_sync_retries"
)->fetchColumn();

$dueRetries = (int) $pdo->query(
    "SELECT COUNT(*) FROM order_sync_retries
     WHERE next_attempt_at IS NULL OR next_attempt_at <= NOW()"
)->fetchColumn();

$refunds24h = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount), 0)
     FROM refund_events
     WHERE status = 'completed'
       AND created_at >= NOW() - INTERVAL 24 HOUR"
)->fetchColumn();

$profit24h = (float) $pdo->query(
    "SELECT COALESCE(SUM(profit), 0)
     FROM orders
     WHERE created_at >= NOW() - INTERVAL 24 HOUR"
)->fetchColumn();

$recentRetries = $pdo->query(
    "SELECT r.*, o.provider_order_id, o.status AS order_status
     FROM order_sync_retries r
     JOIN orders o ON o.id = r.order_id
     ORDER BY r.next_attempt_at ASC, r.last_attempt_at DESC
     LIMIT 50"
)->fetchAll();

$recentOrders = $pdo->query(
    "SELECT o.id, o.provider_order_id, o.status, o.remains, o.charge,
            o.updated_at, o.user_id
     FROM orders o
     WHERE o.provider = 'marketerum'
     ORDER BY o.updated_at DESC
     LIMIT 30"
)->fetchAll();

$jobs = $pdo->query(
    'SELECT * FROM job_runs ORDER BY started_at DESC LIMIT 30'
)->fetchAll();

$audit = $pdo->query(
    "SELECT a.order_id, a.action, a.old_status, a.new_status,
            a.metadata, a.created_at
     FROM order_audit_logs a
     ORDER BY a.id DESC
     LIMIT 30"
)->fetchAll();

$balanced = abs((float) $summary['payment_transaction_difference']) < 0.0001
    && abs((float) $summary['ledger_transaction_difference']) < 0.0001;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="60">
    <title>Operations | SMM Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/">SMM Operations</a>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="/admin/financials.php">Financials</a>
            <a class="btn btn-outline-light btn-sm" href="/admin/orders.php">Orders</a>
            <a class="btn btn-outline-light btn-sm" href="/admin/">Dashboard</a>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Production Operations</h2>
            <div class="text-muted small">Auto-refreshes every 60 seconds</div>
        </div>
        <form method="get">
            <select name="days" class="form-select" onchange="this.form.submit()">
                <option value="1" <?= $days === 1 ? 'selected' : '' ?>>24 hours</option>
                <option value="7" <?= $days === 7 ? 'selected' : '' ?>>7 days</option>
                <option value="30" <?= $days === 30 ? 'selected' : '' ?>>30 days</option>
                <option value="90" <?= $days === 90 ? 'selected' : '' ?>>90 days</option>
            </select>
        </form>
    </div>

    <div class="alert alert-<?= $balanced ? 'success' : 'danger' ?>">
        <?= $balanced
            ? '✓ Financial reconciliation is balanced for the selected period.'
            : '⚠ Financial reconciliation differences detected. Review Financials and exceptions.' ?>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['Active orders', number_format($activeOrders), 'primary'],
            ['Sync retries', number_format($retryCount), $retryCount > 0 ? 'warning' : 'success'],
            ['Retries due now', number_format($dueRetries), $dueRetries > 0 ? 'danger' : 'success'],
            ['Failed jobs / 24h', number_format($failedJobs), $failedJobs > 0 ? 'danger' : 'success'],
            ['Running jobs', number_format($runningJobs), $runningJobs > 0 ? 'info' : 'secondary'],
            ['Refunds / 24h', '₦' . number_format($refunds24h, 2), 'success'],
            ['Order profit / 24h', '₦' . number_format($profit24h, 2), 'primary'],
            ['Missing wallet transactions', number_format(count($exceptions)), count($exceptions) ? 'danger' : 'success'],
        ];
        foreach ($cards as $card):
        ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?= htmlspecialchars($card[0]) ?></div>
                        <div class="fs-3 fw-semibold text-<?= $card[2] ?>"><?= htmlspecialchars($card[1]) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between">
                    <strong>Order synchronization retries</strong>
                    <span class="badge text-bg-<?= $dueRetries ? 'danger' : 'success' ?>">
                        <?= $dueRetries ? $dueRetries . ' due' : 'Healthy' ?>
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Order</th><th>Status</th><th>Attempts</th><th>Next retry</th><th>Error</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentRetries as $retry): ?>
                            <tr>
                                <td>#<?= (int) $retry['order_id'] ?><br><code><?= htmlspecialchars((string) $retry['provider_order_id']) ?></code></td>
                                <td><?= htmlspecialchars((string) $retry['order_status']) ?></td>
                                <td><?= number_format((int) $retry['attempts']) ?></td>
                                <td><?= htmlspecialchars((string) ($retry['next_attempt_at'] ?? 'now')) ?></td>
                                <td class="text-danger small"><?= htmlspecialchars((string) $retry['last_error']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$recentRetries): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No retry backlog.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Recent provider order state</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Order</th><th>User</th><th>Status</th><th>Remains</th><th>Charge</th><th>Updated</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?= (int) $order['id'] ?><br><code><?= htmlspecialchars((string) $order['provider_order_id']) ?></code></td>
                                <td><?= (int) $order['user_id'] ?></td>
                                <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string) $order['status']) ?></span></td>
                                <td><?= $order['remains'] === null ? '-' : number_format((int) $order['remains']) ?></td>
                                <td>₦<?= number_format((float) $order['charge'], 2) ?></td>
                                <td><?= htmlspecialchars((string) $order['updated_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Financial exceptions</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Reference</th><th>User</th><th>Amount</th><th>Paid</th></tr></thead>
                        <tbody>
                        <?php foreach ($exceptions as $exception): ?>
                            <tr>
                                <td><code><?= htmlspecialchars((string) $exception['reference']) ?></code></td>
                                <td><?= (int) $exception['user_id'] ?></td>
                                <td>₦<?= number_format((float) $exception['amount'], 2) ?></td>
                                <td><?= htmlspecialchars((string) $exception['paid_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$exceptions): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No payment exceptions.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><strong>Recent synchronization audit</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Order</th><th>Transition</th><th>Time</th></tr></thead>
                        <tbody>
                        <?php foreach ($audit as $event): ?>
                            <tr>
                                <td>#<?= (int) $event['order_id'] ?></td>
                                <td>
                                    <span class="small"><?= htmlspecialchars((string) $event['old_status']) ?></span>
                                    →
                                    <strong><?= htmlspecialchars((string) $event['new_status']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars((string) $event['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white"><strong>Worker executions</strong></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Job</th><th>Status</th><th>Processed</th><th>Updated</th><th>Failed</th><th>Started</th><th>Finished</th><th>Error</th></tr></thead>
                <tbody>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $job['job_name']) ?></td>
                        <td><span class="badge text-bg-<?= $job['status'] === 'completed' ? 'success' : ($job['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= htmlspecialchars((string) $job['status']) ?></span></td>
                        <td><?= number_format((int) $job['processed']) ?></td>
                        <td><?= number_format((int) $job['updated']) ?></td>
                        <td><?= number_format((int) $job['failed']) ?></td>
                        <td><?= htmlspecialchars((string) $job['started_at']) ?></td>
                        <td><?= htmlspecialchars((string) ($job['finished_at'] ?? '-')) ?></td>
                        <td class="small text-danger"><?= htmlspecialchars((string) ($job['error_message'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
