<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Payments\Exceptions\InvalidSignature;
use App\Payments\Gateways\FakeGateway;
use App\Payments\PaymentManager;
use App\Payments\WebhookOutcome;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentManager $manager,
        private readonly PaymentService $payments,
    ) {}

    public function providers(): JsonResponse
    {
        return response()->json(['data' => $this->manager->enabled()]);
    }

    /**
     * Single entry point for provider callbacks. Signature verification lives
     * in each gateway; the outcome is handled the same way for all of them.
     */
    public function webhook(Request $request, string $provider): JsonResponse
    {
        abort_unless($this->manager->isEnabled($provider), 404);

        try {
            $result = $this->manager->gateway($provider)->parseWebhook($request);
        } catch (InvalidSignature $e) {
            Log::warning('Rejected webhook', ['provider' => $provider, 'reason' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        if ($result === null) {
            return response()->json(['status' => 'ignored']);
        }

        $fresh = $this->payments->handle($result);

        return response()->json(['status' => $fresh ? 'processed' : 'duplicate']);
    }

    /**
     * The fake "bank page" in the SPA calls this; it signs a callback and runs
     * it through the regular webhook handling.
     */
    public function fakeComplete(Request $request, Order $order, FakeGateway $gateway): JsonResponse
    {
        abort_unless($this->manager->isEnabled('fake') && $order->payment_provider === 'fake', 404);
        Gate::authorize('pay', $order);

        $data = $request->validate(['outcome' => ['required', Rule::in(['paid', 'failed'])]]);

        if ($order->status !== OrderStatus::Pending) {
            throw new OrderException('This order is not waiting for payment.');
        }

        $body = (string) json_encode($gateway->callbackPayload($order, WebhookOutcome::from($data['outcome'])));
        $callback = Request::create('/api/webhooks/fake', 'POST', server: [
            'HTTP_X_FAKE_SIGNATURE' => $gateway->sign($body),
            'CONTENT_TYPE' => 'application/json',
        ], content: $body);

        $this->payments->handle($gateway->parseWebhook($callback));

        return response()->json(['status' => $order->fresh()->status->value]);
    }
}
