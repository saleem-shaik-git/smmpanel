<?php

declare(strict_types=1);

namespace App\Services;

final class PricingService
{
    public static function sellingRate(float $providerRate, ?float $markupPercent = null): float
    {
        $markupPercent ??= (float) env('DEFAULT_MARKUP_PERCENT', 40);
        return round($providerRate * (1 + max(0, $markupPercent) / 100), 6);
    }

    public static function orderCharge(float $sellingRatePerThousand, int $quantity): float
    {
        return round(($sellingRatePerThousand / 1000) * $quantity, 4);
    }
}
