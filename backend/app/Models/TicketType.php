<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'price', 'currency', 'quantity'])]
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Seats taken by pending (reserved) and paid orders.
     */
    public function takenCount(): int
    {
        return (int) $this->orderItems()
            ->whereHas('order', fn ($q) => $q->whereIn('status', OrderStatus::holdingSeats()))
            ->sum('quantity');
    }

    public function soldCount(): int
    {
        return (int) $this->orderItems()
            ->whereHas('order', fn ($q) => $q->where('status', OrderStatus::Paid))
            ->sum('quantity');
    }

    public function availableCount(): int
    {
        return max(0, $this->quantity - $this->takenCount());
    }
}
