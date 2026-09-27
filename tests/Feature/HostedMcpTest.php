<?php

use App\Models\Lead;
use Illuminate\Auth\Middleware\RequirePassword;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

test('the hosted MCP endpoint refuses requests without a token', function () {
    auth()->logout();

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized();
});

test('a Passport token acts as its user on the hosted endpoint', function () {
    Lead::factory()->create(['company' => 'Tandarts Bos']);
    Passport::actingAs($this->user, ['mcp:use']);

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list_leads', 'arguments' => []]])
        ->assertOk()
        ->assertSee('Tandarts Bos');
});

test('OAuth discovery is advertised for MCP clients', function () {
    auth()->logout();

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('code_challenge_methods_supported.0', 'S256');
});

test('the consent page renders in our UI', function () {
    $this->withoutMiddleware(RequirePassword::class);

    $client = Client::factory()->create(['name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']]);

    $this->actingAs($this->user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'abc',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/oauth/authorize')->where('client.name', 'Claude')->where('state', 'abc'));
});
