<?php

use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->type = TicketType::factory()->create(['price' => 50000, 'quantity' => 5]);
    $this->buyer = User::factory()->create();
});

function buy(TicketType $type, int $quantity, string $provider = 'fake'): array
{
    return [
        'event_id' => $type->event_id,
        'provider' => $provider,
        'items' => [['ticket_type_id' => $type->id, 'quantity' => $quantity]],
    ];
}

it('reserves seats and returns a checkout target', function () {
    $this->actingAs($this->buyer)->postJson('/api/orders', buy($this->type, 2))
        ->assertCreated()
        ->assertJsonPath('order.status', 'pending')
        ->assertJsonPath('order.total', 100000)
        ->assertJsonPath('checkout.type', 'redirect');

    expect($this->type->availableCount())->toBe(3);
});

it('never sells more seats than exist', function () {
    $this->actingAs($this->buyer)->postJson('/api/orders', buy($this->type, 4))->assertCreated();

    $this->actingAs(User::factory()->create())->postJson('/api/orders', buy($this->type, 2))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Not enough "'.$this->type->name.'" tickets left.');
});

it('requires a verified email to buy', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->postJson('/api/orders', buy($this->type, 1))
        ->assertForbidden();
});

it('rejects ticket types from another event', function () {
    $foreign = TicketType::factory()->create();
    $payload = buy($this->type, 1);
    $payload['items'][] = ['ticket_type_id' => $foreign->id, 'quantity' => 1];

    $this->actingAs($this->buyer)->postJson('/api/orders', $payload)->assertUnprocessable();
});

it('does not sell tickets for past or draft events', function () {
    $past = TicketType::factory()->for(Event::factory()->past())->create();

    $this->actingAs($this->buyer)->postJson('/api/orders', buy($past, 1))->assertUnprocessable();
});

it('builds a signed LiqPay checkout form', function () {
    $response = $this->actingAs($this->buyer)->postJson('/api/orders', buy($this->type, 1, 'liqpay'))->assertCreated();

    $fields = $response->json('checkout.fields');
    $data = json_decode(base64_decode($fields['data']), true);

    expect($response->json('checkout.type'))->toBe('form')
        ->and($fields['signature'])->toBe(base64_encode(sha1('sandbox_private'.$fields['data'].'sandbox_private', true)))
        ->and($data['amount'])->toEqual(500)
        ->and($data['order_id'])->toBe($response->json('order.uuid'));
});

it('creates a Stripe checkout session through the API', function () {
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/cs_1'])]);

    $this->actingAs($this->buyer)->postJson('/api/orders', buy($this->type, 2, 'stripe'))
        ->assertCreated()
        ->assertJsonPath('checkout.url', 'https://checkout.stripe.com/c/cs_1');

    Http::assertSent(fn ($request) => $request['line_items'][0]['price_data']['unit_amount'] === 50000
        && $request['line_items'][0]['quantity'] === 2
        && $request->hasHeader('Authorization', 'Bearer sk_test_123'));
});

it('answers 502 when the provider is down', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

    $this->actingAs($this->buyer)->postJson('/api/orders', buy($this->type, 1, 'stripe'))->assertStatus(502);
});

it('expires unpaid orders and frees their seats', function () {
    $order = Order::factory()->forTicketType($this->type, 5)->create(['expires_at' => now()->subMinute()]);
    expect($this->type->availableCount())->toBe(0);

    $this->artisan('orders:expire')->assertSuccessful();

    expect($order->fresh()->status->value)->toBe('expired')
        ->and($this->type->availableCount())->toBe(5);
});

it('lets the buyer cancel a pending order but hides it from others', function () {
    $order = Order::factory()->forTicketType($this->type)->create(['user_id' => $this->buyer->id]);

    $this->actingAs(User::factory()->create())->getJson("/api/orders/{$order->uuid}")->assertForbidden();
    $this->actingAs($this->buyer)->postJson("/api/orders/{$order->uuid}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
});
