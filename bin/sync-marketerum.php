<?php

declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
use App\Services\Provider\ProviderFactory; use App\Services\Provider\MarketerumSyncService; use App\Services\Provider\OrderSyncService;
$provider=ProviderFactory::marketerum();
$command=$argv[1]??'orders';
if($command==='services'){$result=(new MarketerumSyncService($provider))->sync();}elseif($command==='orders'){$result=(new OrderSyncService($provider))->sync(100);}elseif($command==='balance'){$result=$provider->getBalance();}else{fwrite(STDERR,"Usage: php bin/sync-marketerum.php [services|orders|balance]\n");exit(2);}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
