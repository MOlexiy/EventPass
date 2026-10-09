<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Event;
use App\Models\Order;
use App\Payments\Exceptions\PaymentFailed;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            $request->user()->orders()->with('event', 'items.ticketType')->latest()->paginate(20)
        );
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load('event', 'items.ticketType', 'tickets.ticketType'));
    }

    /**
     * Reserve seats and immediately start the payment.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $event = Event::findOrFail($request->integer('event_id'));
        $order = $this->orders->place($request->user(), $event, $request->validated('items'), $request->validated('provider'));

        return $this->checkoutResponse($order, 201);
    }

    /**
     * Retry payment for a pending order (e.g. the first attempt was declined).
     */
    public function checkout(Order $order): JsonResponse
    {
        Gate::authorize('pay', $order);

        return $this->checkoutResponse($order);
    }

    public function cancel(Order $order): OrderResource
    {
        Gate::authorize('cancel', $order);

        $this->orders->cancel($order);

        return new OrderResource($order->load('event', 'items.ticketType'));
    }

    public function refund(Order $order): OrderResource|JsonResponse
    {
        Gate::authorize('refund', $order);

        try {
            $this->payments->refund($order);
        } catch (PaymentFailed $e) {
            report($e);

            return response()->json(['message' => 'The payment provider rejected the refund.'], 502);
        }

        return new OrderResource($order->fresh(['event', 'items.ticketType', 'user', 'tickets.ticketType']));
    }

    private function checkoutResponse(Order $order, int $status = 200): JsonResponse
    {
        try {
            $checkout = $this->orders->checkout($order);
        } catch (PaymentFailed $e) {
            report($e);

            return response()->json(['message' => 'Could not reach the payment provider. Try again.'], 502);
        }

        return response()->json([
            'order' => new OrderResource($order->load('event', 'items.ticketType')),
            'checkout' => $checkout->toArray(),
        ], $status);
    }
}
