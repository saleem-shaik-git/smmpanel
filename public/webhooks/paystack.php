<?php

declare(strict_types=1);
require dirname(__DIR__,2).'/config/bootstrap.php';
use App\Services\PaymentWebhookService;
$raw=file_get_contents('php://input')?:'';$signature=(string)($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']??'');header('Content-Type: application/json');
try{(new PaymentWebhookService())->processPaystack($raw,$signature);http_response_code(200);echo json_encode(['status'=>true]);}catch(Throwable $e){http_response_code(400);echo json_encode(['status'=>false,'message'=>'Webhook rejected']);}
