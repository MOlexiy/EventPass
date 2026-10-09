<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\Checkout;
use App\Payments\Exceptions\InvalidSignature;
use App\Payments\Exceptions\PaymentFailed;
use App\Payments\PaymentGateway;
use App\Payments\WebhookOutcome;
use App\Payments\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * LiqPay (PrivatBank) API v3.
 *
 * Every request and callback is a base64 JSON "data" string plus
 * signature = base64(sha1(private_key . data . private_key)).
 */
class LiqPayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'liqpay';
    }

    public function createCheckout(Order $order, Payment $payment): Checkout
    {
        $params = [
            'version' => 3,
            'public_key' => $this->publicKey(),
            'action' => 'pay',
            'amount' => $this->toMajor($order->total),
            'currency' => $order->currency,
            'description' => $order->description(),
            'order_id' => $order->uuid,
            'language' => 'uk',
            'result_url' => config('payments.frontend_url').'/orders/'.$order->uuid,
            'server_url' => rtrim((string) config('payments.webhook_base_url'), '/').'/api/webhooks/liqpay',
            'expired_date' => $order->expires_at?->utc()->format('Y-m-d H:i:s'),
        ];

        if (config('payments.liqpay.sandbox')) {
            $params['sandbox'] = '1';
        }

        [$data, $signature] = $this->encode($params);

        return Checkout::form(config('payments.liqpay.checkout_url'), [
            'data' => $data,
            'signature' => $signature,
        ]);
    }

    public function parseWebhook(Request $request): ?WebhookResult
    {
        $data = (string) $request->input('data');
        $signature = (string) $request->input('signature');

        if ($data === '' || ! hash_equals($this->sign($data), $signature)) {
            throw new InvalidSignature('LiqPay signature mismatch.');
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) base64_decode($data, true), true) ?: [];

        $outcome = match ($payload['status'] ?? null) {
            'success', 'sandbox' => WebhookOutcome::Paid,
            'failure', 'error' => WebhookOutcome::Failed,
            'reversed' => WebhookOutcome::Refunded,
            default => null, // processing, wait_secure, ... are intermediate states
        };

        if ($outcome === null) {
            return null;
        }

        $paymentId = isset($payload['payment_id']) ? (string) $payload['payment_id'] : null;

        return new WebhookResult(
            provider: $this->name(),
            eventId: ($paymentId ?? $payload['order_id']).':'.$payload['status'],
            orderUuid: (string) $payload['order_id'],
            outcome: $outcome,
            providerPaymentId: $paymentId,
            amount: isset($payload['amount']) ? (int) round(((float) $payload['amount']) * 100) : null,
            payload: $payload,
        );
    }

    public function refund(Payment $payment): void
    {
        [$data, $signature] = $this->encode([
            'version' => 3,
            'public_key' => $this->publicKey(),
            'action' => 'refund',
            'order_id' => $payment->order->uuid,
            'amount' => $this->toMajor($payment->amount),
        ]);

        $response = Http::asForm()
            ->timeout(20)
            ->post(config('payments.liqpay.api_url'), ['data' => $data, 'signature' => $signature]);

        if ($response->failed() || ! in_array($response->json('status'), ['reversed', 'success'], true)) {
            throw new PaymentFailed('LiqPay refund failed: '.($response->json('err_description') ?? $response->body()));
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{string, string}
     */
    public function encode(array $params): array
    {
        $data = base64_encode((string) json_encode(array_filter($params, fn ($v) => $v !== null)));

        return [$data, $this->sign($data)];
    }

    public function sign(string $data): string
    {
        $key = $this->privateKey();

        return base64_encode(sha1($key.$data.$key, true));
    }

    private function toMajor(int $minor): float
    {
        return round($minor / 100, 2);
    }

    private function publicKey(): string
    {
        return (string) config('payments.liqpay.public_key');
    }

    private function privateKey(): string
    {
        return (string) config('payments.liqpay.private_key');
    }
}
