<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\Checkout;
use App\Payments\Exceptions\InvalidSignature;
use App\Payments\PaymentGateway;
use App\Payments\WebhookOutcome;
use App\Payments\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Local simulator: the SPA shows a "bank" page and posts a signed callback,
 * which goes through exactly the same webhook pipeline as real providers.
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function createCheckout(Order $order, Payment $payment): Checkout
    {
        return Checkout::redirect(config('payments.frontend_url').'/checkout/fake/'.$order->uuid);
    }

    public function parseWebhook(Request $request): ?WebhookResult
    {
        $body = $request->getContent();

        if (! hash_equals($this->sign($body), (string) $request->header('X-Fake-Signature'))) {
            throw new InvalidSignature('Fake gateway signature mismatch.');
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true) ?: [];

        return new WebhookResult(
            provider: $this->name(),
            eventId: (string) $payload['event_id'],
            orderUuid: (string) $payload['order_uuid'],
            outcome: WebhookOutcome::from((string) $payload['outcome']),
            providerPaymentId: (string) $payload['payment_id'],
            amount: (int) $payload['amount'],
            payload: $payload,
        );
    }

    public function refund(Payment $payment): void
    {
        // Nothing to call: the simulator always refunds successfully.
    }

    /**
     * @return array<string, mixed>
     */
    public function callbackPayload(Order $order, WebhookOutcome $outcome): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'order_uuid' => $order->uuid,
            'outcome' => $outcome->value,
            'payment_id' => 'fake_'.Str::lower(Str::random(16)),
            'amount' => $order->total,
        ];
    }

    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('app.key'));
    }
}
