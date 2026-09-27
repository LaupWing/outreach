<?php

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Offer;
use App\Models\SequenceStep;
use App\Support\Mail\FollowUps;
use App\Support\Mail\Placeholders;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-09-28 10:00:00')); // a Monday, noon in Amsterdam

function sequenced(): Offer
{
    $offer = Offer::factory()->create();
    SequenceStep::factory()->for($offer)->create(['step' => 1, 'subject' => '{{hook_subject}}', 'body' => "Hoi,\n\n{{hook}}"]);
    SequenceStep::factory()->for($offer)->followUp(2)->create(['days_after_previous' => 4, 'subject' => 'Re: {{hook_subject}}', 'body' => 'Nog even hierop terugkomen, {{company}}.']);

    return $offer;
}

test('a due lead gets its next step queued in the same thread, placeholders filled', function () {
    $offer = sequenced();
    $mailbox = Mailbox::factory()->create();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->emailed()->create([
        'company' => 'Tandarts Bos',
        'hook' => 'Copyright staat nog op 2017.',
        'next_action_at' => now()->subHour(),
    ]);
    Message::factory()->for($lead)->for($mailbox)->create(['step' => 1, 'thread_id' => 'thr_first', 'sent_at' => now()->subDays(4)]);

    $result = app(FollowUps::class)->run($this->user);

    $queued = Message::query()->where('status', MessageStatus::Queued)->sole();

    expect($result)->toBe(['queued' => 1, 'finished' => 0, 'waiting' => 0])
        ->and($queued->step)->toBe(2)
        ->and($queued->thread_id)->toBe('thr_first')
        ->and($queued->subject)->toBe('Re: Copyright staat nog op 2017')
        ->and($queued->body)->toBe('Nog even hierop terugkomen, Tandarts Bos.')
        ->and($queued->mailbox_id)->toBe($mailbox->id)
        ->and($queued->send_after)->not->toBeNull();
});

test('leads that replied, are not due yet, or already have mail waiting are skipped', function () {
    $offer = sequenced();
    Mailbox::factory()->create();
    $replied = Lead::factory()->for($offer->niche)->for($offer)->create(['status' => LeadStatus::Replied, 'next_action_at' => now()->subDay()]);
    $later = Lead::factory()->for($offer->niche)->for($offer)->emailed()->create(['next_action_at' => now()->addDay()]);
    $waiting = Lead::factory()->for($offer->niche)->for($offer)->emailed()->create(['next_action_at' => now()->subDay()]);
    Message::factory()->queued()->for($waiting)->create(['step' => 2]);

    $due = app(FollowUps::class)->due($this->user);

    expect($due->modelKeys())->not->toContain($replied->id, $later->id, $waiting->id)
        ->and(app(FollowUps::class)->run($this->user))->toBe(['queued' => 0, 'finished' => 0, 'waiting' => 0]);
});

test('a lead at the end of its sequence is closed off, not mailed again', function () {
    $offer = sequenced();
    Mailbox::factory()->create();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create(['status' => LeadStatus::FollowedUp, 'next_action_at' => now()->subDay()]);
    Message::factory()->for($lead)->create(['step' => 2]);

    $result = app(FollowUps::class)->run($this->user);

    expect($result)->toBe(['queued' => 0, 'finished' => 1, 'waiting' => 0])
        ->and($lead->refresh()->next_action_at)->toBeNull()
        ->and($lead->status)->toBe(LeadStatus::FollowedUp)
        ->and(Message::query()->where('status', MessageStatus::Queued)->count())->toBe(0);
});

test('when every mailbox is full the lead stays due for the next round', function () {
    $offer = sequenced();
    $full = Mailbox::factory()->create(['daily_limit' => 5, 'sent_today' => 5, 'sent_today_on' => today()]);
    $lead = Lead::factory()->for($offer->niche)->for($offer)->emailed()->create(['next_action_at' => now()->subDay()]);
    // Explicitly on the full box: a factory message would otherwise bring a fresh box with room.
    Message::factory()->for($lead)->for($full)->create(['step' => 1]);

    $result = app(FollowUps::class)->run($this->user);

    expect($result)->toBe(['queued' => 0, 'finished' => 0, 'waiting' => 1])
        ->and($lead->refresh()->next_action_at)->not->toBeNull();
});

test('the command runs for every account and sums it up', function () {
    $offer = sequenced();
    Mailbox::factory()->create();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->emailed()->create(['next_action_at' => now()->subDay()]);
    Message::factory()->for($lead)->create(['step' => 1]);

    $this->artisan('outreach:follow-up')
        ->expectsOutputToContain('1 follow-ups queued, 0 sequences finished, 0 waiting for mailbox room.')
        ->assertSuccessful();
});

test('placeholders fall back to a plain subject when there is no hook', function () {
    $lead = Lead::factory()->make(['company' => 'Fysio Noord', 'hook' => null]);

    expect(Placeholders::fill('{{hook_subject}} / {{hook}} / {{company}}', $lead))->toBe('Jullie website, Fysio Noord /  / Fysio Noord');
});
