<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class FxPricingService
{
    public static function providerToCustomerRate(): float
    {
        $provider = strtoupper((string) env('MARKETERUM_CURRENCY', 'USD'));
        $customer = strtoupper((string) env('CURRENCY', 'NGN'));
        if ($provider === $customer) return 1.0;

        $configured = (float) env('MARKETERUM_TO_NGN_RATE', 0);
        if ($provider === 'USD' && $customer === 'NGN' && $configured > 0) return $configured;

        throw new RuntimeException("No safe FX rate configured for {$provider} to {$customer}. Set MARKETERUM_TO_NGN_RATE in .env.");
    }

    public static function sellingRate(float $providerRate, ?float $markupPercent = null): float
    {
        $fx = self::providerToCustomerRate();
        $markupPercent ??= (float) env('DEFAULT_MARKUP_PERCENT', 40);
        return round($providerRate * $fx * (1 + max(0, $markupPercent) / 100), 4);
    }

    public static function providerCost(float $providerRate, int $quantity): float
    {
        return round($providerRate * self::providerToCustomerRate() * $quantity / 1000, 4);
    }
}
