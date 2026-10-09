<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status(),
            'ticket_type' => $this->whenLoaded('ticketType', fn () => $this->ticketType->name),
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
        ];
    }
}
