<?php

use App\Enums\MailboxStatus;
use App\Enums\MailboxType;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are sent to the login page', function () {
    $this->get(route('mailboxes.index'))->assertRedirect(route('login'));
});

test('the mailboxes page lists the boxes with their messages and leads', function () {
    $mailbox = Mailbox::factory()->warmingUp()->create();
    $lead = Lead::factory()->create();
    Message::factory()->for($lead)->for($mailbox)->create(['sent_at' => now(), 'reply_body' => 'Ja, graag.', 'reply_received_at' => now()]);

    $this->actingAs(User::factory()->onboarded()->create())
        ->get(route('mailboxes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('mailboxes/index')
            ->has('mailboxes', 1, fn (AssertableInertia $box) => $box
                ->where('id', $mailbox->id)
                ->where('status', 'warming_up')
                ->where('type', 'imap')
                ->etc())
            ->has('messages', 1, fn (AssertableInertia $message) => $message
                ->where('mailbox_id', $mailbox->id)
                ->where('lead_id', $lead->id)
                ->has('reply.body')
                ->missing('reply_body')
                ->missing('body')
                ->etc())
            ->has('leads', 1, fn (AssertableInertia $row) => $row
                ->where('id', $lead->id)
                ->where('company', $lead->company)));
});

test('a mailbox is added with its servers and stores the username as the address when none is given', function () {
    $this->actingAs(User::factory()->onboarded()->create())
        ->post(route('mailboxes.store'), [
            'address' => 'loc@snelstack.com',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'password' => 'app-password',
            'daily_limit' => 40,
            'warm_up' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $mailbox = Mailbox::query()->where('address', 'loc@snelstack.com')->sole();

    expect($mailbox->type)->toBe(MailboxType::Imap)
        ->and($mailbox->username)->toBe('loc@snelstack.com')
        ->and($mailbox->daily_limit)->toBe(40)
        ->and($mailbox->status)->toBe(MailboxStatus::Active)
        ->and($mailbox->warm_up_started_at)->toBeNull();
});

test('a mailbox needs its hosts and a password', function () {
    $this->actingAs(User::factory()->onboarded()->create())
        ->post(route('mailboxes.store'), [
            'address' => 'hallo@snelstack.io',
            'daily_limit' => 30,
            'warm_up' => false,
        ])
        ->assertSessionHasErrors(['imap_host', 'imap_port', 'smtp_host', 'smtp_port', 'password']);
});

test('warming up a new mailbox marks it and stamps the start', function () {
    $this->freezeTime();

    $this->actingAs(User::factory()->onboarded()->create())
        ->post(route('mailboxes.store'), [
            'address' => 'loc@snelstack.nl',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'password' => 'app-password',
            'daily_limit' => 20,
            'warm_up' => true,
        ])
        ->assertSessionHasNoErrors();

    $mailbox = Mailbox::query()->where('address', 'loc@snelstack.nl')->sole();

    expect($mailbox->status)->toBe(MailboxStatus::WarmingUp)
        ->and($mailbox->warm_up_started_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('the address must be unique', function () {
    Mailbox::factory()->create(['address' => 'loc@snelstack.com']);

    $this->actingAs(User::factory()->onboarded()->create())
        ->post(route('mailboxes.store'), [
            'address' => 'loc@snelstack.com',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'password' => 'app-password',
            'daily_limit' => 40,
            'warm_up' => false,
        ])
        ->assertSessionHasErrors('address');
});

test('pausing and resuming puts a warming box back to warming up', function () {
    $user = User::factory()->onboarded()->create();
    $mailbox = Mailbox::factory()->warmingUp()->create();

    $this->actingAs($user)
        ->patch(route('mailboxes.update', $mailbox), ['status' => 'paused'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($mailbox->refresh()->status)->toBe(MailboxStatus::Paused);

    $this->actingAs($user)
        ->patch(route('mailboxes.update', $mailbox), ['status' => 'active'])
        ->assertSessionHasNoErrors();

    expect($mailbox->refresh()->status)->toBe(MailboxStatus::WarmingUp);
});

test('resuming after the two-week warm-up lands on active', function () {
    $mailbox = Mailbox::factory()->paused()->create(['warm_up_started_at' => now()->subDays(15)]);

    $this->actingAs(User::factory()->onboarded()->create())
        ->patch(route('mailboxes.update', $mailbox), ['status' => 'active'])
        ->assertSessionHasNoErrors();

    expect($mailbox->refresh()->status)->toBe(MailboxStatus::Active);
});

test('the limit and warm-up can be edited', function () {
    $mailbox = Mailbox::factory()->create(['daily_limit' => 40]);

    $this->actingAs(User::factory()->onboarded()->create())
        ->patch(route('mailboxes.update', $mailbox), ['daily_limit' => 25, 'warm_up' => true])
        ->assertSessionHasNoErrors();

    $mailbox->refresh();

    expect($mailbox->daily_limit)->toBe(25)
        ->and($mailbox->status)->toBe(MailboxStatus::WarmingUp)
        ->and($mailbox->warm_up_started_at)->not->toBeNull();
});

test('a mailbox can be removed', function () {
    $mailbox = Mailbox::factory()->create();

    $this->actingAs(User::factory()->onboarded()->create())
        ->delete(route('mailboxes.destroy', $mailbox))
        ->assertRedirect();

    $this->assertModelMissing($mailbox);
});
