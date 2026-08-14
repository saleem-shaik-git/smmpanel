<?php

declare(strict_types=1);

namespace App\Services\Provider;

use RuntimeException;

final class MarketerumProvider implements SmmProviderInterface
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 30,
    ) {
        if ($this->apiKey === '') {
            throw new RuntimeException('MARKETERUM_API_KEY is not configured.');
        }
    }

    public function getServices(): array
    {
        return $this->request(['action' => 'services']);
    }

    public function getBalance(): array
    {
        return $this->request(['action' => 'balance']);
    }

    public function addOrder(array $order): array
    {
        return $this->request(array_merge(['action' => 'add'], $order));
    }

    public function getOrderStatus(string $providerOrderId): array
    {
        return $this->request([
            'action' => 'status',
            'order' => $providerOrderId,
        ]);
    }

    public function cancelOrder(string $providerOrderId): array
    {
        return $this->request([
            'action' => 'cancel',
            'order' => $providerOrderId,
        ]);
    }

    public function refillOrder(string $providerOrderId): array
    {
        return $this->request([
            'action' => 'refill',
            'order' => $providerOrderId,
        ]);
    }

    private function request(array $payload): array
    {
        $payload['key'] = $this->apiKey;

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Marketerum request failed: ' . $error);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Marketerum returned invalid JSON (HTTP %d).', $status));
        }

        if ($status >= 400) {
            throw new RuntimeException(sprintf('Marketerum returned HTTP %d.', $status));
        }

        return $decoded;
    }
}
