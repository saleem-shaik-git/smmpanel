<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Auth; use App\Database; use App\Services\PaystackService; use App\Services\PaymentService;
Auth::requireLogin();
$reference=trim((string)($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$status='pending'; $message='We are verifying your payment.';
if($reference!==''){
    try{
        $payment=(new PaystackService((string)env('PAYSTACK_SECRET_KEY','')))->verify($reference);
        if(($payment['status'] ?? '')==='success'){
            $metadata=$payment['metadata'] ?? [];
            $userId=(int)($metadata['user_id'] ?? Auth::id());
            $amount=((int)($payment['amount'] ?? 0))/100;
            (new PaymentService())->creditWallet($userId,$amount,'PAYSTACK-'.$reference,'Paystack wallet deposit '.$reference);
            $status='success'; $message='Payment verified and your wallet has been credited.';
        } else { $status='failed'; $message='Payment was not successful.'; }
    }catch(Throwable $e){$status='pending';$message='Payment verification is still processing. If your payment was successful, your wallet will be updated after webhook verification.';}
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="alert alert-<?= $status==='success'?'success':($status==='failed'?'danger':'info') ?>"><h4><?= $status==='success'?'Payment successful':($status==='failed'?'Payment failed':'Payment verification') ?></h4><p><?=htmlspecialchars($message)?></p><p class="mb-3">Reference: <strong><?=htmlspecialchars($reference)?></strong></p><a href="/dashboard.php" class="btn btn-primary">Return to dashboard</a></div></main></body></html>
