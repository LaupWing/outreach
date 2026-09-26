<?php

namespace App\Policies;

use App\Models\ScrapeRun;
use App\Models\User;

/**
 * Every row belongs to one account; only that account can see or touch it.
 */
class ScrapeRunPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScrapeRun $scrapeRun): bool
    {
        return $scrapeRun->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ScrapeRun $scrapeRun): bool
    {
        return $scrapeRun->isOwnedBy($user);
    }

    public function delete(User $user, ScrapeRun $scrapeRun): bool
    {
        return $scrapeRun->isOwnedBy($user);
    }
}
