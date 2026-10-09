<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Created = 'created';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
