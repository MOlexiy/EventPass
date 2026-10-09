<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    /**
     * Start a payment for the order and tell the SPA how to send the buyer there.
     */
    public function createCheckout(Order $order, Payment $payment): Checkout;

    /**
     * Verify the signature of an incoming webhook and translate it into a
     * provider-agnostic result. Returns null for event types we ignore.
     *
     * @throws Exceptions\InvalidSignature
     */
    public function parseWebhook(Request $request): ?WebhookResult;

    /**
     * Return the money for a captured payment.
     *
     * @throws Exceptions\PaymentFailed
     */
    public function refund(Payment $payment): void;
}
