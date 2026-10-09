<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        $events = Event::query()
            ->published()
            ->upcoming()
            ->with('ticketTypes')
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = '%'.mb_strtolower($request->string('q')).'%';
                $q->whereRaw('lower(title) like ?', [$term])->orWhereRaw('lower(venue) like ?', [$term]);
            }))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->string('city')))
            ->orderBy('starts_at')
            ->paginate(12)
            ->withQueryString();

        return EventResource::collection($events);
    }

    public function cities(): JsonResponse
    {
        return response()->json([
            'data' => Event::published()->upcoming()->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    public function show(Event $event): EventResource
    {
        Gate::authorize('view', $event);

        return new EventResource($event->load('ticketTypes', 'organizer'));
    }
}
