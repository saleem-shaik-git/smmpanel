<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use App\Services\Provider\ProviderFactory;use App\Services\Provider\MarketerumSyncService;use App\Services\Provider\OrderSyncService;use App\Services\JobRunService;
$command=$argv[1]??'orders';$job='marketerum_'.$command;$run=JobRunService::start($job);
try{$provider=ProviderFactory::marketerum();if($command==='services'){$result=(new MarketerumSyncService($provider))->sync();}elseif($command==='orders'){$result=(new OrderSyncService($provider))->sync(100);}elseif($command==='balance'){$result=$provider->getBalance();}else{throw new RuntimeException('Usage: php bin/sync-marketerum.php [services|orders|balance]');}JobRunService::finish($run,$result);echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;}catch(Throwable $e){JobRunService::fail($run,$e);fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
