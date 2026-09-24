<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayClient
{
    public function enabled(): bool
    {
        return $this->keyId() !== '' && $this->keySecret() !== '';
    }

    public function keyId(): string
    {
        return trim((string) config('services.razorpay.key_id'));
    }

    public function createOrder(int $amountSubunits, string $receipt, array $notes = []): array
    {
        $this->assertEnabled();

        $response = $this->request()->post($this->base().'/orders', [
            'amount' => $amountSubunits,
            'currency' => 'INR',
            'receipt' => $receipt,
            'notes' => $notes,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Online payment order could not be created.');
        }

        return $response->json();
    }

    public function fetchPayment(string $paymentId): array
    {
        $this->assertEnabled();

        $response = $this->request()->get($this->base().'/payments/'.rawurlencode($paymentId));
        if (! $response->successful()) {
            throw new RuntimeException('Online payment could not be verified.');
        }

        return $response->json();
    }

    public function refundPayment(string $paymentId, int $amountSubunits, string $reason = ''): array
    {
        $this->assertEnabled();

        $payload = ['amount' => $amountSubunits];
        if ($reason !== '') {
            $payload['notes'] = ['reason' => mb_substr($reason, 0, 250)];
        }

        $response = $this->request()->post(
            $this->base().'/payments/'.rawurlencode($paymentId).'/refund',
            $payload
        );

        if (! $response->successful()) {
            throw new RuntimeException('The online payment provider did not accept the refund.');
        }

        return $response->json();
    }

    public function verifyCheckoutSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = $this->keySecret();
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);

        return hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = trim((string) config('services.razorpay.webhook_secret'));
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withBasicAuth($this->keyId(), $this->keySecret())
            ->timeout(15)
            ->retry(2, 250);
    }

    private function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Online payment gateway is not configured.');
        }
    }

    private function keySecret(): string
    {
        return trim((string) config('services.razorpay.key_secret'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.razorpay.api_base'), '/');
    }
}
