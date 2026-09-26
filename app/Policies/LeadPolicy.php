<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Single-tenant for now: anyone who can sign in owns everything. The policy exists
 * so every action already has an authorization boundary when accounts get scoped.
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return true;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return true;
    }
}
