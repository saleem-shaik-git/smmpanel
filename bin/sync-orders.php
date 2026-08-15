<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Database;
use App\Services\JobRunService;
use App\Services\Provider\MarketerumProvider;
use App\Services\Provider\OrderSyncService;
use App\Services\Provider\ProviderFactory;

$limit = max(1, min(100, (int) ($argv[1] ?? 100)));
$lockName = 'smmpanel:marketerum:order-sync';
$pdo = Database::connection();

$lockStmt = $pdo->prepare('SELECT GET_LOCK(:name, 0)');
$lockStmt->execute([':name' => $lockName]);
$locked = (int) $lockStmt->fetchColumn() === 1;

if (!$locked) {
    fwrite(STDERR, "Order sync is already running.\n");
    exit(0);
}

$runId = JobRunService::start('marketerum_orders');

try {
    $provider = ProviderFactory::marketerum();
    $result = (new OrderSyncService($provider))->sync($limit);

    JobRunService::finish($runId, $result);
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} catch (Throwable $e) {
    JobRunService::fail($runId, $e);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(:name)');
    $release->execute([':name' => $lockName]);
}
