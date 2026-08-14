<?php

declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Auth; use App\Database; use App\Services\PaystackService;
$userId=Auth::requireLogin();
$stmt=Database::connection()->prepare('SELECT name,email FROM users WHERE id=:id AND status="active"');$stmt->execute([':id'=>$userId]);$user=$stmt->fetch();
$amount=(float)($_POST['amount'] ?? 0); $error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    Auth::verifyCsrf($_POST['_csrf'] ?? null);
    if($amount < 100 || $amount > 10000000){$error='Enter an amount between ₦100 and ₦10,000,000.';}
    else{
        try{
            $reference='SMM-'.date('YmdHis').'-'.bin2hex(random_bytes(6));
            $callback=rtrim((string)env('APP_URL','http://localhost/smmpanel/public'),'/').'/payment-callback.php';
            $data=(new PaystackService((string)env('PAYSTACK_SECRET_KEY','')))->initialize($user['email'],$amount,$reference,$callback,['user_id'=>$userId,'name'=>$user['name']]);
            header('Location: '.$data['authorization_url']); exit;
        }catch(Throwable $e){$error='Unable to initialize payment. Please try again.';}
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Add Funds | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="/dashboard.php">SMM Panel</a></div></nav><main class="container py-5"><div class="row justify-content-center"><div class="col-md-6"><div class="card border-0 shadow-sm"><div class="card-body p-4"><h2>Add Funds</h2><p class="text-muted">Fund your SMM wallet securely with Paystack.</p><?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post"><input type="hidden" name="_csrf" value="<?=htmlspecialchars(Auth::csrfToken())?>"><div class="mb-3"><label class="form-label">Amount (NGN)</label><input class="form-control form-control-lg" name="amount" type="number" min="100" max="10000000" step="0.01" value="<?=htmlspecialchars((string)($amount ?: ''))?>" required></div><button class="btn btn-primary w-100">Continue to Paystack</button></form></div></div></div></div></main></body></html>
