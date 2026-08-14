<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Services\Provider\ProviderFactory;
use App\Services\ServiceSyncService;

try {
    $count = (new ServiceSyncService(ProviderFactory::marketerum()))->sync();
    fwrite(STDOUT, sprintf("Synchronized %d services.\n", $count));
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
