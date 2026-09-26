<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

/**
 * Every row belongs to one account; only that account can see or touch it.
 */
class OfferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Offer $offer): bool
    {
        return $offer->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Offer $offer): bool
    {
        return $offer->isOwnedBy($user);
    }

    public function delete(User $user, Offer $offer): bool
    {
        return $offer->isOwnedBy($user);
    }
}
