<?php

declare(strict_types=1);
require dirname(__DIR__,2) . '/config/bootstrap.php';
use App\Database; use App\Services\PaymentService;
header('Content-Type: application/json; charset=utf-8');
$secret=(string)env('PAYSTACK_SECRET_KEY','');
$signature=$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$raw=file_get_contents('php://input') ?: '';
if($secret==='' || $signature==='' || !hash_equals(hash_hmac('sha512',$raw,$secret),$signature)){http_response_code(401);echo json_encode(['error'=>'Invalid signature']);exit;}
$data=json_decode($raw,true);
if(!is_array($data) || ($data['event'] ?? '') !== 'charge.success'){http_response_code(200);echo json_encode(['ok'=>true]);exit;}
$payload=$data['data'] ?? [];
$metadata=$payload['metadata'] ?? [];
$userId=(int)($metadata['user_id'] ?? 0);
$amount=((int)($payload['amount'] ?? 0))/100;
$reference=(string)($payload['reference'] ?? '');
if($userId<=0 || $amount<=0 || $reference===''){http_response_code(422);echo json_encode(['error'=>'Invalid payment payload']);exit;}
try{(new PaymentService())->creditWallet($userId,$amount,'PAYSTACK-'.$reference,'Paystack wallet deposit '.$reference);echo json_encode(['ok'=>true]);}catch(Throwable $e){http_response_code(500);echo json_encode(['error'=>'Unable to credit wallet']);}
