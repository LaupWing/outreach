<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every row belongs to one account. Queries go through the user's relations
 * (`$user->leads()`), so what a request can see is spelled out where it is read.
 */
trait BelongsToUser
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this row is the given user's.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->getAttribute('user_id') === $user->getKey();
    }
}
