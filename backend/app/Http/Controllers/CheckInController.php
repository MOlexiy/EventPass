<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CheckInController extends Controller
{
    /**
     * Scan a ticket at the door. The conditional UPDATE makes a double scan
     * on two devices at once let exactly one of them through.
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('checkIn', $event);

        $code = strtoupper(trim((string) $request->validate(['code' => ['required', 'string', 'max:64']])['code']));
        $ticket = Ticket::with('ticketType', 'order.user')->where('code', $code)->first();

        if ($ticket === null || $ticket->event_id !== $event->id) {
            return $this->result('invalid', 'Ticket not found for this event.', null, 404);
        }

        if ($ticket->voided_at !== null) {
            return $this->result('voided', 'This ticket was refunded.', $ticket, 409);
        }

        $updated = Ticket::whereKey($ticket->id)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => now(), 'checked_in_by' => $request->user()->id]);

        if ($updated === 0) {
            return $this->result('already_used', 'Already checked in.', $ticket->fresh(['ticketType', 'order.user']), 409);
        }

        return $this->result('ok', 'Welcome!', $ticket->fresh(['ticketType', 'order.user']));
    }

    private function result(string $status, string $message, ?Ticket $ticket, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'ticket' => $ticket ? [
                'code' => $ticket->code,
                'ticket_type' => $ticket->ticketType->name,
                'holder' => $ticket->order->user->name,
                'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
            ] : null,
        ], $code);
    }
}
