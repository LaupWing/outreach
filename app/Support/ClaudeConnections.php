<?php

namespace App\Support;

use App\Models\User;
use Laravel\Passport\Token;

/**
 * The MCP clients (Claude Code, claude.ai, …) that hold a live token for an account.
 * One row per client; tokens of the same client are one connection to the user.
 */
class ClaudeConnections
{
    /**
     * @return array{url: string, connections: list<array{client_id: string, name: string, connected_at: string|null, last_used_at: string|null}>}
     */
    public static function for(User $user): array
    {
        $connections = $user->tokens()
            ->with('client:id,name')
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('client_id')
            ->map(fn ($tokens) => [
                'client_id' => (string) $tokens->first()->client_id,
                'name' => $tokens->first()->client?->name ?? 'Unknown client',
                'connected_at' => $tokens->min('created_at')?->toJSON(),
                'last_used_at' => $tokens->max('updated_at')?->toJSON(),
            ])
            ->values()
            ->all();

        return [
            'url' => rtrim(config('app.url'), '/').'/mcp',
            'connections' => $connections,
        ];
    }

    /**
     * Cut a client loose: every token it holds for the user is revoked.
     */
    public static function disconnect(User $user, string $clientId): int
    {
        return $user->tokens()
            ->where('client_id', $clientId)
            ->where('revoked', false)
            ->get()
            ->each(fn (Token $token) => $token->revoke())
            ->count();
    }
}
