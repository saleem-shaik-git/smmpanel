<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) env('APP_NAME', 'SMM Panel')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">SMM Panel</a>
        <div class="ms-auto d-flex gap-2">
            <a class="btn btn-outline-primary" href="#services">Services</a>
            <a class="btn btn-primary" href="/login.php">Login</a>
        </div>
    </div>
</nav>

<main>
<section class="py-5">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="badge text-bg-primary mb-3">Social Media Marketing Platform</span>
                <h1 class="display-4 fw-bold">Grow your social presence from one simple dashboard.</h1>
                <p class="lead text-secondary mt-3">A fast reseller platform for social media marketing services, with local pricing, wallet payments and automated provider fulfilment.</p>
                <div class="d-flex gap-2 mt-4">
                    <a class="btn btn-primary btn-lg" href="/register.php">Create account</a>
                    <a class="btn btn-outline-secondary btn-lg" href="#services">Browse services</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-bold">Why this panel?</h5>
                        <div class="d-flex gap-3 py-3 border-bottom"><i class="bi bi-lightning-charge text-primary fs-4"></i><span>Automated order fulfilment</span></div>
                        <div class="d-flex gap-3 py-3 border-bottom"><i class="bi bi-wallet2 text-primary fs-4"></i><span>Wallet-based ordering</span></div>
                        <div class="d-flex gap-3 py-3"><i class="bi bi-graph-up-arrow text-primary fs-4"></i><span>Reseller-friendly pricing</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section id="services" class="py-5 bg-white">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><i class="bi bi-instagram fs-2"></i><h5 class="mt-3">Instagram</h5><p class="text-secondary mb-0">Followers, likes, views and other available services.</p></div></div></div>
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><i class="bi bi-tiktok fs-2"></i><h5 class="mt-3">TikTok</h5><p class="text-secondary mb-0">TikTok growth services available through the provider catalogue.</p></div></div></div>
            <div class="col-md-4"><div class="card h-100"><div class="card-body"><i class="bi bi-youtube fs-2"></i><h5 class="mt-3">YouTube</h5><p class="text-secondary mb-0">Views, subscribers and other supported services.</p></div></div></div>
        </div>
    </div>
</section>
</main>
</body>
</html>
