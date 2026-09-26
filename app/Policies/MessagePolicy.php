<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

/**
 * Every row belongs to one account; only that account can see or touch it.
 */
class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Message $message): bool
    {
        return $message->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Message $message): bool
    {
        return $message->isOwnedBy($user);
    }

    public function delete(User $user, Message $message): bool
    {
        return $message->isOwnedBy($user);
    }
}
