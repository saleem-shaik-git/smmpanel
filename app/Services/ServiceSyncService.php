<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\Provider\SmmProviderInterface;
use PDO;
use RuntimeException;

final class ServiceSyncService
{
    public function __construct(private readonly SmmProviderInterface $provider) {}

    public function sync(): int
    {
        $response = $this->provider->getServices();
        $services = $this->extractServices($response);
        $pdo = Database::connection();
        $count = 0;

        $sql = <<<'SQL'
INSERT INTO services
(provider, provider_service_id, name, description, provider_rate, selling_rate, min_quantity, max_quantity, refill, cancel, status, provider_raw)
VALUES
(:provider, :provider_service_id, :name, :description, :provider_rate, :selling_rate, :min_quantity, :max_quantity, :refill, :cancel, 1, :provider_raw)
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description),
provider_rate = VALUES(provider_rate),
selling_rate = VALUES(selling_rate),
min_quantity = VALUES(min_quantity),
max_quantity = VALUES(max_quantity),
refill = VALUES(refill),
cancel = VALUES(cancel),
status = 1,
provider_raw = VALUES(provider_raw)
SQL;

        $stmt = $pdo->prepare($sql);
        $markup = (float) env('DEFAULT_MARKUP_PERCENT', 40);

        foreach ($services as $service) {
            $rate = (float) ($service['rate'] ?? $service['price'] ?? 0);
            $stmt->execute([
                ':provider' => 'marketerum',
                ':provider_service_id' => (string) ($service['service'] ?? $service['id'] ?? ''),
                ':name' => (string) ($service['name'] ?? 'Unnamed service'),
                ':description' => $service['description'] ?? null,
                ':provider_rate' => $rate,
                ':selling_rate' => $rate * (1 + ($markup / 100)),
                ':min_quantity' => (int) ($service['min'] ?? $service['min_quantity'] ?? 1),
                ':max_quantity' => (int) ($service['max'] ?? $service['max_quantity'] ?? 1),
                ':refill' => !empty($service['refill']) ? 1 : 0,
                ':cancel' => !empty($service['cancel']) ? 1 : 0,
                ':provider_raw' => json_encode($service, JSON_THROW_ON_ERROR),
            ]);
            $count++;
        }

        return $count;
    }

    private function extractServices(array $response): array
    {
        if (array_is_list($response)) {
            return $response;
        }

        foreach (['services', 'data', 'result'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        throw new RuntimeException('Unable to find a service list in the provider response.');
    }
}
