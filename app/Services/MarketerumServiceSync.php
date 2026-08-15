<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\Provider\MarketerumProvider;
use RuntimeException;

final class MarketerumServiceSync
{
    public function __construct(private readonly MarketerumProvider $provider) {}

    public function sync(): array
    {
        $remote = $this->provider->getServices();
        $pdo = Database::connection();
        $defaultMarkup = max(0, (float) env('DEFAULT_MARKUP_PERCENT', 40));
        $seen = [];
        $created = $updated = $disabled = $categories = 0;

        $pdo->beginTransaction();
        try {
            foreach ($remote as $item) {
                if (!is_array($item) || !isset($item['service'], $item['name'])) {
                    continue;
                }
                $providerId = (string) $item['service'];
                $name = trim((string) $item['name']);
                $categoryName = trim((string) ($item['category'] ?? 'Uncategorized')) ?: 'Uncategorized';
                $providerRate = max(0, (float) ($item['rate'] ?? 0));
                $min = max(1, (int) ($item['min'] ?? 1));
                $max = max($min, (int) ($item['max'] ?? $min));
                $type = trim((string) ($item['type'] ?? 'Default')) ?: 'Default';
                $refill = !empty($item['refill']) ? 1 : 0;
                $cancel = !empty($item['cancel']) ? 1 : 0;

                $slug = $this->slug($categoryName);
                $cat = $pdo->prepare('SELECT id FROM categories WHERE slug=:slug LIMIT 1');
                $cat->execute([':slug' => $slug]);
                $categoryId = $cat->fetchColumn();
                if ($categoryId === false) {
                    $pdo->prepare('INSERT INTO categories(name,slug,status) VALUES(:name,:slug,1)')
                        ->execute([':name' => $categoryName, ':slug' => $slug]);
                    $categoryId = (int) $pdo->lastInsertId();
                    $categories++;
                } else {
                    $pdo->prepare('UPDATE categories SET name=:name,status=1 WHERE id=:id')
                        ->execute([':name' => $categoryName, ':id' => $categoryId]);
                    $categoryId = (int) $categoryId;
                }

                $find = $pdo->prepare('SELECT id,markup_percent FROM services WHERE provider=:provider AND provider_service_id=:provider_id LIMIT 1');
                $find->execute([':provider' => 'marketerum', ':provider_id' => $providerId]);
                $existing = $find->fetch();
                $markup = $existing && $existing['markup_percent'] !== null
                    ? (float) $existing['markup_percent']
                    : $defaultMarkup;
                $selling = PricingService::sellingRate($providerRate, $markup);

                if ($existing) {
                    $pdo->prepare('UPDATE services SET category_id=:category_id,name=:name,provider_type=:type,provider_rate=:provider_rate,selling_rate=:selling_rate,markup_percent=:markup,min_quantity=:min,max_quantity=:max,refill=:refill,cancel=:cancel,status=1,provider_raw=:raw WHERE id=:id')
                        ->execute([
                            ':category_id'=>$categoryId, ':name'=>$name, ':type'=>$type,
                            ':provider_rate'=>$providerRate, ':selling_rate'=>$selling, ':markup'=>$markup,
                            ':min'=>$min, ':max'=>$max, ':refill'=>$refill, ':cancel'=>$cancel,
                            ':raw'=>json_encode($item, JSON_THROW_ON_ERROR), ':id'=>(int)$existing['id'],
                        ]);
                    $updated++;
                } else {
                    $pdo->prepare('INSERT INTO services(category_id,provider,provider_service_id,name,provider_type,provider_rate,selling_rate,markup_percent,min_quantity,max_quantity,refill,cancel,status,provider_raw) VALUES(:category_id,\'marketerum\',:provider_id,:name,:type,:provider_rate,:selling_rate,:markup,:min,:max,:refill,:cancel,1,:raw)')
                        ->execute([
                            ':category_id'=>$categoryId, ':provider_id'=>$providerId, ':name'=>$name,
                            ':type'=>$type, ':provider_rate'=>$providerRate, ':selling_rate'=>$selling,
                            ':markup'=>$markup, ':min'=>$min, ':max'=>$max, ':refill'=>$refill,
                            ':cancel'=>$cancel, ':raw'=>json_encode($item, JSON_THROW_ON_ERROR),
                        ]);
                    $created++;
                }
                $seen[$providerId] = true;
            }

            if ($seen === []) {
                throw new RuntimeException('Marketerum returned no valid services; existing services were not disabled.');
            }
            $placeholders = implode(',', array_fill(0, count($seen), '?'));
            $stmt = $pdo->prepare("UPDATE services SET status=0 WHERE provider='marketerum' AND provider_service_id NOT IN ($placeholders) AND status=1");
            $stmt->execute(array_keys($seen));
            $disabled = $stmt->rowCount();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return compact('created', 'updated', 'disabled', 'categories') + ['total' => count($seen)];
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
        return $slug !== '' ? $slug : 'uncategorized';
    }
}
