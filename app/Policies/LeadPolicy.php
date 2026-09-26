<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Every row belongs to one account; only that account can see or touch it.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $lead->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $lead->isOwnedBy($user);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $lead->isOwnedBy($user);
    }
}
