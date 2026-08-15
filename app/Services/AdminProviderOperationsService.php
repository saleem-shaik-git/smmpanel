<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth;
use App\Database;
use App\Services\Provider\MarketerumProvider;
use App\Services\Provider\ProviderFactory;
use RuntimeException;

final class AdminProviderOperationsService
{
    public static function overview(): array
    {
        $pdo = Database::connection();
        $configuredProviderCurrency = strtoupper((string) env('MARKETERUM_CURRENCY', 'USD'));
        $customerCurrency = strtoupper((string) env('CURRENCY', 'NGN'));
        $configuredFx = 0.0;

        try {
            $configuredFx = FxPricingService::providerToCustomerRate();
        } catch (RuntimeException) {
            $configuredFx = 0.0;
        }

        $active = (int) $pdo->query("SELECT COUNT(*) FROM services WHERE provider='marketerum' AND status=1")->fetchColumn();
        $disabled = (int) $pdo->query("SELECT COUNT(*) FROM services WHERE provider='marketerum' AND status=0")->fetchColumn();
        $categories = (int) $pdo->query("SELECT COUNT(*) FROM categories WHERE status=1")->fetchColumn();
        $fxRows = (int) $pdo->query("SELECT COUNT(*) FROM services WHERE provider='marketerum' AND fx_rate > 0 AND ABS(fx_rate - " . (float) $configuredFx . ") > 0.00000001")->fetchColumn();

        return [
            'provider' => 'Marketerum',
            'provider_currency' => $configuredProviderCurrency,
            'customer_currency' => $customerCurrency,
            'configured_fx' => $configuredFx,
            'default_markup' => max(0, (float) env('DEFAULT_MARKUP_PERCENT', 40)),
            'active_services' => $active,
            'disabled_services' => $disabled,
            'active_categories' => $categories,
            'fx_mismatches' => $configuredFx > 0 ? $fxRows : null,
            'last_service_sync' => self::lastJob($pdo, 'marketerum_services'),
            'last_order_sync' => self::lastJob($pdo, 'marketerum_orders'),
            'last_refill_sync' => self::lastJob($pdo, 'marketerum_refills'),
        ];
    }

    public static function servicePricing(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $pdo = Database::connection();
        $stmt = $pdo->query("SELECT s.id,s.provider_service_id,s.name,s.provider_type,s.provider_rate,s.provider_currency,s.selling_rate,s.customer_currency,s.fx_rate,s.markup_percent,s.min_quantity,s.max_quantity,s.refill,s.cancel,s.status,s.updated_at,c.name AS category FROM services s LEFT JOIN categories c ON c.id=s.category_id WHERE s.provider='marketerum' ORDER BY s.status DESC,c.name,s.id LIMIT {$limit}");
        return $stmt->fetchAll();
    }

    public static function updateService(int $serviceId, bool $active, ?float $markupPercent, ?float $sellingRate): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id,provider,provider_rate,markup_percent,status FROM services WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $serviceId]);
        $service = $stmt->fetch();
        if (!$service || $service['provider'] !== 'marketerum') {
            throw new RuntimeException('Marketerum service not found.');
        }
        if ($markupPercent !== null && ($markupPercent < 0 || $markupPercent > 10000)) {
            throw new RuntimeException('Markup must be between 0% and 10,000%.');
        }
        if ($sellingRate !== null && ($sellingRate < 0 || $sellingRate > 100000000)) {
            throw new RuntimeException('Selling rate is invalid.');
        }

        $markup = $markupPercent ?? ($service['markup_percent'] !== null ? (float) $service['markup_percent'] : null);
        if ($sellingRate === null) {
            $sellingRate = FxPricingService::sellingRate((float) $service['provider_rate'], $markup);
        }

        $update = $pdo->prepare('UPDATE services SET status=:status,selling_rate=:selling_rate,markup_percent=:markup WHERE id=:id');
        $update->execute([
            ':status' => $active ? 1 : 0,
            ':selling_rate' => $sellingRate,
            ':markup' => $markup,
            ':id' => $serviceId,
        ]);

        SecurityAuditService::log(Auth::id(), 'admin_service_configuration_changed', [
            'service_id' => $serviceId,
            'provider' => 'marketerum',
            'active' => $active,
            'markup_percent' => $markup,
            'selling_rate' => $sellingRate,
        ]);
    }

    public static function health(): array
    {
        $started = microtime(true);
        $provider = ProviderFactory::marketerum();
        try {
            $balance = $provider->getBalance();
            $latencyMs = (int) round((microtime(true) - $started) * 1000);
            return [
                'status' => 'healthy',
                'latency_ms' => $latencyMs,
                'balance' => $balance,
                'checked_at' => date('Y-m-d H:i:s'),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'degraded',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'balance' => null,
                'checked_at' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function runServiceSync(): array
    {
        $runId = JobRunService::start('marketerum_services');
        try {
            $provider = ProviderFactory::marketerum();
            $result = (new MarketerumServiceSync($provider))->sync();
            JobRunService::finish($runId, [
                'provider_services' => (int) ($result['total'] ?? 0),
                'updated' => (int) (($result['created'] ?? 0) + ($result['updated'] ?? 0)),
                'failed' => 0,
            ]);
            return $result;
        } catch (\Throwable $e) {
            JobRunService::fail($runId, $e);
            throw $e;
        }
    }

    private static function lastJob(\PDO $pdo, string $name): ?array
    {
        $stmt = $pdo->prepare('SELECT id,job_name,status,processed,updated,failed,error_message,started_at,finished_at FROM job_runs WHERE job_name=:name ORDER BY id DESC LIMIT 1');
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
