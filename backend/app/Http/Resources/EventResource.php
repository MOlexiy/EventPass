<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'venue' => $this->venue,
            'city' => $this->city,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'cover_url' => $this->cover_url,
            'status' => $this->status->value,
            'on_sale' => $this->isOnSale(),
            'organizer' => $this->whenLoaded('organizer', fn () => ['id' => $this->organizer->id, 'name' => $this->organizer->name]),
            'price_from' => $this->whenLoaded('ticketTypes', fn () => $this->ticketTypes->min('price')),
            'currency' => $this->whenLoaded('ticketTypes', fn () => $this->ticketTypes->first()?->currency),
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
        ];
    }
}
