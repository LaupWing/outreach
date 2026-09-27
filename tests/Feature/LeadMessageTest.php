<?php

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Offer;
use App\Models\SequenceStep;
use Illuminate\Support\Carbon;

test('sending a step queues it on a mailbox with room; the lead moves once it goes out', function () {
    Carbon::setTestNow('2026-09-28 10:00:00'); // Monday noon in Amsterdam

    $offer = Offer::factory()->create();
    SequenceStep::factory()->for($offer)->create(['step' => 1]);
    SequenceStep::factory()->for($offer)->followUp(2)->create(['days_after_previous' => 4]);
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create();

    $full = Mailbox::factory()->create(['daily_limit' => 10, 'sent_today' => 10, 'sent_today_on' => today()]);
    $paused = Mailbox::factory()->paused()->create();
    $open = Mailbox::factory()->create(['daily_limit' => 40, 'sent_today' => 12, 'sent_today_on' => today()]);

    $this->actingAs($this->user)
        ->post(route('leads.messages.store', $lead), [
            'subject' => 'Jullie site op een telefoon',
            'body' => 'Hoi,',
            'mailbox_id' => null,
            'step' => 1,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $message = Message::query()->sole();

    expect($message->mailbox_id)->toBe($open->id)
        ->and($message->step)->toBe(1)
        ->and($message->status)->toBe(MessageStatus::Queued)
        ->and($message->sent_at)->toBeNull()
        ->and($message->send_after?->greaterThanOrEqualTo(now()))->toBeTrue()
        ->and($message->thread_id)->toMatch('/^thr_[a-z0-9]{5}$/')
        ->and($open->refresh()->sent_today)->toBe(12)
        ->and($full->refresh()->sent_today)->toBe(10)
        ->and($paused->refresh()->sent_today)->toBe(0);

    // Nothing on the lead until the sender has actually handed it over.
    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::New)
        ->and($lead->last_contact_at)->toBeNull();

    // The slot carries some jitter, so the tick runs a little later in the window.
    Carbon::setTestNow('2026-09-28 11:00:00');
    $this->artisan('outreach:send')->assertSuccessful();

    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Emailed)
        ->and($lead->last_contact_at?->toDateTimeString())->toBe('2026-09-28 11:00:00')
        ->and($lead->next_action_at?->toDateTimeString())->toBe('2026-10-02 11:00:00')
        ->and($open->refresh()->sent_today)->toBe(13);
});

test('a later step marks the lead as followed up and ends the plan after the last step', function () {
    Carbon::setTestNow('2026-09-28 10:00:00');
    $offer = Offer::factory()->create();
    SequenceStep::factory()->for($offer)->create(['step' => 1]);
    SequenceStep::factory()->for($offer)->followUp(2)->create();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->emailed()->create();
    $mailbox = Mailbox::factory()->create();

    $this->actingAs($this->user)
        ->post(route('leads.messages.store', $lead), [
            'subject' => 'Re: Jullie site',
            'body' => 'Nog even hierop terugkomen.',
            'mailbox_id' => $mailbox->id,
            'step' => 2,
        ])
        ->assertSessionHasNoErrors();

    // The slot carries some jitter, so the tick runs a little later in the window.
    Carbon::setTestNow('2026-09-28 11:00:00');
    $this->artisan('outreach:send')->assertSuccessful();
    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::FollowedUp)
        ->and($lead->next_action_at)->toBeNull()
        ->and(Message::query()->sole()->mailbox_id)->toBe($mailbox->id);
});

test('a free-form mail only records the contact', function () {
    Carbon::setTestNow('2026-09-28 10:00:00');
    $lead = Lead::factory()->create();
    Mailbox::factory()->create();

    $this->actingAs($this->user)
        ->post(route('leads.messages.store', $lead), [
            'subject' => 'Even iets anders',
            'body' => 'Hoi,',
            'mailbox_id' => null,
            'step' => null,
        ])
        ->assertSessionHasNoErrors();

    // The slot carries some jitter, so the tick runs a little later in the window.
    Carbon::setTestNow('2026-09-28 11:00:00');
    $this->artisan('outreach:send')->assertSuccessful();
    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::New)
        ->and($lead->last_contact_at)->not->toBeNull()
        ->and($lead->next_action_at)->toBeNull()
        ->and(Message::query()->sole()->step)->toBe(0);
});

test('sending fails when every mailbox is full or paused', function () {
    $lead = Lead::factory()->create();
    Mailbox::factory()->create(['daily_limit' => 5, 'sent_today' => 5, 'sent_today_on' => today()]);
    Mailbox::factory()->paused()->create();

    $this->actingAs($this->user)
        ->post(route('leads.messages.store', $lead), [
            'subject' => 'Hoi',
            'body' => 'Hoi,',
            'mailbox_id' => null,
            'step' => 1,
        ])
        ->assertSessionHasErrors('mailbox_id');

    expect(Message::query()->count())->toBe(0)
        ->and($lead->refresh()->status)->toBe(LeadStatus::New);
});

test('a subject and body are required', function () {
    $lead = Lead::factory()->create();
    Mailbox::factory()->create();

    $this->actingAs($this->user)
        ->post(route('leads.messages.store', $lead), ['subject' => '', 'body' => ''])
        ->assertSessionHasErrors(['subject', 'body']);
});

test('a reply lands in the same thread from the same mailbox', function () {
    Carbon::setTestNow('2026-09-28 10:00:00');

    $original = Message::factory()->replied()->create(['subject' => 'Wix en laadtijd', 'step' => 2]);

    $this->actingAs($this->user)
        ->post(route('messages.reply.store', $original), ['body' => 'Komt eraan.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $reply = Message::query()->whereKeyNot($original->id)->sole();

    expect($reply->lead_id)->toBe($original->lead_id)
        ->and($reply->mailbox_id)->toBe($original->mailbox_id)
        ->and($reply->thread_id)->toBe($original->thread_id)
        ->and($reply->step)->toBe(2)
        ->and($reply->subject)->toBe('Re: Wix en laadtijd')
        ->and($reply->body)->toBe('Komt eraan.')
        ->and($reply->status)->toBe(MessageStatus::Queued)
        ->and($reply->send_after?->toDateTimeString())->toBe('2026-09-28 10:00:00')
        ->and($reply->reply)->toBeNull();

    // The slot carries some jitter, so the tick runs a little later in the window.
    Carbon::setTestNow('2026-09-28 11:00:00');
    $this->artisan('outreach:send')->assertSuccessful();

    expect($reply->refresh()->status)->toBe(MessageStatus::Sent)
        ->and($original->lead->refresh()->last_contact_at?->toDateTimeString())->toBe('2026-09-28 11:00:00');
});

test('an empty reply is refused', function () {
    $original = Message::factory()->replied()->create();

    $this->actingAs($this->user)
        ->post(route('messages.reply.store', $original), ['body' => ''])
        ->assertSessionHasErrors('body');

    expect(Message::query()->count())->toBe(1);
});
