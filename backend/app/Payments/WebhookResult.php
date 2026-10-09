<?php

namespace App\Payments;

final readonly class WebhookResult
{
    /**
     * @param  string  $eventId  unique id of this notification, used for idempotency
     * @param  string|null  $orderUuid  our order reference; null when the provider only knows its own payment id
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public string $eventId,
        public ?string $orderUuid,
        public WebhookOutcome $outcome,
        public ?string $providerPaymentId,
        public ?int $amount,
        public array $payload,
    ) {}
}
