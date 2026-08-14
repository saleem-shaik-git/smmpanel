<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/config/bootstrap.php';
use App\Auth; use App\Services\OrderLifecycleService; use App\Services\Provider\ProviderFactory;
App\Services\AdminService::requireAdmin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required');}
Auth::verifyCsrf($_POST['_csrf']??null);$orderId=(int)($_POST['order_id']??0);$action=$_POST['action']??'';
try{$svc=new OrderLifecycleService(ProviderFactory::marketerum());if($action==='cancel')$svc->cancel($orderId);elseif($action==='refill')$svc->refill($orderId);else throw new RuntimeException('Invalid action.');header('Location: /admin/orders.php?success=1');exit;}catch(Throwable $e){http_response_code(422);echo htmlspecialchars($e->getMessage());}
