<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Auth;
use App\Database;

Auth::start();
if (Auth::id() !== null) {
    header('Location: /dashboard.php'); exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf($_POST['_csrf'] ?? null);
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
        Auth::login($user);
        header('Location: /dashboard.php'); exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="row justify-content-center"><div class="col-md-5"><div class="card shadow-sm border-0"><div class="card-body p-4"><h3 class="mb-4">Sign in</h3><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>"><div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required autocomplete="email"></div><div class="mb-3"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required autocomplete="current-password"></div><button class="btn btn-primary w-100">Login</button></form><div class="text-center mt-3"><a href="/register.php">Create an account</a></div></div></div></div></div></main></body></html>
