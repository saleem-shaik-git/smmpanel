<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/config/bootstrap.php';

use App\Auth;
use App\Database;

header('Content-Type: application/json; charset=utf-8');
Auth::requireLogin();

try {
    $pdo = Database::connection();
    $query = trim((string) ($_GET['q'] ?? ''));
    $category = (int) ($_GET['category'] ?? 0);
    $sql = "SELECT s.id,s.name,s.description,s.provider_type,s.selling_rate,s.min_quantity,s.max_quantity,
                   s.refill,s.cancel,c.id AS category_id,c.name AS category
            FROM services s
            LEFT JOIN categories c ON c.id=s.category_id
            WHERE s.status=1";
    $params = [];
    if ($category > 0) {
        $sql .= ' AND s.category_id=:category';
        $params[':category'] = $category;
    }
    if ($query !== '') {
        $sql .= " AND (s.name LIKE :q OR s.provider_type LIKE :q OR COALESCE(s.description,'') LIKE :q)";
        $params[':q'] = '%' . $query . '%';
    }
    $sql .= ' ORDER BY COALESCE(c.sort_order,0),c.name,s.name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'data' => $stmt->fetchAll(),
        'currency' => (string) env('CURRENCY', 'NGN'),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load services.'], JSON_THROW_ON_ERROR);
}
