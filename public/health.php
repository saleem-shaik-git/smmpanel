<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    \App\Database::connection()->query('SELECT 1');
    echo json_encode(['status' => 'ok', 'database' => 'ok'], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'database' => 'unavailable'], JSON_THROW_ON_ERROR);
}
