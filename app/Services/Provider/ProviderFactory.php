<?php

declare(strict_types=1);

namespace App\Services\Provider;

final class ProviderFactory
{
    public static function marketerum(): MarketerumProvider
    {
        return new MarketerumProvider(
            (string) env('MARKETERUM_API_URL', 'https://marketerum.com/api/v2'),
            (string) env('MARKETERUM_API_KEY', ''),
            (int) env('MARKETERUM_TIMEOUT', 30),
        );
    }
}
