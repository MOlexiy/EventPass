<?php

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;

beforeEach(function () {
    $this->type = TicketType::factory()->create();
    $this->event = $this->type->event;
    $order = Order::factory()->forTicketType($this->type)->create(['status' => 'paid']);
    $this->ticket = $order->tickets()->create([
        'event_id' => $this->event->id,
        'ticket_type_id' => $this->type->id,
        'code' => Ticket::generateCode(),
    ]);
});

it('checks a ticket in once', function () {
    $this->actingAs($this->event->organizer)
        ->postJson("/api/organizer/events/{$this->event->slug}/check-in", ['code' => $this->ticket->code])
        ->assertOk()->assertJsonPath('status', 'ok');

    $this->actingAs($this->event->organizer)
        ->postJson("/api/organizer/events/{$this->event->slug}/check-in", ['code' => $this->ticket->code])
        ->assertStatus(409)->assertJsonPath('status', 'already_used');
});

it('accepts codes typed in lowercase', function () {
    $this->actingAs($this->event->organizer)
        ->postJson("/api/organizer/events/{$this->event->slug}/check-in", ['code' => strtolower($this->ticket->code)])
        ->assertOk();
});

it('rejects tickets of another event and refunded tickets', function () {
    $other = TicketType::factory()->create()->event;
    $other->forceFill(['organizer_id' => $this->event->organizer_id])->save();

    $this->actingAs($this->event->organizer)
        ->postJson("/api/organizer/events/{$other->slug}/check-in", ['code' => $this->ticket->code])
        ->assertNotFound();

    $this->ticket->update(['voided_at' => now()]);
    $this->actingAs($this->event->organizer)
        ->postJson("/api/organizer/events/{$this->event->slug}/check-in", ['code' => $this->ticket->code])
        ->assertStatus(409)->assertJsonPath('status', 'voided');
});

it('only lets the event organizer scan', function () {
    $this->actingAs(User::factory()->organizer()->create())
        ->postJson("/api/organizer/events/{$this->event->slug}/check-in", ['code' => $this->ticket->code])
        ->assertForbidden();
});
