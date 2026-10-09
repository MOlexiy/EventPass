<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $this->organizes($user, $order);
    }

    public function pay(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function refund(User $user, Order $order): bool
    {
        return $this->organizes($user, $order);
    }

    private function organizes(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->event->organizer_id === $user->id;
    }
}
