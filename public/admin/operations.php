<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Auth;
use App\Services\AdminOrderReconciliationService;
use App\Services\AdminService;

AdminService::requireAdmin();

$summary = AdminOrderReconciliationService::summary();
$retryQueue = AdminOrderReconciliationService::retryQueue(50);
$activeOrders = AdminOrderReconciliationService::activeOrders(50);
$refunds = AdminOrderReconciliationService::recentRefunds(30);
$jobs = AdminOrderReconciliationService::recentJobs(30);
$audit = AdminOrderReconciliationService::recentAudit(50);

$csrf = Auth::csrfToken();
$actionMessage = null;
$actionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf($_POST['_csrf'] ?? null);

    $orderId = (int) ($_POST['order_id'] ?? 0);

    if ($orderId <= 0) {
        $actionError = 'Invalid order ID.';
    } else {
        try {
            $result = AdminOrderReconciliationService::applyReconciliation($orderId);
            $actionMessage = $result['mismatch']
                ? 'The order was reconciled, but the provider still reports a different status.'
                : 'Order reconciled successfully; local status now matches the provider.';
        } catch (Throwable $e) {
            $actionError = $e->getMessage();
        }

        $summary = AdminOrderReconciliationService::summary();
        $retryQueue = AdminOrderReconciliationService::retryQueue(50);
        $activeOrders = AdminOrderReconciliationService::activeOrders(50);
        $refunds = AdminOrderReconciliationService::recentRefunds(30);
        $jobs = AdminOrderReconciliationService::recentJobs(30);
        $audit = AdminOrderReconciliationService::recentAudit(50);
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Operations & Reconciliation | SMM Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-size: .92rem; }
        .metric h3 { margin: 0; }
        .table td, .table th { vertical-align: middle; white-space: nowrap; }
        .error-cell { max-width: 360px; white-space: normal !important; }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/">SMM Operations</a>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="/admin/financials.php">Financials</a>
            <a class="btn btn-outline-light btn-sm" href="/admin/">Admin</a>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Operations & Reconciliation</h2>
            <p class="text-muted mb-0">Marketerum order synchronization, retries and refund monitoring.</p>
        </div>
        <a class="btn btn-primary" href="/admin/operations.php">Refresh</a>
    </div>

    <?php if ($actionMessage !== null): ?>
        <div class="alert alert-success"><?= e($actionMessage) ?></div>
    <?php endif; ?>
    <?php if ($actionError !== null): ?>
        <div class="alert alert-danger"><?= e($actionError) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm metric"><div class="card-body">
                <small class="text-muted">Active Marketerum orders</small>
                <h3><?= number_format((int) $summary['active_orders']) ?></h3>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm metric"><div class="card-body">
                <small class="text-muted">Retry backlog</small>
                <h3><?= number_format((int) $summary['retry_backlog']) ?></h3>
                <small class="text-warning"><?= number_format((int) $summary['retry_due']) ?> due now</small>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm metric"><div class="card-body">
                <small class="text-muted">Exhausted retries</small>
                <h3><?= number_format((int) $summary['retry_exhausted']) ?></h3>
                <small class="text-muted">6+ attempts</small>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm metric"><div class="card-body">
                <small class="text-muted">Failed jobs / 24h</small>
                <h3><?= number_format((int) $summary['failed_jobs_24h']) ?></h3>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm metric"><div class="card-body">
                <small class="text-muted">Refunds / 24h</small>
                <h3>₦<?= number_format((float) $summary['refunds_24h'], 2) ?></h3>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm metric"><div class="card-body">
                <small class="text-muted">Refund exceptions</small>
                <h3><?= number_format((int) $summary['refund_exceptions']) ?></h3>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Retry / reconciliation queue</strong>
            <span class="badge text-bg-warning"><?= number_format(count($retryQueue)) ?> shown</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>Order</th><th>Customer</th><th>Provider order</th><th>Status</th>
                    <th>Attempts</th><th>Next attempt</th><th>Last error</th><th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($retryQueue as $row): ?>
                    <tr>
                        <td>#<?= e($row['order_id']) ?></td>
                        <td><?= e($row['customer_name']) ?></td>
                        <td><code><?= e($row['provider_order_id']) ?></code></td>
                        <td><?= e($row['status']) ?></td>
                        <td><?= e($row['attempts']) ?></td>
                        <td><?= e($row['next_attempt_at'] ?? '-') ?></td>
                        <td class="error-cell text-danger small"><?= e($row['last_error'] ?? '') ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="order_id" value="<?= e($row['order_id']) ?>">
                                <button class="btn btn-sm btn-outline-primary" type="submit">Reconcile</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$retryQueue): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No retry records.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header"><strong>Active orders</strong></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>Order</th><th>Customer</th><th>Provider ID</th><th>Local status</th>
                    <th>Quantity</th><th>Remains</th><th>Charge</th><th>Updated</th><th>Retry</th><th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($activeOrders as $row): ?>
                    <tr>
                        <td>#<?= e($row['id']) ?></td>
                        <td><?= e($row['customer_name']) ?></td>
                        <td><code><?= e($row['provider_order_id']) ?></code></td>
                        <td><span class="badge text-bg-secondary"><?= e($row['status']) ?></span></td>
                        <td><?= number_format((int) $row['quantity']) ?></td>
                        <td><?= $row['remains'] === null ? '-' : number_format((int) $row['remains']) ?></td>
                        <td>₦<?= number_format((float) $row['charge'], 2) ?></td>
                        <td><?= e($row['updated_at']) ?></td>
                        <td><?= $row['attempts'] === null ? '-' : e($row['attempts']) ?></td>
                        <td>
                            <form method="post">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="order_id" value="<?= e($row['id']) ?>">
                                <button class="btn btn-sm btn-primary" type="submit">Sync now</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$activeOrders): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No active orders.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Recent refunds</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Order</th><th>Customer</th><th>Reason</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($refunds as $row): ?>
                            <tr>
                                <td>#<?= e($row['order_id']) ?></td>
                                <td><?= e($row['customer_name']) ?></td>
                                <td><?= e($row['reason']) ?></td>
                                <td>₦<?= number_format((float) $row['amount'], 2) ?></td>
                                <td><span class="badge text-bg-<?= $row['status'] === 'completed' ? 'success' : 'danger' ?>"><?= e($row['status']) ?></span></td>
                                <td><?= e($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$refunds): ?><tr><td colspan="6" class="text-center text-muted py-4">No refunds recorded.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong>Recent worker runs</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Job</th><th>Status</th><th>Processed</th><th>Updated</th><th>Failed</th><th>Started</th></tr></thead>
                        <tbody>
                        <?php foreach ($jobs as $row): ?>
                            <tr>
                                <td><?= e($row['job_name']) ?></td>
                                <td><span class="badge text-bg-<?= $row['status'] === 'completed' ? 'success' : ($row['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= e($row['status']) ?></span></td>
                                <td><?= number_format((int) $row['processed']) ?></td>
                                <td><?= number_format((int) $row['updated']) ?></td>
                                <td><?= number_format((int) $row['failed']) ?></td>
                                <td><?= e($row['started_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$jobs): ?><tr><td colspan="6" class="text-center text-muted py-4">No worker runs recorded.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header"><strong>Order audit trail</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Order</th><th>Customer</th><th>Actor</th><th>Action</th><th>Status</th><th>Metadata</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($audit as $row): ?>
                    <tr>
                        <td>#<?= e($row['order_id']) ?></td>
                        <td><?= e($row['customer_name']) ?></td>
                        <td><?= e($row['actor_type']) ?></td>
                        <td><?= e($row['action']) ?></td>
                        <td><?= e($row['old_status']) ?> → <?= e($row['new_status']) ?></td>
                        <td class="error-cell small"><code><?= e($row['metadata']) ?></code></td>
                        <td><?= e($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$audit): ?><tr><td colspan="7" class="text-center text-muted py-4">No audit records found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
