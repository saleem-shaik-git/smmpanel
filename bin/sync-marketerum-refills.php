<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use App\Services\RefillSyncService;use App\Services\Provider\ProviderFactory;
$result=(new RefillSyncService(ProviderFactory::marketerum()))->sync(100);echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
