<?php

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Mcp\Servers\OutreachServer;
use App\Mcp\Tools\ImportSentMail;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Niche;
use App\Support\Mail\IncomingMail;
use App\Support\Mail\MailboxReader;
use App\Support\Mail\SentMail;
use App\Support\Mail\SentMailImport;
use Carbon\CarbonImmutable;

function readerWith(array $sent, array $inbox = []): void
{
    test()->instance(MailboxReader::class, tap(Mockery::mock(MailboxReader::class), function ($fake) use ($sent, $inbox): void {
        $fake->shouldReceive('sentMail')->andReturn($sent);
        $fake->shouldReceive('mailSince')->andReturn($inbox);
        $fake->shouldReceive('newMail')->andReturn([]);
    }));
}

test('sent mail lands on the lead with that address, threads follow-ups, and replies are caught up', function () {
    $lead = Lead::factory()->create(['email' => 'info@bos.nl', 'status' => LeadStatus::New]);
    $mailbox = Mailbox::factory()->create();

    readerWith([
        new SentMail(1, ['info@bos.nl'], 'Jullie site', 'Hoi, eerste mail.', '<one@gmail.com>', CarbonImmutable::parse('2026-08-10 10:00')),
        new SentMail(2, ['info@bos.nl'], 'Re: Jullie site', 'Nog even.', '<two@gmail.com>', CarbonImmutable::parse('2026-08-14 10:00')),
        new SentMail(3, ['nobody@else.nl'], 'Iets', 'x', '<three@gmail.com>', CarbonImmutable::parse('2026-08-15 10:00')),
    ], [
        new IncomingMail(9, 'info@bos.nl', 'Re: Jullie site', 'Stuur maar.', '<r@bos.nl>', ['<two@gmail.com>'], CarbonImmutable::parse('2026-08-16 09:00')),
    ]);

    $result = app(SentMailImport::class)->run($mailbox, CarbonImmutable::parse('2026-08-01'));

    expect($result)->toMatchArray(['imported' => 2, 'skipped' => 1, 'created' => 0, 'replies' => 1, 'bounces' => 0, 'error' => null]);

    $messages = $lead->messages()->reorder()->orderBy('sent_at')->get();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->step)->toBe(1)
        ->and($messages[1]->step)->toBe(2)
        ->and($messages[1]->thread_id)->toBe($messages[0]->thread_id)
        ->and($messages[1]->status)->toBe(MessageStatus::Replied)
        ->and($messages[1]->reply_body)->toBe('Stuur maar.')
        ->and($lead->refresh()->status)->toBe(LeadStatus::Replied)
        ->and($lead->last_contact_at?->toDateString())->toBe('2026-08-14');
});

test('running the import again skips what is already there', function () {
    Lead::factory()->create(['email' => 'info@bos.nl']);
    $mailbox = Mailbox::factory()->create();
    readerWith([new SentMail(1, ['info@bos.nl'], 'Jullie site', 'Hoi.', '<one@gmail.com>', CarbonImmutable::parse('2026-08-10 10:00'))]);

    app(SentMailImport::class)->run($mailbox, CarbonImmutable::parse('2026-08-01'));
    $second = app(SentMailImport::class)->run($mailbox, CarbonImmutable::parse('2026-08-01'));

    expect($second['imported'])->toBe(0)
        ->and($second['skipped'])->toBe(1)
        ->and(Message::query()->count())->toBe(1);
});

test('the tool runs the import in the background, can create leads, and reports the outcome', function () {
    $niche = Niche::factory()->create();
    $mailbox = Mailbox::factory()->create();
    readerWith([new SentMail(1, ['praktijk@mondzorgzuid.nl'], 'Hoi', 'Tekst', '<m@gmail.com>', CarbonImmutable::parse('2026-08-10 10:00'))]);

    OutreachServer::actingAs($this->user)
        ->tool(ImportSentMail::class, ['since' => '2026-08-01', 'create_leads' => true, 'niche_id' => $niche->id])
        ->assertOk()->assertSee('Import started');

    // The queue runs sync in tests, so the job has already finished.
    $status = OutreachServer::actingAs($this->user)->tool(ImportSentMail::class, ['status' => true]);

    $status->assertOk()->assertSee('Import done')->assertSee('1 sent mails imported')->assertSee('1 leads created');

    $lead = $this->user->leads()->where('email', 'praktijk@mondzorgzuid.nl')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->company)->toBe('Mondzorgzuid')
        ->and($lead->status)->toBe(LeadStatus::Emailed);
});

test('a sent mail without a Message-ID is still not imported twice', function () {
    Lead::factory()->create(['email' => 'info@bos.nl']);
    $mailbox = Mailbox::factory()->create();
    readerWith([new SentMail(1, ['info@bos.nl'], 'Jullie site', 'Hoi.', null, CarbonImmutable::parse('2026-08-10 10:00'))]);

    app(SentMailImport::class)->run($mailbox, CarbonImmutable::parse('2026-08-01'));
    app(SentMailImport::class)->run($mailbox, CarbonImmutable::parse('2026-08-01'));

    expect(Message::query()->count())->toBe(1);
});
