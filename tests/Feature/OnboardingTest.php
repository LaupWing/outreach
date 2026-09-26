<?php

use App\Models\Mailbox;
use App\Models\User;
use App\Support\MailboxConnection;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

/** A user who did both steps. */
function onboardedUser(): User
{
    Mailbox::factory()->create();

    return User::factory()->create(['google_places_key' => 'AIza'.str_repeat('a', 35)]);
}

test('a fresh account is sent to onboarding before the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.show'));
});

test('onboarding shows what is still missing', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('onboarding.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('onboarding/index')
            ->where('hasKey', false)
            ->has('mailboxes', 0)
        );
});

test('the places key is saved encrypted and validated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.key'), ['google_places_key' => 'not-a-key'])
        ->assertSessionHasErrors('google_places_key');

    $this->actingAs($user)
        ->post(route('onboarding.key'), ['google_places_key' => 'AIza'.str_repeat('b', 35)])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->google_places_key)->toBe('AIza'.str_repeat('b', 35))
        ->and($user->fresh()->getRawOriginal('google_places_key'))->not->toContain('AIza');
});

test('finishing needs both steps and then opens the dashboard', function () {
    $user = User::factory()->create(['google_places_key' => 'AIza'.str_repeat('c', 35)]);

    $this->actingAs($user)->post(route('onboarding.finish'))->assertRedirect();
    expect(session(SessionKey::FLASH_DATA))->toBeNull();

    Mailbox::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.finish'))
        ->assertRedirect(route('dashboard'));
});

test('an onboarded account skips onboarding', function () {
    $this->actingAs(onboardedUser())
        ->get(route('onboarding.show'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs(User::query()->first())->get(route('dashboard'))->assertOk();
});

test('a mailbox stores its imap and smtp credentials with the password encrypted', function () {
    $this->actingAs(onboardedUser())
        ->post(route('mailboxes.store'), [
            'address' => 'hallo@snelstack.io',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'username' => null,
            'password' => 'abcd efgh ijkl mnop',
            'daily_limit' => 20,
            'warm_up' => true,
        ])
        ->assertSessionHasNoErrors();

    $mailbox = Mailbox::query()->where('address', 'hallo@snelstack.io')->firstOrFail();

    expect($mailbox->username)->toBe('hallo@snelstack.io')
        ->and($mailbox->password)->toBe('abcd efgh ijkl mnop')
        ->and($mailbox->getRawOriginal('password'))->not->toContain('abcd')
        ->and($mailbox->toArray())->not->toHaveKey('password');
});

test('editing a mailbox keeps the password when none is typed and forgets the last check', function () {
    $mailbox = Mailbox::factory()->create(['password' => 'keep-me', 'connection_checked_at' => now()]);

    $this->actingAs(onboardedUser())
        ->patch(route('mailboxes.update', $mailbox), ['smtp_host' => 'smtp.other.com', 'password' => ''])
        ->assertSessionHasNoErrors();

    $mailbox->refresh();

    expect($mailbox->password)->toBe('keep-me')
        ->and($mailbox->smtp_host)->toBe('smtp.other.com')
        ->and($mailbox->connection_checked_at)->toBeNull();
});

test('testing a connection records the outcome on the mailbox', function () {
    $mailbox = Mailbox::factory()->create();

    $this->mock(MailboxConnection::class)->shouldReceive('check')->once()->andReturn('SMTP: 535 bad credentials');

    $this->actingAs(onboardedUser())
        ->post(route('mailboxes.test', $mailbox))
        ->assertRedirect();

    $mailbox->refresh();

    expect($mailbox->connection_error)->toBe('SMTP: 535 bad credentials')
        ->and($mailbox->connection_checked_at)->not->toBeNull()
        ->and(session(SessionKey::FLASH_DATA)['toast']['type'])->toBe('error');
});

test('the google settings page saves a new key', function () {
    $user = onboardedUser();

    $this->actingAs($user)->get(route('google.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/google')->where('hasKey', true));

    $this->actingAs($user)
        ->patch(route('google.update'), ['google_places_key' => 'AIza'.str_repeat('d', 35)])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->google_places_key)->toEndWith('dddd');
});
