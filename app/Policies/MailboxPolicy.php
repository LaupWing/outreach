<?php

namespace App\Policies;

use App\Models\Mailbox;
use App\Models\User;

/**
 * Every row belongs to one account; only that account can see or touch it.
 */
class MailboxPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Mailbox $mailbox): bool
    {
        return $mailbox->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Mailbox $mailbox): bool
    {
        return $mailbox->isOwnedBy($user);
    }

    public function delete(User $user, Mailbox $mailbox): bool
    {
        return $mailbox->isOwnedBy($user);
    }
}
