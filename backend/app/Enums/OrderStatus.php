<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    /**
     * Statuses that hold seats: a pending order reserves them, a paid one owns them.
     *
     * @return list<self>
     */
    public static function holdingSeats(): array
    {
        return [self::Pending, self::Paid];
    }
}
