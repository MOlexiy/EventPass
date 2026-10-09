<?php

namespace App\Payments;

/**
 * How the browser gets to the payment page: either a plain redirect (Stripe,
 * fake) or an auto-submitted POST form (LiqPay).
 */
final readonly class Checkout
{
    /**
     * @param  array<string, string>  $fields
     */
    private function __construct(
        public string $type,
        public string $url,
        public array $fields = [],
        public ?string $providerPaymentId = null,
    ) {}

    public static function redirect(string $url, ?string $providerPaymentId = null): self
    {
        return new self('redirect', $url, [], $providerPaymentId);
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function form(string $action, array $fields): self
    {
        return new self('form', $action, $fields);
    }

    /**
     * @return array{type: string, url: string, fields: object}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'url' => $this->url, 'fields' => (object) $this->fields];
    }
}
