<?php

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Support\Mail\InboxCheck;
use App\Support\Mail\IncomingMail;
use App\Support\Mail\MailboxReader;
use App\Support\Mail\ReplyText;
use Carbon\CarbonImmutable;

function incoming(array $overrides = []): IncomingMail
{
    return new IncomingMail(...[
        'uid' => 10,
        'from' => 'info@praktijk.nl',
        'subject' => 'Re: Jullie site',
        'text' => "Stuur maar een voorbeeld.\n\nOn Mon, Sep 28, 2026 Loc wrote:\n> Hoi,",
        'messageId' => '<abc@praktijk.nl>',
        'references' => [],
        'receivedAt' => CarbonImmutable::parse('2026-09-29 08:00:00'),
        ...$overrides,
    ]);
}

function inboxWith(array $mail): void
{
    test()->instance(MailboxReader::class, tap(Mockery::mock(MailboxReader::class), function ($fake) use ($mail): void {
        $fake->shouldReceive('newMail')->andReturn($mail);
    }));
}

test('a reply is matched by In-Reply-To and stops the follow-up', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'info@praktijk.nl', 'next_action_at' => now()->addDays(3)]);
    $mailbox = Mailbox::factory()->create();
    $sent = Message::factory()->for($lead)->for($mailbox)->create(['message_id' => '<first@snelstack.com>']);

    inboxWith([incoming(['references' => ['<first@snelstack.com>']])]);

    $result = app(InboxCheck::class)->run($mailbox);

    $sent->refresh();
    $lead->refresh();
    $mailbox->refresh();

    expect($result)->toMatchArray(['replies' => 1, 'bounces' => 0, 'skipped' => 0, 'error' => null])
        ->and($sent->status)->toBe(MessageStatus::Replied)
        ->and($sent->reply_body)->toBe('Stuur maar een voorbeeld.')
        ->and($sent->reply_received_at?->toDateTimeString())->toBe('2026-09-29 08:00:00')
        ->and($lead->status)->toBe(LeadStatus::Replied)
        ->and($lead->next_action_at)->toBeNull()
        ->and($mailbox->last_seen_uid)->toBe(10)
        ->and($mailbox->inbox_checked_at)->not->toBeNull();
});

test('a reply without threading headers lands on the newest mail sent to that address', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'info@praktijk.nl']);
    $mailbox = Mailbox::factory()->create();
    $older = Message::factory()->for($lead)->for($mailbox)->create(['step' => 1, 'sent_at' => now()->subDays(6)]);
    $newest = Message::factory()->for($lead)->for($mailbox)->create(['step' => 2, 'sent_at' => now()->subDay()]);

    inboxWith([incoming(['from' => 'INFO@praktijk.nl', 'references' => []])]);

    app(InboxCheck::class)->run($mailbox);

    expect($newest->refresh()->status)->toBe(MessageStatus::Replied)
        ->and($older->refresh()->status)->toBe(MessageStatus::Sent);
});

test('a delivery failure marks the mail bounced and the lead undeliverable', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'gone@praktijk.nl', 'next_action_at' => now()->addDays(3)]);
    $mailbox = Mailbox::factory()->create();
    $sent = Message::factory()->for($lead)->for($mailbox)->create();

    inboxWith([incoming([
        'from' => 'mailer-daemon@googlemail.com',
        'subject' => 'Delivery Status Notification (Failure)',
        'text' => "Your message wasn't delivered to gone@praktijk.nl because the address couldn't be found.",
        'references' => [],
    ])]);

    $result = app(InboxCheck::class)->run($mailbox);

    expect($result['bounces'])->toBe(1)
        ->and($sent->refresh()->status)->toBe(MessageStatus::Bounced)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Undeliverable)
        ->and($lead->next_action_at)->toBeNull();
});

test('mail from strangers and auto-replies are left alone', function () {
    $mailbox = Mailbox::factory()->create();
    Lead::factory()->emailed()->create(['email' => 'info@praktijk.nl']);

    inboxWith([
        incoming(['uid' => 11, 'from' => 'newsletter@shop.com', 'subject' => 'Sale!']),
        incoming(['uid' => 12, 'autoSubmitted' => true]),
    ]);

    $result = app(InboxCheck::class)->run($mailbox);

    expect($result)->toMatchArray(['replies' => 0, 'bounces' => 0, 'skipped' => 2])
        ->and(Message::query()->where('status', MessageStatus::Replied)->count())->toBe(0)
        ->and($mailbox->refresh()->last_seen_uid)->toBe(12);
});

test('a mailbox that cannot be read reports the error and keeps its place', function () {
    $mailbox = Mailbox::factory()->create(['last_seen_uid' => 5]);

    $this->instance(MailboxReader::class, tap(Mockery::mock(MailboxReader::class), function ($fake): void {
        $fake->shouldReceive('newMail')->andThrow(new RuntimeException("IMAP: login refused\nmore"));
    }));

    $result = app(InboxCheck::class)->run($mailbox);

    expect($result['error'])->toBe('IMAP: login refused')
        ->and($mailbox->refresh()->last_seen_uid)->toBe(5);
});

test('the command reads every mailbox and sums it up', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'info@praktijk.nl']);
    $one = Mailbox::factory()->create();
    $two = Mailbox::factory()->create();
    Message::factory()->for($lead)->for($one)->create(['message_id' => '<first@snelstack.com>']);

    $this->instance(MailboxReader::class, tap(Mockery::mock(MailboxReader::class), function ($fake) use ($one, $two): void {
        // The onboarded test account has a box of its own too; that one stays empty.
        $fake->shouldReceive('newMail')->andReturnUsing(fn (Mailbox $mailbox) => match (true) {
            $mailbox->is($one) => [incoming(['references' => ['<first@snelstack.com>']])],
            $mailbox->is($two) => [incoming(['uid' => 3, 'from' => 'someone@else.com'])],
            default => [],
        });
    }));

    $this->artisan('outreach:check-inbox')
        ->expectsOutputToContain('1 replies, 0 bounces, 1 other mails left alone.')
        ->assertSuccessful();

    expect($two->refresh()->last_seen_uid)->toBe(3);
});

test('every box is read; a good read marks the login ok and a failing one records the error', function () {
    $unchecked = Mailbox::factory()->create(['connection_checked_at' => null]);
    $broken = Mailbox::factory()->create(['connection_error' => 'IMAP: login refused']);

    $this->instance(MailboxReader::class, tap(Mockery::mock(MailboxReader::class), function ($fake) use ($broken): void {
        $fake->shouldReceive('newMail')->andReturnUsing(fn (Mailbox $mailbox) => $mailbox->is($broken)
            ? throw new RuntimeException('AUTHENTICATIONFAILED')
            : []);
    }));

    $this->artisan('outreach:check-inbox')->assertSuccessful();

    expect($unchecked->refresh()->connection_checked_at)->not->toBeNull()
        ->and($unchecked->connection_error)->toBeNull()
        ->and($unchecked->inbox_checked_at)->not->toBeNull()
        ->and($broken->refresh()->connection_error)->toBe('IMAP: AUTHENTICATIONFAILED');
});

test('the quoted mail underneath a reply is stripped', function () {
    expect(ReplyText::strip("Ja graag.\n\nOp ma 28 sep 2026 om 10:00 schreef Loc <loc@snelstack.com>:\n> Hoi,"))->toBe('Ja graag.')
        ->and(ReplyText::strip("Top\r\n\r\n-----Original Message-----\r\nFrom: Loc"))->toBe('Top')
        ->and(ReplyText::strip('> alleen quote'))->toBe('> alleen quote');
});

test('a colleague answering from their own address lands on the company lead', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'info@fitadministraties.nl']);
    $mailbox = Mailbox::factory()->create();
    $sent = Message::factory()->for($lead)->for($mailbox)->create();

    inboxWith([incoming(['from' => 'timstroomer@fitadministraties.nl', 'references' => []])]);

    app(InboxCheck::class)->run($mailbox);

    expect($sent->refresh()->status)->toBe(MessageStatus::Replied)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Replied);
});

test('a domain match is skipped when two leads share it, or for gmail', function () {
    $mailbox = Mailbox::factory()->create();
    foreach (['info@groep.nl', 'sales@groep.nl', 'jan@gmail.com'] as $email) {
        Message::factory()->for(Lead::factory()->emailed()->create(['email' => $email]))->for($mailbox)->create();
    }

    inboxWith([
        incoming(['uid' => 1, 'from' => 'piet@groep.nl', 'references' => []]),
        incoming(['uid' => 2, 'from' => 'kees@gmail.com', 'references' => []]),
    ]);

    $result = app(InboxCheck::class)->run($mailbox);

    expect($result['replies'])->toBe(0)->and($result['skipped'])->toBe(2);
});

test('a delivery delay is not booked as a bounce or a reply', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'info@veiligheidscombinatie.nl']);
    $mailbox = Mailbox::factory()->create();
    $sent = Message::factory()->for($lead)->for($mailbox)->create();

    inboxWith([incoming([
        'from' => 'mailer-daemon@googlemail.com',
        'subject' => 'Delivery Status Notification (Delay)',
        'text' => "Delivery incomplete\nThere was a temporary problem delivering your message to info@veiligheidscombinatie.nl. Gmail will retry for 46 more hours.",
        'references' => [],
    ])]);

    $result = app(InboxCheck::class)->run($mailbox);

    expect($result)->toMatchArray(['replies' => 0, 'bounces' => 0, 'skipped' => 1])
        ->and($sent->refresh()->status)->toBe(MessageStatus::Sent)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Emailed);
});

test('a permanent failure after the retries still counts as a bounce', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'info@veiligheidscombinatie.nl']);
    $mailbox = Mailbox::factory()->create();
    Message::factory()->for($lead)->for($mailbox)->create();

    inboxWith([incoming([
        'from' => 'mailer-daemon@googlemail.com',
        'subject' => 'Delivery Status Notification (Failure)',
        'text' => 'Your message to info@veiligheidscombinatie.nl has failed permanently after 48 hours of retrying.',
        'references' => [],
    ])]);

    expect(app(InboxCheck::class)->run($mailbox)['bounces'])->toBe(1)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Undeliverable);
});

test('a bounce for a lead without a message in the app still marks the lead undeliverable', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'Info@Gone.nl', 'next_action_at' => now()->addDay()]);
    $mailbox = Mailbox::factory()->create();

    inboxWith([incoming([
        'from' => 'mailer-daemon@googlemail.com',
        'subject' => 'Delivery Status Notification (Failure)',
        'text' => "Address not found. Your message wasn't delivered to info@gone.nl because the address couldn't be found.",
        'references' => [],
    ])]);

    expect(app(InboxCheck::class)->run($mailbox)['bounces'])->toBe(1)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Undeliverable)
        ->and($lead->next_action_at)->toBeNull();
});
