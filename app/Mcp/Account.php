<?php

namespace App\Mcp;

use App\Models\User;
use Laravel\Mcp\Request;

/**
 * Which account a tool call works on. Over HTTP that is the authenticated
 * user; over stdio there is none, so it is the account named in the config,
 * or the first one.
 */
class Account
{
    public static function for(Request $request): User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        $email = config('services.outreach.mcp_user');

        return User::query()
            ->when($email, fn ($query) => $query->where('email', $email))
            ->orderBy('id')
            ->firstOrFail();
    }
}
