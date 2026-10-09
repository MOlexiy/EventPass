<?php

namespace App\Payments;

enum WebhookOutcome: string
{
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
