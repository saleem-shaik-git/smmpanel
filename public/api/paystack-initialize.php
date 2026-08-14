<?php

declare(strict_types=1);
require dirname(__DIR__,2) . '/config/bootstrap.php';
use App\Auth; use App\Database; use App\Services\PaystackService;
header('Content-Type: application/json; charset=utf-8');
$userId=Auth::requireLogin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['error'=>'POST required']);exit;}
Auth::verifyCsrf($_POST['_csrf'] ?? null);
$amount=(float)($_POST['amount'] ?? 0);
if($amount<100 || $amount>10000000){http_response_code(422);echo json_encode(['error'=>'Amount must be between ₦100 and ₦10,000,000.']);exit;}
$pdo=Database::connection();$stmt=$pdo->prepare('SELECT name,email FROM users WHERE id=:id AND status="active" LIMIT 1');$stmt->execute([':id'=>$userId]);$user=$stmt->fetch();
if(!$user){http_response_code(403);echo json_encode(['error'=>'Account unavailable']);exit;}
$reference='SMM-'.date('YmdHis').'-'.bin2hex(random_bytes(6));
$callback=rtrim((string)env('APP_URL','http://localhost/smmpanel/public'),'/').'/payment-callback.php';
try{$data=(new PaystackService((string)env('PAYSTACK_SECRET_KEY','')))->initialize($user['email'],$amount,$reference,$callback,['user_id'=>$userId,'name'=>$user['name']]);echo json_encode(['data'=>$data,'reference'=>$reference],JSON_THROW_ON_ERROR);}catch(Throwable $e){http_response_code(502);echo json_encode(['error'=>$e->getMessage()]);}
