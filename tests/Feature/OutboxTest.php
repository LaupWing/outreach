<?php

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Offer;
use App\Models\SequenceStep;
use App\Support\Mail\MailSender;
use App\Support\Mail\Outbox;
use Illuminate\Support\Carbon;

// Amsterdam is UTC+2 in September: 10:00 UTC is noon, inside the 9-17 window.
beforeEach(fn () => Carbon::setTestNow('2026-09-28 10:00:00')); // a Monday

test('queueing spreads a mailbox evenly over the sending window', function () {
    $mailbox = Mailbox::factory()->create(['daily_limit' => 8]); // 8 hours / 8 = one an hour
    $lead = Lead::factory()->create();
    $outbox = app(Outbox::class);

    $first = $outbox->queue($lead, $mailbox, ['subject' => 'A', 'body' => 'a', 'step' => 1]);
    $second = $outbox->queue($lead, $mailbox, ['subject' => 'B', 'body' => 'b', 'step' => 1]);

    expect($first->status)->toBe(MessageStatus::Queued)
        ->and($first->message_id)->toMatch('/^<[0-9a-f-]{36}@[^>]+>$/')
        ->and($first->thread_id)->toMatch('/^thr_[a-z0-9]{5}$/')
        ->and($first->send_after->diffInMinutes(now(), true))->toBeLessThanOrEqual(20)
        ->and($second->send_after->greaterThanOrEqualTo($first->send_after->addHour()))->toBeTrue()
        ->and($second->send_after->diffInMinutes($first->send_after->addHour(), true))->toBeLessThanOrEqual(20);
});

test('a mail queued after hours or on a weekend waits for the next weekday morning', function () {
    Carbon::setTestNow('2026-09-25 16:30:00'); // Friday 18:30 Amsterdam
    $mailbox = Mailbox::factory()->create();
    $lead = Lead::factory()->create();

    $message = app(Outbox::class)->queue($lead, $mailbox, ['subject' => 'A', 'body' => 'a', 'step' => 1]);

    $local = $message->send_after->setTimezone('Europe/Amsterdam');

    expect($local->isMonday())->toBeTrue()
        ->and($local->format('Y-m-d'))->toBe('2026-09-28')
        ->and($local->hour)->toBe(9);
});

test('a reply skips the spreading and is due right away', function () {
    $mailbox = Mailbox::factory()->create(['daily_limit' => 8]);
    $lead = Lead::factory()->create();
    $outbox = app(Outbox::class);
    $outbox->queue($lead, $mailbox, ['subject' => 'A', 'body' => 'a', 'step' => 1]);

    $reply = $outbox->queue($lead, $mailbox, ['subject' => 'Re: A', 'body' => 'r', 'step' => 1, 'thread_id' => 'thr_abcde'], rightAway: true);

    expect($reply->send_after->toDateTimeString())->toBe('2026-09-28 10:00:00')
        ->and($reply->thread_id)->toBe('thr_abcde');
});

test('a warming-up mailbox ramps from a handful to its limit over two weeks', function () {
    $limit = fn (int $daysAgo) => Mailbox::factory()->warmingUp()->create([
        'daily_limit' => 28,
        'warm_up_started_at' => now()->subDays($daysAgo),
    ])->limitToday();

    expect($limit(0))->toBe(5)   // day 1: 28/14 = 2, floored up to the first-day handful
        ->and($limit(6))->toBe(14) // day 7: half
        ->and($limit(13))->toBe(28) // day 14: full
        ->and($limit(30))->toBe(28); // long done
});

test('picking prefers the box with the most room and counts what is already queued', function () {
    $user = $this->user;
    $busy = Mailbox::factory()->create(['daily_limit' => 10, 'sent_today' => 8, 'sent_today_on' => today()]);
    $roomy = Mailbox::factory()->create(['daily_limit' => 10, 'sent_today' => 2, 'sent_today_on' => today()]);
    $paused = Mailbox::factory()->paused()->create();
    $lead = Lead::factory()->create();
    Message::factory()->count(6)->queued()->for($lead)->for($roomy)->create();

    $outbox = app(Outbox::class);

    // roomy: 10 - 2 - 6 = 2, busy: 10 - 8 = 2, tie goes to whichever came first; both beat paused.
    expect($outbox->roomToday($roomy))->toBe(2)
        ->and($outbox->roomToday($busy))->toBe(2)
        ->and($outbox->roomToday($paused))->toBe(0)
        ->and($outbox->pick($user, $paused->id))->toBeNull()
        ->and($outbox->pick($user, $busy->id)?->id)->toBe($busy->id);

    Message::factory()->count(2)->queued()->for($lead)->for($roomy)->create();

    expect($outbox->pick($user)?->id)->toBe($busy->id);
});

test('yesterday\'s count does not eat into today\'s room', function () {
    $mailbox = Mailbox::factory()->create(['daily_limit' => 10, 'sent_today' => 10, 'sent_today_on' => today()->subDay()]);

    expect($mailbox->sent_today)->toBe(0)
        ->and(app(Outbox::class)->roomToday($mailbox))->toBe(10);
});

test('sending hands the mail over and moves the lead along', function () {
    $offer = Offer::factory()->create();
    SequenceStep::factory()->for($offer)->create(['step' => 1]);
    SequenceStep::factory()->for($offer)->followUp(2)->create(['days_after_previous' => 4]);
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create(['email' => 'info@praktijk.nl']);
    $mailbox = Mailbox::factory()->create(['daily_limit' => 10]);
    $message = Message::factory()->queued()->for($lead)->for($mailbox)->create(['step' => 1]);

    $this->mock(MailSender::class)
        ->shouldReceive('send')
        ->once()
        ->withArgs(fn (Message $sent, ?string $inReplyTo) => $sent->is($message) && $inReplyTo === null);

    $status = app(Outbox::class)->send($message);

    $message->refresh();
    $lead->refresh();

    expect($status)->toBe(MessageStatus::Sent)
        ->and($message->status)->toBe(MessageStatus::Sent)
        ->and($message->sent_at?->toDateTimeString())->toBe('2026-09-28 10:00:00')
        ->and($message->send_after)->toBeNull()
        ->and($mailbox->refresh()->sent_today)->toBe(1)
        ->and($mailbox->sent_today_on?->toDateString())->toBe('2026-09-28')
        ->and($lead->status)->toBe(LeadStatus::Emailed)
        ->and($lead->last_contact_at?->toDateTimeString())->toBe('2026-09-28 10:00:00')
        ->and($lead->next_action_at?->toDateTimeString())->toBe('2026-10-02 10:00:00');
});

test('a follow-up references the mail it continues and a replied lead keeps its status', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Replied]);
    $mailbox = Mailbox::factory()->create();
    $original = Message::factory()->replied()->for($lead)->for($mailbox)->create(['thread_id' => 'thr_aaaaa', 'message_id' => '<first@example.com>']);
    $reply = Message::factory()->queued()->for($lead)->for($mailbox)->create(['thread_id' => 'thr_aaaaa', 'step' => $original->step]);

    $this->mock(MailSender::class)
        ->shouldReceive('send')
        ->once()
        ->withArgs(fn (Message $sent, ?string $inReplyTo) => $sent->is($reply) && $inReplyTo === '<first@example.com>');

    app(Outbox::class)->send($reply);

    expect($reply->refresh()->status)->toBe(MessageStatus::Sent)
        ->and($lead->refresh()->status)->toBe(LeadStatus::Replied)
        ->and($lead->next_action_at)->toBeNull();
});

test('a refused mail is marked failed with the reason', function () {
    $message = Message::factory()->queued()->create();

    $this->mock(MailSender::class)
        ->shouldReceive('send')
        ->andThrow(new RuntimeException("Expected response code \"250\" but got code \"535\", with message \"535 5.7.8 Username and Password not accepted\".\nSecond line"));

    $status = app(Outbox::class)->send($message);

    $message->refresh();

    expect($status)->toBe(MessageStatus::Failed)
        ->and($message->status)->toBe(MessageStatus::Failed)
        ->and($message->error)->toStartWith('Expected response code "250" but got code "535"')
        ->and($message->error)->not->toContain('Second line')
        ->and($message->mailbox->refresh()->sent_today)->toBe(0)
        ->and($message->lead->refresh()->status)->toBe(LeadStatus::New);
});

test('a lead without an email address cannot be sent to', function () {
    $lead = Lead::factory()->create(['email' => null]);
    $message = Message::factory()->queued()->for($lead)->create();

    $this->mock(MailSender::class)->shouldNotReceive('send');

    expect(app(Outbox::class)->send($message))->toBe(MessageStatus::Failed)
        ->and($message->refresh()->error)->toBe('The lead has no email address.');
});

test('a box that hit its limit pushes the mail to the next window instead of sending', function () {
    $mailbox = Mailbox::factory()->create(['daily_limit' => 3, 'sent_today' => 3, 'sent_today_on' => today()]);
    $message = Message::factory()->queued()->for($mailbox)->create();

    $this->mock(MailSender::class)->shouldNotReceive('send');

    expect(app(Outbox::class)->send($message))->toBe(MessageStatus::Queued)
        ->and($message->refresh()->send_after?->setTimezone('Europe/Amsterdam')->format('Y-m-d H:i'))->toBe('2026-09-29 09:00');
});

test('the command sends what is due and leaves the rest', function () {
    $due = Message::factory()->queued()->create(['send_after' => now()->subMinute()]);
    $later = Message::factory()->queued()->create(['send_after' => now()->addHour()]);
    $failing = Message::factory()->queued()->for(Lead::factory()->create(['email' => null]))->create(['send_after' => now()]);

    $this->artisan('outreach:send')
        ->expectsOutputToContain('1 sent, 1 failed, 0 pushed to a later window.')
        ->assertSuccessful();

    expect($due->refresh()->status)->toBe(MessageStatus::Sent)
        ->and($later->refresh()->status)->toBe(MessageStatus::Queued)
        ->and($failing->refresh()->status)->toBe(MessageStatus::Failed);
});

test('the command sends nothing outside the window', function () {
    Carbon::setTestNow('2026-09-28 20:00:00'); // 22:00 Amsterdam
    $due = Message::factory()->queued()->create(['send_after' => now()->subHour()]);

    $this->mock(MailSender::class)->shouldNotReceive('send');

    $this->artisan('outreach:send')
        ->expectsOutputToContain('Outside the sending window')
        ->assertSuccessful();

    expect($due->refresh()->status)->toBe(MessageStatus::Queued);
});
