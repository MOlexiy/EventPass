<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['order_id', 'event_id', 'ticket_type_id', 'code', 'checked_in_at', 'checked_in_by', 'voided_at'])]
class Ticket extends Model
{
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public static function generateCode(): string
    {
        // 32 random alphanumerics: unguessable, fits comfortably in a QR code.
        return 'EP-'.Str::upper(Str::random(32));
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<TicketType, $this> */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function status(): string
    {
        return match (true) {
            $this->voided_at !== null => 'voided',
            $this->checked_in_at !== null => 'used',
            default => 'valid',
        };
    }
}
