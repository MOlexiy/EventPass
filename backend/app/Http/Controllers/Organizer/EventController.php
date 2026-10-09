<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEventRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\OrderResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::query()
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('organizer_id', $request->user()->id))
            ->with('ticketTypes')
            ->latest('starts_at')
            ->get();

        return EventResource::collection($events);
    }

    public function show(Event $event): EventResource
    {
        Gate::authorize('update', $event);

        return new EventResource($event->load('ticketTypes'));
    }

    public function store(SaveEventRequest $request): JsonResponse
    {
        Gate::authorize('create', Event::class);

        $event = DB::transaction(function () use ($request) {
            $event = $request->user()->organizedEvents()->create([
                ...$request->safe()->except('ticket_types'),
                'status' => EventStatus::Draft,
            ]);
            $this->syncTicketTypes($event, $request->validated('ticket_types'));

            return $event;
        });

        return (new EventResource($event->load('ticketTypes')))->response()->setStatusCode(201);
    }

    public function update(SaveEventRequest $request, Event $event): EventResource
    {
        Gate::authorize('update', $event);

        DB::transaction(function () use ($request, $event) {
            $event->update($request->safe()->except('ticket_types'));
            $this->syncTicketTypes($event, $request->validated('ticket_types'));
        });

        return new EventResource($event->fresh('ticketTypes'));
    }

    public function publish(Event $event): EventResource
    {
        Gate::authorize('update', $event);

        if ($event->status === EventStatus::Cancelled) {
            throw new OrderException('A cancelled event cannot be published again.');
        }

        if ($event->ticketTypes()->doesntExist()) {
            throw new OrderException('Add at least one ticket type before publishing.');
        }

        $event->update(['status' => EventStatus::Published]);

        return new EventResource($event->load('ticketTypes'));
    }

    public function cancel(Event $event): EventResource
    {
        Gate::authorize('update', $event);

        $event->update(['status' => EventStatus::Cancelled]);

        return new EventResource($event->load('ticketTypes'));
    }

    public function destroy(Event $event): Response
    {
        Gate::authorize('delete', $event);

        $event->delete();

        return response()->noContent();
    }

    public function stats(Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $types = $event->ticketTypes->map(fn ($type) => [
            'id' => $type->id,
            'name' => $type->name,
            'price' => $type->price,
            'quantity' => $type->quantity,
            'sold' => $sold = $type->soldCount(),
            'reserved' => $type->takenCount() - $sold,
            'revenue' => $sold * $type->price,
        ]);

        return response()->json(['data' => [
            'ticket_types' => $types,
            'sold' => $types->sum('sold'),
            'revenue' => $event->orders()->where('status', OrderStatus::Paid)->sum('total'),
            'currency' => $event->ticketTypes->first()?->currency,
            'checked_in' => $event->tickets()->whereNotNull('checked_in_at')->count(),
            'issued' => $event->tickets()->whereNull('voided_at')->count(),
        ]]);
    }

    public function orders(Event $event): AnonymousResourceCollection
    {
        Gate::authorize('update', $event);

        return OrderResource::collection(
            $event->orders()->with('user', 'items.ticketType')->latest()->paginate(20)
        );
    }

    /**
     * Upsert ticket types by id. Types that already have orders cannot be
     * removed or shrunk below what is already taken.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncTicketTypes(Event $event, array $rows): void
    {
        $keep = [];

        foreach ($rows as $row) {
            $type = isset($row['id']) ? $event->ticketTypes()->find($row['id']) : null;

            if ($type && $row['quantity'] < $type->takenCount()) {
                throw new OrderException("\"{$type->name}\" already has {$type->takenCount()} tickets taken.");
            }

            $attributes = [
                'name' => $row['name'],
                'price' => $row['price'],
                'quantity' => $row['quantity'],
                'currency' => $row['currency'] ?? 'UAH',
            ];

            $type ? $type->update($attributes) : $type = $event->ticketTypes()->create($attributes);
            $keep[] = $type->id;
        }

        $removed = $event->ticketTypes()->whereNotIn('id', $keep)->get();

        foreach ($removed as $type) {
            if ($type->orderItems()->exists()) {
                throw new OrderException("\"{$type->name}\" has orders and cannot be removed.");
            }
            $type->delete();
        }
    }
}
