<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Mail\TicketsIssued;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Runs on the queue: issuing tickets and sending email must never slow down
 * (or fail) the webhook response the payment provider is waiting for.
 */
class IssueTickets implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public function handle(OrderPaid $event): void
    {
        $order = $event->order->fresh(['items', 'user', 'event']);

        $created = DB::transaction(function () use ($order) {
            // Idempotent: a retried job must not issue a second set.
            if ($order->tickets()->lockForUpdate()->exists()) {
                return false;
            }

            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $order->tickets()->create([
                        'event_id' => $order->event_id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'code' => Ticket::generateCode(),
                    ]);
                }
            }

            return true;
        });

        if ($created) {
            Mail::to($order->user)->queue(new TicketsIssued($order));
        }
    }
}
