<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Services\Provider\ProviderFactory;

header('Content-Type: application/json; charset=utf-8');

// This endpoint is intended for temporary local/staging verification only.
// Keep it disabled or protected by your web server in production.
try {
    $provider = ProviderFactory::marketerum();
    $balance = $provider->getBalance();
    echo json_encode([
        'ok' => true,
        'provider' => 'marketerum',
        'balance' => $balance,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'provider' => 'marketerum',
        'error' => $e->getMessage(),
    ], JSON_THROW_ON_ERROR);
}
