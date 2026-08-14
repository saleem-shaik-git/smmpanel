<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Database;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connection();
    $stmt = $pdo->query(
        'SELECT s.id, s.provider_service_id, s.name, s.description, s.provider_type, '
        . 's.selling_rate, s.min_quantity, s.max_quantity, s.refill, s.cancel, '
        . 'c.name AS category '
        . 'FROM services s LEFT JOIN categories c ON c.id = s.category_id '
        . 'WHERE s.status = 1 ORDER BY c.sort_order, c.name, s.id'
    );

    echo json_encode(['data' => $stmt->fetchAll()], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load services.'], JSON_THROW_ON_ERROR);
}
