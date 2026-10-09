<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\OrderException;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use App\Payments\Checkout;
use App\Payments\PaymentManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public const int MAX_TICKETS_PER_ORDER = 10;

    public function __construct(private readonly PaymentManager $payments) {}

    /**
     * Reserve seats for a pending order. Ticket type rows are locked so two
     * buyers can never take the last seat at the same time.
     *
     * @param  array<int, array{ticket_type_id: int, quantity: int}>  $items
     */
    public function place(User $user, Event $event, array $items, string $provider): Order
    {
        if (! $event->isOnSale()) {
            throw new OrderException('Tickets for this event are not on sale.');
        }

        if (! $this->payments->isEnabled($provider)) {
            throw new OrderException('This payment method is not available.');
        }

        $items = collect($items)
            ->groupBy('ticket_type_id')
            ->map(fn (Collection $rows, $id) => ['ticket_type_id' => (int) $id, 'quantity' => (int) $rows->sum('quantity')])
            ->filter(fn (array $row) => $row['quantity'] > 0)
            ->values();

        if ($items->isEmpty()) {
            throw new OrderException('Choose at least one ticket.');
        }

        if ($items->sum('quantity') > self::MAX_TICKETS_PER_ORDER) {
            throw new OrderException('You can buy up to '.self::MAX_TICKETS_PER_ORDER.' tickets per order.');
        }

        return DB::transaction(function () use ($user, $event, $items, $provider) {
            $types = TicketType::query()
                ->where('event_id', $event->id)
                ->whereIn('id', $items->pluck('ticket_type_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($types->count() !== $items->count()) {
                throw new OrderException('Some ticket types do not belong to this event.');
            }

            foreach ($items as $item) {
                $type = $types[$item['ticket_type_id']];
                if ($type->availableCount() < $item['quantity']) {
                    throw new OrderException("Not enough \"{$type->name}\" tickets left.");
                }
            }

            $order = $user->orders()->create([
                'event_id' => $event->id,
                'status' => OrderStatus::Pending,
                'total' => $items->sum(fn ($i) => $types[$i['ticket_type_id']]->price * $i['quantity']),
                'currency' => $types->first()->currency,
                'payment_provider' => $provider,
                'expires_at' => now()->addMinutes(config('payments.hold_minutes')),
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'ticket_type_id' => $item['ticket_type_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $types[$item['ticket_type_id']]->price,
                ]);
            }

            return $order->load('items.ticketType', 'event');
        });
    }

    /**
     * Create a payment attempt and get the provider's checkout target.
     */
    public function checkout(Order $order): Checkout
    {
        if ($order->status !== OrderStatus::Pending || $order->expires_at?->isPast()) {
            throw new OrderException('This order can no longer be paid.');
        }

        $payment = $order->payments()->create([
            'provider' => $order->payment_provider,
            'status' => PaymentStatus::Created,
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        $order->loadMissing('items.ticketType', 'event', 'user');

        return $this->payments->gateway($order->payment_provider)->createCheckout($order, $payment);
    }

    public function cancel(Order $order): void
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new OrderException('Only pending orders can be cancelled.');
        }

        $order->update(['status' => OrderStatus::Cancelled]);
    }

    /**
     * Release seats held by unpaid orders. Runs every minute from the scheduler.
     */
    public function expireOverdue(): int
    {
        return Order::overdue()->update(['status' => OrderStatus::Expired, 'updated_at' => now()]);
    }
}
