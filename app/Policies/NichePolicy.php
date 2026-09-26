<?php

namespace App\Policies;

use App\Models\Niche;
use App\Models\User;

/**
 * Single-tenant for now: anyone who can sign in owns everything. The policy exists
 * so every action already has an authorization boundary when accounts get scoped.
 */
class NichePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Niche $niche): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Niche $niche): bool
    {
        return true;
    }

    public function delete(User $user, Niche $niche): bool
    {
        return true;
    }
}
