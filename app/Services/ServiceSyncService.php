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

        $categoryStmt = $pdo->prepare(
            'INSERT INTO categories (name, slug, status) VALUES (:name, :slug, 1)\n' .
            'ON DUPLICATE KEY UPDATE name = VALUES(name), status = 1'
        );

        $sql = <<<'SQL'
INSERT INTO services
(provider, provider_service_id, category_id, name, description, provider_type, provider_rate, selling_rate, min_quantity, max_quantity, refill, cancel, status, provider_raw)
VALUES
(:provider, :provider_service_id, :category_id, :name, :description, :provider_type, :provider_rate, :selling_rate, :min_quantity, :max_quantity, :refill, :cancel, 1, :provider_raw)
ON DUPLICATE KEY UPDATE
category_id = VALUES(category_id),
name = VALUES(name),
description = VALUES(description),
provider_type = VALUES(provider_type),
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

        $pdo->beginTransaction();
        try {
            foreach ($services as $service) {
                $providerServiceId = (string) ($service['service'] ?? '');
                if ($providerServiceId === '') {
                    continue;
                }

                $categoryName = trim((string) ($service['category'] ?? 'Uncategorized'));
                $categorySlug = $this->slug($categoryName);
                $categoryStmt->execute([
                    ':name' => $categoryName,
                    ':slug' => $categorySlug,
                ]);

                $categoryIdStmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
                $categoryIdStmt->execute([':slug' => $categorySlug]);
                $categoryId = $categoryIdStmt->fetchColumn();

                $rate = (float) ($service['rate'] ?? 0);
                $stmt->execute([
                    ':provider' => 'marketerum',
                    ':provider_service_id' => $providerServiceId,
                    ':category_id' => $categoryId !== false ? (int) $categoryId : null,
                    ':name' => (string) ($service['name'] ?? 'Unnamed service'),
                    ':description' => $service['description'] ?? null,
                    ':provider_type' => (string) ($service['type'] ?? 'Default'),
                    ':provider_rate' => $rate,
                    ':selling_rate' => $rate * (1 + ($markup / 100)),
                    ':min_quantity' => (int) ($service['min'] ?? 1),
                    ':max_quantity' => (int) ($service['max'] ?? 1),
                    ':refill' => !empty($service['refill']) ? 1 : 0,
                    ':cancel' => !empty($service['cancel']) ? 1 : 0,
                    ':provider_raw' => json_encode($service, JSON_THROW_ON_ERROR),
                ]);
                $count++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
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

    private function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? ''));
        return trim($slug, '-') ?: 'uncategorized';
    }
}
