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

    /**
     * Add an order. The payload must contain service/link and the fields
     * required by the selected Marketerum service type.
     */
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

    /**
     * Fetch up to 100 order statuses in one request.
     */
    public function getMultipleOrderStatuses(array $providerOrderIds): array
    {
        $ids = array_values(array_filter(array_map('strval', $providerOrderIds)));
        if ($ids === []) {
            return [];
        }
        if (count($ids) > 100) {
            throw new RuntimeException('Marketerum supports a maximum of 100 order IDs per status request.');
        }

        return $this->request([
            'action' => 'status',
            'orders' => implode(',', $ids),
        ]);
    }

    public function cancelOrder(string $providerOrderId): array
    {
        return $this->cancelOrders([$providerOrderId]);
    }

    /**
     * Cancel up to 100 provider orders.
     */
    public function cancelOrders(array $providerOrderIds): array
    {
        $ids = array_values(array_filter(array_map('strval', $providerOrderIds)));
        if ($ids === []) {
            return [];
        }
        if (count($ids) > 100) {
            throw new RuntimeException('Marketerum supports a maximum of 100 order IDs per cancel request.');
        }

        return $this->request([
            'action' => 'cancel',
            'orders' => implode(',', $ids),
        ]);
    }

    public function refillOrder(string $providerOrderId): array
    {
        return $this->refillOrders([$providerOrderId]);
    }

    /**
     * Create refills for up to 100 provider orders.
     */
    public function refillOrders(array $providerOrderIds): array
    {
        $ids = array_values(array_filter(array_map('strval', $providerOrderIds)));
        if ($ids === []) {
            return [];
        }
        if (count($ids) > 100) {
            throw new RuntimeException('Marketerum supports a maximum of 100 order IDs per refill request.');
        }

        return $this->request([
            'action' => 'refill',
            'orders' => implode(',', $ids),
        ]);
    }

    public function getRefillStatus(string $refillId): array
    {
        return $this->request([
            'action' => 'refill_status',
            'refill' => $refillId,
        ]);
    }

    /**
     * Fetch up to 100 refill statuses in one request.
     */
    public function getMultipleRefillStatuses(array $refillIds): array
    {
        $ids = array_values(array_filter(array_map('strval', $refillIds)));
        if ($ids === []) {
            return [];
        }
        if (count($ids) > 100) {
            throw new RuntimeException('Marketerum supports a maximum of 100 refill IDs per status request.');
        }

        return $this->request([
            'action' => 'refill_status',
            'refills' => implode(',', $ids),
        ]);
    }

    private function request(array $payload): array
    {
        $payload['key'] = $this->apiKey;

        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
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

        // Marketerum can return an error object for an otherwise valid HTTP request.
        if (isset($decoded['error']) && is_string($decoded['error']) && $decoded['error'] !== '') {
            throw new RuntimeException('Marketerum API error: ' . $decoded['error']);
        }

        return $decoded;
    }
}
