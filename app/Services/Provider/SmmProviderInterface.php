<?php

declare(strict_types=1);

namespace App\Services\Provider;

interface SmmProviderInterface
{
    public function getServices(): array;

    public function getBalance(): array;

    public function addOrder(array $order): array;

    public function getOrderStatus(string $providerOrderId): array;

    public function cancelOrder(string $providerOrderId): array;

    public function refillOrder(string $providerOrderId): array;
}
