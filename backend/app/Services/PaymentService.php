<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookCall;
use App\Payments\PaymentManager;
use App\Payments\WebhookOutcome;
use App\Payments\WebhookResult;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private readonly PaymentManager $payments) {}

    /**
     * Apply a verified provider notification. Returns false when this exact
     * notification was already processed (providers retry deliveries).
     */
    public function handle(WebhookResult $result): bool
    {
        try {
            return DB::transaction(function () use ($result) {
                WebhookCall::create([
                    'provider' => $result->provider,
                    'event_id' => $result->eventId,
                    'payload' => $result->payload,
                ]);

                $order = $this->resolveOrder($result);

                if ($order === null) {
                    Log::warning('Webhook for unknown order', ['provider' => $result->provider, 'event' => $result->eventId]);

                    return true;
                }

                match ($result->outcome) {
                    WebhookOutcome::Paid => $this->onPaid($order, $result),
                    WebhookOutcome::Failed => $this->onFailed($order, $result),
                    WebhookOutcome::Refunded => $this->onRefunded($order, $result),
                };

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Refund a paid order through the gateway that took the money.
     */
    public function refund(Order $order): void
    {
        if ($order->status !== OrderStatus::Paid) {
            throw new OrderException('Only paid orders can be refunded.');
        }

        $payment = $order->successfulPayment() ?? throw new OrderException('No captured payment found for this order.');

        $this->payments->gateway($payment->provider)->refund($payment);

        DB::transaction(fn () => $this->markRefunded($order, $payment));
    }

    private function resolveOrder(WebhookResult $result): ?Order
    {
        $query = Order::query()->lockForUpdate();

        if ($result->orderUuid !== null) {
            return $query->where('uuid', $result->orderUuid)->first();
        }

        $payment = Payment::where('provider', $result->provider)
            ->where('provider_payment_id', $result->providerPaymentId)
            ->first();

        return $payment ? $query->find($payment->order_id) : null;
    }

    private function onPaid(Order $order, WebhookResult $result): void
    {
        $payment = $this->recordPayment($order, $result, PaymentStatus::Succeeded);

        if ($order->status === OrderStatus::Paid) {
            return; // a second successful notification for an already paid order
        }

        if ($result->amount !== null && $result->amount !== $order->total) {
            Log::error('Paid amount does not match order total', ['order' => $order->uuid, 'paid' => $result->amount]);
            $this->refundLater($payment);

            return;
        }

        // The buyer paid after the hold expired (or cancelled the order in
        // another tab): accept only if the seats are still free.
        if ($order->status !== OrderStatus::Pending && ! $this->seatsStillAvailable($order)) {
            Log::warning('Late payment for an order whose seats were released', ['order' => $order->uuid]);
            $this->refundLater($payment);

            return;
        }

        $order->update(['status' => OrderStatus::Paid, 'paid_at' => now(), 'expires_at' => null]);

        DB::afterCommit(fn () => OrderPaid::dispatch($order));
    }

    private function onFailed(Order $order, WebhookResult $result): void
    {
        // The order stays pending: the buyer can retry until the hold expires.
        $this->recordPayment($order, $result, PaymentStatus::Failed);
    }

    private function onRefunded(Order $order, WebhookResult $result): void
    {
        $payment = $this->recordPayment($order, $result, PaymentStatus::Refunded);

        if ($order->status === OrderStatus::Paid) {
            $this->markRefunded($order, $payment);
        }
    }

    private function markRefunded(Order $order, Payment $payment): void
    {
        $payment->update(['status' => PaymentStatus::Refunded]);
        $order->update(['status' => OrderStatus::Refunded, 'refunded_at' => now()]);
        $order->tickets()->whereNull('voided_at')->update(['voided_at' => now()]);
    }

    private function refundLater(Payment $payment): void
    {
        DB::afterCommit(function () use ($payment) {
            try {
                $this->payments->gateway($payment->provider)->refund($payment);
                $payment->update(['status' => PaymentStatus::Refunded]);
            } catch (\Throwable $e) {
                Log::error('Automatic refund failed, manual action needed', ['payment' => $payment->id, 'error' => $e->getMessage()]);
            }
        });
    }

    private function seatsStillAvailable(Order $order): bool
    {
        foreach ($order->items()->with('ticketType')->get() as $item) {
            if ($item->ticketType->availableCount() < $item->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Attach the provider's payment id to our open payment attempt, or create
     * a record if the notification is the first we hear of it.
     */
    private function recordPayment(Order $order, WebhookResult $result, PaymentStatus $status): Payment
    {
        $payment = null;

        if ($result->providerPaymentId !== null) {
            $payment = $order->payments()
                ->where('provider', $result->provider)
                ->where('provider_payment_id', $result->providerPaymentId)
                ->first();
        }

        $payment ??= $order->payments()
            ->where('provider', $result->provider)
            ->where('status', PaymentStatus::Created)
            ->latest('id')
            ->first();

        $payment ??= $order->payments()->make([
            'provider' => $result->provider,
            'amount' => $result->amount ?? $order->total,
            'currency' => $order->currency,
        ]);

        $payment->fill([
            'provider_payment_id' => $result->providerPaymentId ?? $payment->provider_payment_id,
            'status' => $status,
            'payload' => $result->payload,
        ])->save();

        return $payment;
    }
}
