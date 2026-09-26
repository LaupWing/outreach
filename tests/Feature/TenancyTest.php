<?php

use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Account B must never see, touch or count anything that belongs to account A.
 * $this->user is account A and owns everything created before the switch.
 */
function otherAccount(): User
{
    return User::factory()->onboarded()->create();
}

test('another account sees none of the rows on any page', function () {
    $niche = Niche::factory()->create();
    Offer::factory()->create(['niche_id' => $niche->id]);
    Lead::factory()->count(3)->create(['niche_id' => $niche->id]);
    Message::factory()->create();
    ScrapeRun::factory()->create(['niche_id' => $niche->id]);

    $this->actingAs(otherAccount());

    $this->get(route('leads.index'))->assertInertia(fn (Assert $page) => $page->has('leads', 0)->has('niches', 0));
    $this->get(route('niches.index'))->assertInertia(fn (Assert $page) => $page->has('niches', 0));
    $this->get(route('offers.index'))->assertInertia(fn (Assert $page) => $page->has('offers', 0));
    $this->get(route('messages.index'))->assertInertia(fn (Assert $page) => $page->has('messages', 0));
    $this->get(route('scrape.index'))->assertInertia(fn (Assert $page) => $page->has('runs', 0)->where('usage.used', 0));
    $this->get(route('mailboxes.index'))->assertInertia(fn (Assert $page) => $page->has('mailboxes', 1)); // only its own default box
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('leads', 0)->has('messages', 0));
});

test('another account cannot open, change or delete a row by id', function () {
    $lead = Lead::factory()->create();
    $mailbox = Mailbox::factory()->create();

    $this->actingAs(otherAccount());

    $this->patch(route('leads.update', $lead), ['company' => 'Stolen'])->assertForbidden();
    $this->delete(route('leads.destroy', $lead))->assertForbidden();
    $this->post(route('leads.notes.store', $lead), ['body' => 'hi'])->assertForbidden();
    $this->patch(route('mailboxes.update', $mailbox), ['daily_limit' => 1])->assertForbidden();
    $this->post(route('mailboxes.test', $mailbox))->assertForbidden();

    expect($lead->fresh()->company)->not->toBe('Stolen')
        ->and(Lead::query()->whereKey($lead->id)->exists())->toBeTrue();
});

test('another account cannot attach its rows to somebody else\'s niche or mailbox', function () {
    $niche = Niche::factory()->create();
    $mailbox = Mailbox::factory()->create();

    $this->actingAs(otherAccount());

    $this->post(route('leads.store'), ['company' => 'X', 'niche_id' => $niche->id, 'status' => 'new'])
        ->assertSessionHasErrors('niche_id');
    $this->post(route('scrape.store'), ['query' => 'a', 'place' => 'b', 'niche_id' => $niche->id, 'pages' => 1])
        ->assertSessionHasErrors('niche_id');

    $lead = Lead::factory()->create();

    $this->post(route('leads.messages.store', $lead), ['subject' => 's', 'body' => 'b', 'mailbox_id' => $mailbox->id])
        ->assertSessionHasErrors('mailbox_id');
});

test('search only returns the signed-in account\'s rows', function () {
    Lead::factory()->create(['company' => 'Tandarts Geheim']);

    $this->actingAs(otherAccount());

    $this->getJson(route('search', ['q' => 'geheim']))->assertOk()->assertJsonCount(0, 'leads');
});

test('new rows are stamped with the signed-in account', function () {
    $this->post(route('niches.store'), ['name' => 'Kappers', 'status' => 'idea']);

    expect(Niche::query()->where('name', 'Kappers')->sole()->user_id)->toBe($this->user->id);
});

test('the same address may be used by two accounts', function () {
    Mailbox::factory()->create(['address' => 'shared@example.com']);

    $this->actingAs(otherAccount());

    $this->post(route('mailboxes.store'), [
        'address' => 'shared@example.com',
        'imap_host' => 'imap.gmail.com', 'imap_port' => 993,
        'smtp_host' => 'smtp.gmail.com', 'smtp_port' => 587,
        'password' => 'x', 'daily_limit' => 10, 'warm_up' => false,
    ])->assertSessionHasNoErrors();
});
