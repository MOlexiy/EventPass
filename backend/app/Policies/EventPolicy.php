<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function view(?User $user, Event $event): bool
    {
        return $event->status !== EventStatus::Draft || ($user && $this->manage($user, $event));
    }

    public function create(User $user): bool
    {
        return $user->isOrganizer();
    }

    public function update(User $user, Event $event): bool
    {
        return $this->manage($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        // Events with sold tickets are cancelled, never deleted.
        return $this->manage($user, $event) && ! $event->orders()->exists();
    }

    public function checkIn(User $user, Event $event): bool
    {
        return $this->manage($user, $event);
    }

    private function manage(User $user, Event $event): bool
    {
        return $user->isAdmin() || $event->organizer_id === $user->id;
    }
}
