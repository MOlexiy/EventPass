<?php

use App\Events\OrderPaid;
use App\Mail\TicketsIssued;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->type = TicketType::factory()->create(['price' => 50000, 'quantity' => 3]);
});

function liqpayCallback(array $data): array
{
    $encoded = base64_encode(json_encode($data));

    return ['data' => $encoded, 'signature' => base64_encode(sha1('sandbox_private'.$encoded.'sandbox_private', true))];
}

function stripeServer(string $body, ?int $timestamp = null, string $secret = 'whsec_test'): array
{
    $timestamp ??= time();

    return ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", $secret), 'CONTENT_TYPE' => 'application/json'];
}

function stripeEvent(string $type, array $object): string
{
    return json_encode(['id' => 'evt_'.uniqid(), 'type' => $type, 'data' => ['object' => $object]]);
}

it('marks a LiqPay order paid, issues tickets and emails them', function () {
    $order = Order::factory()->forTicketType($this->type, 2)->create(['payment_provider' => 'liqpay']);

    $this->post('/api/webhooks/liqpay', liqpayCallback([
        'status' => 'sandbox', 'order_id' => $order->uuid, 'payment_id' => 777, 'amount' => 1000,
    ]))->assertOk()->assertJsonPath('status', 'processed');

    $order->refresh();
    expect($order->status->value)->toBe('paid')
        ->and($order->tickets)->toHaveCount(2)
        ->and($order->payments()->first()->provider_payment_id)->toBe('777');

    Mail::assertQueued(TicketsIssued::class, fn ($mail) => $mail->hasTo($order->user->email));
});

it('rejects a LiqPay callback with a forged signature', function () {
    $order = Order::factory()->forTicketType($this->type)->create(['payment_provider' => 'liqpay']);
    $payload = liqpayCallback(['status' => 'success', 'order_id' => $order->uuid, 'payment_id' => 1, 'amount' => 500]);
    $payload['signature'] = base64_encode('forged');

    $this->post('/api/webhooks/liqpay', $payload)->assertStatus(400);
    expect($order->fresh()->status->value)->toBe('pending');
});

it('handles a retried delivery only once', function () {
    Events::fake([OrderPaid::class]);
    $order = Order::factory()->forTicketType($this->type)->create(['payment_provider' => 'liqpay']);
    $payload = liqpayCallback(['status' => 'success', 'order_id' => $order->uuid, 'payment_id' => 5, 'amount' => 500]);

    $this->post('/api/webhooks/liqpay', $payload)->assertJsonPath('status', 'processed');
    $this->post('/api/webhooks/liqpay', $payload)->assertJsonPath('status', 'duplicate');

    Events::assertDispatchedTimes(OrderPaid::class, 1);
});

it('ignores intermediate LiqPay statuses', function () {
    $order = Order::factory()->forTicketType($this->type)->create(['payment_provider' => 'liqpay']);

    $this->post('/api/webhooks/liqpay', liqpayCallback(['status' => 'wait_secure', 'order_id' => $order->uuid]))
        ->assertJsonPath('status', 'ignored');
});

it('marks a Stripe order paid from checkout.session.completed', function () {
    $order = Order::factory()->forTicketType($this->type)->create(['payment_provider' => 'stripe']);
    $body = stripeEvent('checkout.session.completed', [
        'client_reference_id' => $order->uuid, 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'amount_total' => 50000,
    ]);

    $this->call('POST', '/api/webhooks/stripe', server: stripeServer($body), content: $body)
        ->assertOk();

    expect($order->fresh()->status->value)->toBe('paid');
});

it('rejects Stripe webhooks with a bad or stale signature', function () {
    $order = Order::factory()->forTicketType($this->type)->create(['payment_provider' => 'stripe']);
    $body = stripeEvent('checkout.session.completed', ['client_reference_id' => $order->uuid, 'payment_status' => 'paid', 'payment_intent' => 'pi_2']);

    $this->call('POST', '/api/webhooks/stripe', server: stripeServer($body, secret: 'whsec_wrong'), content: $body)
        ->assertStatus(400);
    $this->call('POST', '/api/webhooks/stripe', server: stripeServer($body, time() - 3600), content: $body)
        ->assertStatus(400);

    expect($order->fresh()->status->value)->toBe('pending');
});

it('refunds a payment that does not match the order total', function () {
    $order = Order::factory()->forTicketType($this->type)->create(['payment_provider' => 'liqpay']);
    Http::fake(['www.liqpay.ua/*' => Http::response(['result' => 'ok', 'status' => 'reversed'])]);

    $this->post('/api/webhooks/liqpay', liqpayCallback([
        'status' => 'success', 'order_id' => $order->uuid, 'payment_id' => 9, 'amount' => 1,
    ]))->assertOk();

    expect($order->fresh()->status->value)->toBe('pending');
    Http::assertSentCount(1);
});

it('accepts a late payment when seats are still free', function () {
    $order = Order::factory()->forTicketType($this->type, 2)->create(['payment_provider' => 'liqpay', 'status' => 'expired']);

    $this->post('/api/webhooks/liqpay', liqpayCallback([
        'status' => 'success', 'order_id' => $order->uuid, 'payment_id' => 10, 'amount' => 1000,
    ]));

    expect($order->fresh()->status->value)->toBe('paid');
});

it('refunds a late payment when the seats were resold', function () {
    $late = Order::factory()->forTicketType($this->type, 2)->create(['payment_provider' => 'liqpay', 'status' => 'expired']);
    Order::factory()->forTicketType($this->type, 3)->create(['status' => 'paid']);
    Http::fake(['www.liqpay.ua/*' => Http::response(['result' => 'ok', 'status' => 'reversed'])]);

    $this->post('/api/webhooks/liqpay', liqpayCallback([
        'status' => 'success', 'order_id' => $late->uuid, 'payment_id' => 11, 'amount' => 1000,
    ]));

    expect($late->fresh()->status->value)->toBe('expired')
        ->and($late->payments()->first()->status->value)->toBe('refunded');
});

it('completes the fake checkout through the webhook pipeline', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->forTicketType($this->type)->create(['user_id' => $buyer->id]);

    $this->actingAs($buyer)->postJson("/api/payments/fake/{$order->uuid}", ['outcome' => 'paid'])
        ->assertOk()->assertJsonPath('status', 'paid');

    expect($order->tickets()->count())->toBe(1);
});

it('lets the organizer refund a paid order and voids its tickets', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->forTicketType($this->type)->create(['user_id' => $buyer->id]);
    $this->actingAs($buyer)->postJson("/api/payments/fake/{$order->uuid}", ['outcome' => 'paid']);

    $this->actingAs($buyer)->postJson("/api/organizer/orders/{$order->uuid}/refund")->assertForbidden();

    $this->actingAs($this->type->event->organizer)
        ->postJson("/api/organizer/orders/{$order->uuid}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.tickets.0.status', 'voided');

    expect($this->type->availableCount())->toBe(3);
});

it('processes a Stripe refund notification by payment intent', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->forTicketType($this->type)->create(['user_id' => $buyer->id, 'payment_provider' => 'stripe']);
    $paid = stripeEvent('checkout.session.completed', ['client_reference_id' => $order->uuid, 'payment_status' => 'paid', 'payment_intent' => 'pi_9', 'amount_total' => 50000]);
    $this->call('POST', '/api/webhooks/stripe', server: stripeServer($paid), content: $paid);

    $refund = stripeEvent('charge.refunded', ['payment_intent' => 'pi_9', 'amount_refunded' => 50000, 'metadata' => []]);
    $this->call('POST', '/api/webhooks/stripe', server: stripeServer($refund), content: $refund)->assertOk();

    expect($order->fresh()->status->value)->toBe('refunded');
});
