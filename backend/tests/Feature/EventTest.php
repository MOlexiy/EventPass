<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;

function eventPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Vue Meetup',
        'description' => 'Talks about Vue 3 and Pinia.',
        'venue' => 'Hub 1',
        'city' => 'Kyiv',
        'starts_at' => now()->addMonth()->toIso8601String(),
        'ticket_types' => [
            ['name' => 'Standard', 'price' => 50000, 'quantity' => 100],
            ['name' => 'VIP', 'price' => 150000, 'quantity' => 10],
        ],
    ], $overrides);
}

it('lists only published upcoming events with filters', function () {
    Event::factory()->has(TicketType::factory())->create(['title' => 'Kyiv Jazz', 'city' => 'Kyiv']);
    Event::factory()->has(TicketType::factory())->create(['title' => 'Lviv Rock', 'city' => 'Lviv']);
    Event::factory()->draft()->create(['title' => 'Secret draft']);
    Event::factory()->past()->create(['title' => 'Old show']);

    $this->getJson('/api/events')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/events?city=Lviv')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Lviv Rock');
    $this->getJson('/api/events?q=jazz')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Kyiv Jazz');
});

it('hides drafts from the public but shows them to the owner', function () {
    $event = Event::factory()->draft()->create();

    $this->getJson("/api/events/{$event->slug}")->assertForbidden();
    $this->actingAs($event->organizer)->getJson("/api/events/{$event->slug}")->assertOk();
});

it('lets organizers create events with ticket types', function () {
    $organizer = User::factory()->organizer()->create();

    $this->actingAs($organizer)->postJson('/api/organizer/events', eventPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.slug', 'vue-meetup')
        ->assertJsonCount(2, 'data.ticket_types');
});

it('forbids customers from organizer endpoints', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/api/organizer/events', eventPayload())
        ->assertForbidden();
});

it('forbids organizers from editing someone else\'s event', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->organizer()->create())
        ->putJson("/api/organizer/events/{$event->slug}", eventPayload())
        ->assertForbidden();
});

it('publishes an event', function () {
    $event = Event::factory()->draft()->has(TicketType::factory())->create();

    $this->actingAs($event->organizer)
        ->postJson("/api/organizer/events/{$event->slug}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

it('refuses to shrink a ticket type below what is already sold', function () {
    $type = TicketType::factory()->create(['quantity' => 10]);
    Order::factory()->forTicketType($type, 4)->create(['status' => 'paid']);
    $event = $type->event;

    $this->actingAs($event->organizer)->putJson("/api/organizer/events/{$event->slug}", eventPayload([
        'ticket_types' => [['id' => $type->id, 'name' => $type->name, 'price' => $type->price, 'quantity' => 3]],
    ]))->assertUnprocessable();

    expect($type->fresh()->quantity)->toBe(10);
});

it('validates event input', function () {
    $this->actingAs(User::factory()->organizer()->create())
        ->postJson('/api/organizer/events', eventPayload(['starts_at' => now()->subDay()->toIso8601String(), 'ticket_types' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['starts_at', 'ticket_types']);
});

it('does not publish an event without ticket types', function () {
    $event = Event::factory()->draft()->create();

    $this->actingAs($event->organizer)
        ->postJson("/api/organizer/events/{$event->slug}/publish")
        ->assertUnprocessable();
});
