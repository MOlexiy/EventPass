<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketsIssued extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your tickets: {$this->order->event->title}");
    }

    public function content(): Content
    {
        $this->order->loadMissing('tickets.ticketType', 'event', 'user');

        return new Content(
            view: 'mail.tickets',
            with: [
                'order' => $this->order,
                'orderUrl' => config('payments.frontend_url').'/orders/'.$this->order->uuid,
            ],
        );
    }
}
