<?php

namespace App\Policies;

use App\Models\LeadNote;
use App\Models\User;

/**
 * Every row belongs to one account; only that account can see or touch it.
 */
class LeadNotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeadNote $leadNote): bool
    {
        return $leadNote->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LeadNote $leadNote): bool
    {
        return $leadNote->isOwnedBy($user);
    }

    public function delete(User $user, LeadNote $leadNote): bool
    {
        return $leadNote->isOwnedBy($user);
    }
}
