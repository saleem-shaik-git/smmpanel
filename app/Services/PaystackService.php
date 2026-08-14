<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PaystackService
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl = 'https://api.paystack.co',
        private readonly int $timeout = 30,
    ) {
        if ($secretKey === '') {
            throw new RuntimeException('PAYSTACK_SECRET_KEY is not configured.');
        }
    }

    public function initialize(string $email, float $amount, string $reference, string $callbackUrl, array $metadata = []): array
    {
        return $this->request('/transaction/initialize', [
            'email' => $email,
            'amount' => (int) round($amount * 100),
            'currency' => 'NGN',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);
    }

    public function verify(string $reference): array
    {
        return $this->request('/transaction/verify/' . rawurlencode($reference), null, 'GET');
    }

    private function request(string $path, ?array $payload = null, string $method = 'POST'): array
    {
        $ch = curl_init(rtrim($this->baseUrl, '/') . $path);
        if ($ch === false) throw new RuntimeException('Unable to initialize Paystack request.');

        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $this->timeout, CURLOPT_CONNECTTIMEOUT => 10];
        if ($payload !== null) $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) throw new RuntimeException('Paystack request failed: ' . $error);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) throw new RuntimeException('Paystack returned invalid JSON.');
        if ($status >= 400 || ($decoded['status'] ?? false) !== true) throw new RuntimeException((string)($decoded['message'] ?? 'Paystack request failed.'));
        return $decoded['data'] ?? [];
    }
}
