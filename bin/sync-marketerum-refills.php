<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use App\Services\RefillSyncService;use App\Services\Provider\ProviderFactory;use App\Services\JobRunService;
$run=JobRunService::start('marketerum_refills');
try{$result=(new RefillSyncService(ProviderFactory::marketerum()))->sync(100);JobRunService::finish($run,$result);echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;}catch(Throwable $e){JobRunService::fail($run,$e);fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
