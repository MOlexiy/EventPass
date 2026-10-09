<?php

use App\Mail\TicketsIssued;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\Mail;

it('sends tickets with embedded QR codes', function () {
    $type = TicketType::factory()->create();
    $order = Order::factory()->forTicketType($type, 2)->create(['status' => 'paid']);
    foreach (range(1, 2) as $_) {
        $order->tickets()->create(['event_id' => $type->event_id, 'ticket_type_id' => $type->id, 'code' => Ticket::generateCode()]);
    }

    Mail::to($order->user)->send(new TicketsIssued($order));

    $sent = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

    expect($sent->getSubject())->toContain($type->event->title)
        ->and($sent->getAttachments())->toHaveCount(2)
        ->and($sent->getHtmlBody())->toContain($order->tickets->first()->code);
});
