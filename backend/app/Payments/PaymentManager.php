<?php

namespace App\Payments;

use App\Payments\Gateways\FakeGateway;
use App\Payments\Gateways\LiqPayGateway;
use App\Payments\Gateways\StripeGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PaymentManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const array GATEWAYS = [
        'liqpay' => LiqPayGateway::class,
        'stripe' => StripeGateway::class,
        'fake' => FakeGateway::class,
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<string>
     */
    public function enabled(): array
    {
        return array_values(array_intersect(
            config('payments.enabled'),
            array_keys(self::GATEWAYS),
        ));
    }

    public function isEnabled(string $name): bool
    {
        return in_array($name, $this->enabled(), true);
    }

    public function gateway(string $name): PaymentGateway
    {
        $class = self::GATEWAYS[$name] ?? throw new InvalidArgumentException("Unknown payment gateway [{$name}].");

        return $this->container->make($class);
    }
}
