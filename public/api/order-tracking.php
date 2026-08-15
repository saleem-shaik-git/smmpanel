<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Auth;
use App\Services\CustomerOrderTrackingService;
use App\Services\Provider\ProviderFactory;

header('Content-Type: application/json; charset=utf-8');
$userId = Auth::requireLogin();
$orderId = (int) ($_GET['order'] ?? 0);

if ($orderId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid order ID.']);
    exit;
}

try {
    $order = (new CustomerOrderTrackingService(ProviderFactory::marketerum()))
        ->getForCustomer($orderId, $userId, true);

    echo json_encode(['data' => $order], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $status = str_contains(strtolower($e->getMessage()), 'not found') ? 404 : 500;
    http_response_code($status);
    echo json_encode(['error' => $status === 404 ? 'Order not found.' : 'Unable to refresh order status.']);
}
