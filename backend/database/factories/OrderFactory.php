<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'status' => OrderStatus::Pending,
            'total' => 0,
            'currency' => 'UAH',
            'payment_provider' => 'fake',
            'expires_at' => now()->addMinutes(15),
        ];
    }

    /**
     * Add one line for the given ticket type and set the total accordingly.
     */
    public function forTicketType(TicketType $type, int $quantity = 1): static
    {
        return $this->state(fn () => [
            'event_id' => $type->event_id,
            'total' => $type->price * $quantity,
            'currency' => $type->currency,
        ])->afterCreating(function (Order $order) use ($type, $quantity) {
            $order->items()->create(['ticket_type_id' => $type->id, 'quantity' => $quantity, 'unit_price' => $type->price]);
        });
    }
}
