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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout through the plain REST API (no SDK), so the signing and
 * HTTP details stay visible.
 */
class StripeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(Order $order, Payment $payment): Checkout
    {
        $order->loadMissing('items.ticketType');
        $frontend = config('payments.frontend_url');

        $form = [
            'mode' => 'payment',
            'client_reference_id' => $order->uuid,
            'customer_email' => $order->user->email,
            'success_url' => "{$frontend}/orders/{$order->uuid}?paid=1",
            'cancel_url' => "{$frontend}/orders/{$order->uuid}",
            'metadata' => ['order_uuid' => $order->uuid],
            'payment_intent_data' => ['metadata' => ['order_uuid' => $order->uuid]],
            // Stripe sessions live at least 30 minutes; a late payment for an
            // already expired order is handled in PaymentService.
            'expires_at' => max(now()->addMinutes(30)->getTimestamp(), $order->expires_at?->getTimestamp() ?? 0),
        ];

        foreach ($order->items as $i => $item) {
            $form['line_items'][$i] = [
                'quantity' => $item->quantity,
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => $item->unit_price,
                    'product_data' => ['name' => "{$order->event->title}: {$item->ticketType->name}"],
                ],
            ];
        }

        $response = $this->client()
            ->withHeaders(['Idempotency-Key' => 'checkout-'.$payment->id])
            ->post('/checkout/sessions', $form);

        if ($response->failed()) {
            throw new PaymentFailed('Stripe checkout failed: '.$response->json('error.message', $response->body()));
        }

        return Checkout::redirect((string) $response->json('url'));
    }

    public function parseWebhook(Request $request): ?WebhookResult
    {
        $payload = $request->getContent();
        $this->verifySignature($payload, (string) $request->header('Stripe-Signature'));

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true) ?: [];
        $object = $event['data']['object'] ?? [];

        [$outcome, $orderUuid, $paymentIntent, $amount] = match ($event['type'] ?? null) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => ($object['payment_status'] ?? null) === 'paid'
                ? [WebhookOutcome::Paid, $object['client_reference_id'] ?? null, $object['payment_intent'] ?? null, $object['amount_total'] ?? null]
                : [null, null, null, null],
            'checkout.session.expired', 'checkout.session.async_payment_failed' => [WebhookOutcome::Failed, $object['client_reference_id'] ?? null, $object['payment_intent'] ?? null, null],
            'charge.refunded' => [WebhookOutcome::Refunded, $object['metadata']['order_uuid'] ?? null, $object['payment_intent'] ?? null, $object['amount_refunded'] ?? null],
            default => [null, null, null, null],
        };

        if ($outcome === null) {
            return null;
        }

        return new WebhookResult(
            provider: $this->name(),
            eventId: (string) $event['id'],
            orderUuid: $orderUuid,
            outcome: $outcome,
            providerPaymentId: $paymentIntent,
            amount: $amount !== null ? (int) $amount : null,
            payload: $event,
        );
    }

    public function refund(Payment $payment): void
    {
        $response = $this->client()
            ->withHeaders(['Idempotency-Key' => 'refund-'.$payment->id])
            ->post('/refunds', ['payment_intent' => $payment->provider_payment_id]);

        if ($response->failed()) {
            throw new PaymentFailed('Stripe refund failed: '.$response->json('error.message', $response->body()));
        }
    }

    /**
     * Stripe-Signature: t=timestamp,v1=hex(hmac_sha256(secret, "t.payload"))
     */
    public function verifySignature(string $payload, string $header): void
    {
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);
        $signatures = $parts['v1'] ?? [];

        if ($timestamp === 0 || $signatures === []) {
            throw new InvalidSignature('Malformed Stripe-Signature header.');
        }

        if (abs(time() - $timestamp) > config('payments.stripe.tolerance')) {
            throw new InvalidSignature('Stripe webhook timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", (string) config('payments.stripe.webhook_secret'));

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new InvalidSignature('Stripe signature mismatch.');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('payments.stripe.api_url'))
            ->withToken((string) config('payments.stripe.secret'))
            ->asForm()
            ->timeout(20);
    }
}
