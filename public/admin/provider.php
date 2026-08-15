<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Auth;
use App\Services\AdminProviderOperationsService;
use App\Services\AdminService;

AdminService::requireAdmin();

$message = null;
$error = null;
$health = null;
$sync = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf($_POST['_csrf'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'health') {
            $health = AdminProviderOperationsService::health();
            $message = $health['status'] === 'healthy'
                ? 'Provider health check completed successfully.'
                : 'Provider health check completed with a degraded result.';
        } elseif ($action === 'sync_services') {
            $sync = AdminProviderOperationsService::runServiceSync();
            $message = sprintf(
                'Service sync completed: %d created, %d updated, %d disabled, %d categories.',
                (int) ($sync['created'] ?? 0),
                (int) ($sync['updated'] ?? 0),
                (int) ($sync['disabled'] ?? 0),
                (int) ($sync['categories'] ?? 0)
            );
        } elseif ($action === 'service') {
            $id = (int) ($_POST['id'] ?? 0);
            $active = (int) ($_POST['active'] ?? 0) === 1;
            $markup = trim((string) ($_POST['markup_percent'] ?? '')) === ''
                ? null
                : (float) $_POST['markup_percent'];
            $selling = trim((string) ($_POST['selling_rate'] ?? '')) === ''
                ? null
                : (float) $_POST['selling_rate'];
            AdminProviderOperationsService::updateService($id, $active, $markup, $selling);
            $message = $active ? 'Service activated and pricing saved.' : 'Service disabled and pricing saved.';
        } else {
            throw new RuntimeException('Invalid provider operation.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$overview = AdminProviderOperationsService::overview();
$services = AdminProviderOperationsService::servicePricing(100);
$csrf = Auth::csrfToken();

function jobBadge(?array $job): string
{
    if (!$job) {
        return '<span class="badge text-bg-secondary">Never run</span>';
    }
    $status = (string) $job['status'];
    $class = $status === 'completed' ? 'success' : ($status === 'failed' ? 'danger' : 'warning');
    return '<span class="badge text-bg-' . $class . '">' . htmlspecialchars($status) . '</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Provider Operations | SMM Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/">SMM Admin</a>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-light btn-sm" href="/admin/services.php">Full Service Manager</a>
            <a class="btn btn-outline-light btn-sm" href="/admin/operations.php">Order Operations</a>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="mb-1">Provider & Service Operations</h2>
            <div class="text-muted">Marketerum connectivity, catalogue synchronization, FX pricing and service availability.</div>
        </div>
        <div class="d-flex gap-2">
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="health">
                <button class="btn btn-outline-primary">Run Health Check</button>
            </form>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="sync_services">
                <button class="btn btn-primary">Sync Service Catalogue</button>
            </form>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><strong>Operation failed:</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($health): ?>
        <div class="alert alert-<?= $health['status'] === 'healthy' ? 'success' : 'warning' ?>">
            <strong>Provider health:</strong>
            <?= htmlspecialchars($health['status']) ?> ·
            <?= (int) $health['latency_ms'] ?> ms ·
            checked <?= htmlspecialchars($health['checked_at']) ?>
            <?php if ($health['error']): ?>
                <div class="small mt-1"><?= htmlspecialchars($health['error']) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Provider</div>
                <div class="fs-5 fw-semibold"><?= htmlspecialchars($overview['provider']) ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Active services</div>
                <div class="fs-3 fw-semibold"><?= number_format($overview['active_services']) ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Disabled</div>
                <div class="fs-3 fw-semibold"><?= number_format($overview['disabled_services']) ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">FX rate</div>
                <div class="fs-5 fw-semibold">
                    <?= $overview['configured_fx'] > 0 ? number_format((float) $overview['configured_fx'], 4) : 'NOT SET' ?>
                </div>
                <div class="small text-muted"><?= htmlspecialchars($overview['provider_currency']) ?> → <?= htmlspecialchars($overview['customer_currency']) ?></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Default markup</div>
                <div class="fs-3 fw-semibold"><?= number_format((float) $overview['default_markup'], 2) ?>%</div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">FX mismatches</div>
                <div class="fs-3 fw-semibold"><?= $overview['fx_mismatches'] === null ? '—' : number_format((int) $overview['fx_mismatches']) ?></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Service sync', $overview['last_service_sync']],
            ['Order sync', $overview['last_order_sync']],
            ['Refill sync', $overview['last_refill_sync']],
        ] as [$label, $job]): ?>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= htmlspecialchars($label) ?></h6>
                        <?= jobBadge($job) ?>
                    </div>
                    <?php if ($job): ?>
                        <div class="small text-muted mt-2">Started: <?= htmlspecialchars($job['started_at']) ?></div>
                        <div class="small">Processed: <?= number_format((int) $job['processed']) ?> · Updated: <?= number_format((int) $job['updated']) ?> · Failed: <?= number_format((int) $job['failed']) ?></div>
                        <?php if ($job['error_message']): ?><div class="small text-danger mt-1"><?= htmlspecialchars($job['error_message']) ?></div><?php endif; ?>
                    <?php else: ?>
                        <div class="text-muted small mt-2">No execution has been recorded yet.</div>
                    <?php endif; ?>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($sync): ?>
        <div class="card border-0 shadow-sm mb-4"><div class="card-body">
            <h6>Latest catalogue synchronization</h6>
            <div class="row g-2 small">
                <div class="col-md-3">Provider services: <strong><?= number_format((int) ($sync['total'] ?? 0)) ?></strong></div>
                <div class="col-md-3">Created: <strong><?= number_format((int) ($sync['created'] ?? 0)) ?></strong></div>
                <div class="col-md-3">Updated: <strong><?= number_format((int) ($sync['updated'] ?? 0)) ?></strong></div>
                <div class="col-md-3">Disabled: <strong><?= number_format((int) ($sync['disabled'] ?? 0)) ?></strong></div>
            </div>
        </div></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Marketerum Service Pricing & Availability</strong>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/services.php">Open full service manager</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Provider</th>
                        <th>Service</th>
                        <th>Provider rate</th>
                        <th>FX</th>
                        <th>Selling / 1K</th>
                        <th>Markup</th>
                        <th>Limits</th>
                        <th>Status</th>
                        <th>Configuration</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?= (int) $service['id'] ?></td>
                        <td><code><?= htmlspecialchars($service['provider_service_id']) ?></code><div class="small text-muted"><?= htmlspecialchars($service['provider_type']) ?></div></td>
                        <td><?= htmlspecialchars($service['name']) ?><div class="small text-muted"><?= htmlspecialchars($service['category'] ?? 'Uncategorized') ?></div></td>
                        <td><?= number_format((float) $service['provider_rate'], 6) ?> <?= htmlspecialchars($service['provider_currency']) ?></td>
                        <td><?= number_format((float) $service['fx_rate'], 4) ?><div class="small text-muted"><?= htmlspecialchars($service['customer_currency']) ?></div></td>
                        <td><?= number_format((float) $service['selling_rate'], 4) ?> <?= htmlspecialchars($service['customer_currency']) ?></td>
                        <td><?= $service['markup_percent'] === null ? 'Default' : number_format((float) $service['markup_percent'], 2) . '%' ?></td>
                        <td><?= number_format((int) $service['min_quantity']) ?>–<?= number_format((int) $service['max_quantity']) ?></td>
                        <td>
                            <?php if ((int) $service['status'] === 1): ?>
                                <span class="badge text-bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td style="min-width:330px">
                            <form method="post" class="row g-1">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="service">
                                <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                                <div class="col-4"><input class="form-control form-control-sm" name="markup_percent" type="number" min="0" max="10000" step="0.01" value="<?= htmlspecialchars((string) ($service['markup_percent'] ?? '')) ?>" placeholder="Markup %"></div>
                                <div class="col-5"><input class="form-control form-control-sm" name="selling_rate" type="number" min="0" step="0.0001" value="<?= htmlspecialchars((string) $service['selling_rate']) ?>" placeholder="Selling rate"></div>
                                <div class="col-3"><input type="hidden" name="active" value="<?= (int) $service['status'] === 1 ? 0 : 1 ?>"><button class="btn btn-sm w-100 <?= (int) $service['status'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>"><?= (int) $service['status'] === 1 ? 'Disable' : 'Enable' ?></button></div>
                            </form>
                            <div class="small text-muted mt-1">Saving this form applies the displayed selling rate and markup.</div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$services): ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No Marketerum services found. Run a catalogue sync.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
