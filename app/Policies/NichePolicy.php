<?php

namespace App\Policies;

use App\Models\Niche;
use App\Models\User;

/**
 * Every row belongs to one account; only that account can see or touch it.
 */
class NichePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Niche $niche): bool
    {
        return $niche->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Niche $niche): bool
    {
        return $niche->isOwnedBy($user);
    }

    public function delete(User $user, Niche $niche): bool
    {
        return $niche->isOwnedBy($user);
    }
}
