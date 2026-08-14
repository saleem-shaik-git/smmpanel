<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Auth;
use App\Database;

Auth::start();
if (Auth::id() !== null) { header('Location: /dashboard.php'); exit; }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf($_POST['_csrf'] ?? null);
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Enter a valid name, email and password of at least 8 characters.';
    } else {
        try {
            $stmt = Database::connection()->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password)');
            $stmt->execute([':name'=>$name, ':email'=>$email, ':password'=>password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) Database::connection()->lastInsertId();
            Auth::login(['id'=>$id,'role'=>'user']);
            header('Location: /dashboard.php'); exit;
        } catch (PDOException $e) {
            $error = ((int) $e->errorInfo[1] === 1062) ? 'That email is already registered.' : 'Unable to create your account.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create account | SMM Panel</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="row justify-content-center"><div class="col-md-5"><div class="card shadow-sm border-0"><div class="card-body p-4"><h3 class="mb-4">Create account</h3><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>"><div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div><div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div><div class="mb-3"><label class="form-label">Password</label><input name="password" type="password" class="form-control" minlength="8" required></div><button class="btn btn-primary w-100">Create account</button></form><div class="text-center mt-3"><a href="/login.php">Already have an account?</a></div></div></div></div></div></main></body></html>
