<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'total' => $this->total,
            'currency' => $this->currency,
            'payment_provider' => $this->payment_provider,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'buyer' => $this->whenLoaded('user', fn () => ['name' => $this->user->name, 'email' => $this->user->email]),
            'event' => new EventResource($this->whenLoaded('event')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'ticket_type' => $item->ticketType->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])),
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
