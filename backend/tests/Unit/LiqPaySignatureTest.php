<?php

use App\Payments\Gateways\LiqPayGateway;
use Tests\TestCase;

uses(TestCase::class);

it('signs data the way the LiqPay docs describe', function () {
    // base64(sha1(private_key + data + private_key)) with raw binary sha1
    $gateway = new LiqPayGateway;

    expect($gateway->sign('eyJ2ZXJzaW9uIjozfQ=='))
        ->toBe(base64_encode(sha1('sandbox_private'.'eyJ2ZXJzaW9uIjozfQ=='.'sandbox_private', true)));
});
