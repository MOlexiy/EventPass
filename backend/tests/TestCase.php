<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.enabled' => ['fake', 'liqpay', 'stripe'],
            'payments.liqpay.public_key' => 'sandbox_public',
            'payments.liqpay.private_key' => 'sandbox_private',
            'payments.stripe.secret' => 'sk_test_123',
            'payments.stripe.webhook_secret' => 'whsec_test',
        ]);
    }

    /**
     * Make the request look like it comes from the SPA so Sanctum treats it
     * as stateful (session cookie auth).
     */
    protected function fromSpa(): static
    {
        return $this->withHeaders(['Referer' => 'http://localhost', 'Accept' => 'application/json']);
    }
}
