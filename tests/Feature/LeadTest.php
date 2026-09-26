<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are sent to the login page', function () {
    $this->get(route('leads.index'))->assertRedirect(route('login'));
});

test('the leads page renders with everything the table and panel read', function () {
    $niche = Niche::factory()->create();
    $offer = Offer::factory()->for($niche)->create();
    $lead = Lead::factory()->for($niche)->for($offer)->create();
    LeadNote::factory()->for($lead)->create(['body' => 'Belde, terug in oktober.']);
    ScrapeRun::factory()->for($niche)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('leads.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/index')
            ->has('leads', 1, fn (Assert $row) => $row
                ->where('id', $lead->id)
                ->where('status', 'new')
                ->where('source', 'places')
                ->has('notes', 1, fn (Assert $note) => $note
                    ->where('body', 'Belde, terug in oktober.')
                    ->etc())
                ->etc())
            ->has('niches', 1)
            ->has('offers', 1)
            ->has('mailboxes')
            ->has('messages')
            ->has('steps')
            ->has('scrapeRuns', 1, fn (Assert $run) => $run->hasAll(['id', 'query', 'place'])));
});

test('a lead can be added by hand in an existing niche', function () {
    $niche = Niche::factory()->create();
    $offer = Offer::factory()->for($niche)->create();

    $this->actingAs(User::factory()->create())
        ->from(route('leads.index'))
        ->post(route('leads.store'), [
            'company' => 'Tandartspraktijk De Molen',
            'niche_id' => $niche->id,
            'offer_id' => $offer->id,
            'email' => 'info@demolen.nl',
            'website' => 'https://demolen.nl/',
            'status' => 'new',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leads.index'));

    $lead = Lead::query()->sole();

    expect($lead->company)->toBe('Tandartspraktijk De Molen')
        ->and($lead->website)->toBe('demolen.nl')
        ->and($lead->source)->toBe(LeadSource::Manual)
        ->and($lead->offer_id)->toBe($offer->id);
});

test('a lead can be added with a niche typed on the spot', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('leads.store'), [
            'company' => 'Fysio Noord',
            'new_niche' => 'Fysiotherapeuten',
            'status' => 'emailed',
        ])
        ->assertSessionHasNoErrors();

    $lead = Lead::query()->sole();

    expect($lead->niche->name)->toBe('Fysiotherapeuten')
        ->and($lead->status)->toBe(LeadStatus::Emailed);
});

test('a new lead needs a company, a niche, a valid email and a starting status', function () {
    $otherNiche = Niche::factory()->create();
    $niche = Niche::factory()->create();
    $foreignOffer = Offer::factory()->for($otherNiche)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.store'), [
            'company' => '',
            'niche_id' => $niche->id,
            'offer_id' => $foreignOffer->id,
            'email' => 'not-an-email',
            'status' => 'customer',
        ])
        ->assertSessionHasErrors(['company', 'offer_id', 'email', 'status']);

    $this->actingAs(User::factory()->create())
        ->post(route('leads.store'), ['company' => 'Zonder niche', 'status' => 'new'])
        ->assertSessionHasErrors(['niche_id', 'new_niche']);

    expect(Lead::query()->count())->toBe(0);
});

test('a lead can be moved to any status and given an offer of its niche', function () {
    $lead = Lead::factory()->create();
    $offer = Offer::factory()->for($lead->niche)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('leads.update', $lead), ['status' => 'customer', 'offer_id' => $offer->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($lead->refresh()->status)->toBe(LeadStatus::Customer)
        ->and($lead->offer_id)->toBe($offer->id);
});

test('a lead cannot take an offer from another niche', function () {
    $lead = Lead::factory()->create();
    $offer = Offer::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('leads.update', $lead), ['offer_id' => $offer->id])
        ->assertSessionHasErrors('offer_id');

    expect($lead->refresh()->offer_id)->toBeNull();
});

test('a lead can be removed', function () {
    $lead = Lead::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('leads.destroy', $lead))
        ->assertRedirect();

    $this->assertModelMissing($lead);
});
