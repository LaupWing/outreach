<?php

use App\Models\User;
use Laravel\Passport\Client;

test('the shared props list the MCP url and connected clients', function () {
    $user = User::factory()->onboarded()->create();
    $client = Client::factory()->create(['name' => 'Claude Code']);
    $user->tokens()->create(['id' => 'tok1', 'client_id' => $client->id, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addDay()]);
    $user->tokens()->create(['id' => 'tok2', 'client_id' => $client->id, 'scopes' => ['mcp:use'], 'revoked' => true, 'expires_at' => now()->addDay()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.claude.url', rtrim(config('app.url'), '/').'/mcp')
            ->has('auth.claude.connections', 1)
            ->where('auth.claude.connections.0.name', 'Claude Code'));
});

test('disconnecting revokes every token of that client', function () {
    $user = User::factory()->onboarded()->create();
    $client = Client::factory()->create(['name' => 'Claude Code']);
    $user->tokens()->create(['id' => 'tok1', 'client_id' => $client->id, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addDay()]);
    $other = User::factory()->onboarded()->create();
    $other->tokens()->create(['id' => 'tok9', 'client_id' => $client->id, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addDay()]);

    $this->actingAs($user)
        ->delete(route('claude.destroy', $client->id))
        ->assertRedirect();

    expect($user->tokens()->where('revoked', false)->count())->toBe(0)
        ->and($other->tokens()->where('revoked', false)->count())->toBe(1);
});
