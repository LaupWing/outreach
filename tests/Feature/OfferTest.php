<?php

use App\Enums\NicheStatus;
use App\Enums\OfferStatus;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\SequenceStep;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $this->get(route('offers.index'))->assertRedirect(route('login'));
});

test('the offers page renders the offers with their steps and what the counts derive from', function () {
    $offer = Offer::factory()->create();
    SequenceStep::factory()->for($offer)->create();
    SequenceStep::factory()->for($offer)->followUp(2)->create();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create();
    Message::factory()->for($lead)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('offers.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('offers/index')
            ->has('offers', 1, fn (AssertableInertia $item) => $item
                ->where('id', $offer->id)
                ->where('niche_id', $offer->niche_id)
                ->where('status', $offer->status->value)
                ->hasAll(['name', 'description']))
            ->has('niches', 1)
            ->has('leads', 1)
            ->has('messages', 1)
            ->has('steps', 2, fn (AssertableInertia $item) => $item
                ->where('offer_id', $offer->id)
                ->where('step', 1)
                ->hasAll(['id', 'days_after_previous', 'subject', 'body'])));
});

test('an offer can be added to an existing niche with its first mail', function () {
    $niche = Niche::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('offers.index'))
        ->post(route('offers.store'), [
            'name' => 'Nieuwe website in 2 weken',
            'niche_id' => $niche->id,
            'description' => 'A site, fast.',
            'first_step' => ['subject' => '{{hook_subject}}', 'body' => 'Hoi,\n\n{{hook}}'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('offers.index'));

    $offer = Offer::query()->sole();

    expect($offer->niche_id)->toBe($niche->id)
        ->and($offer->status)->toBe(OfferStatus::Idea)
        ->and($offer->steps)->toHaveCount(1)
        ->and($offer->steps->first()->step)->toBe(1)
        ->and($offer->steps->first()->subject)->toBe('{{hook_subject}}');
});

test('an offer can be added to a new niche without a first mail', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('offers.store'), [
            'name' => 'Gratis snelheidscheck',
            'new_niche' => 'Makelaars',
        ])
        ->assertSessionHasNoErrors();

    $offer = Offer::query()->sole();

    expect($offer->niche->name)->toBe('Makelaars')
        ->and($offer->niche->status)->toBe(NicheStatus::Idea)
        ->and($offer->steps)->toHaveCount(0);
});

test('an offer needs a name and a niche', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('offers.store'), ['name' => ''])
        ->assertSessionHasErrors(['name', 'niche_id', 'new_niche']);

    expect(Offer::count())->toBe(0);
});

test('an offer can be updated with only a status', function () {
    $offer = Offer::factory()->create(['name' => 'Menukaart online']);

    $this->actingAs(User::factory()->create())
        ->from(route('offers.index'))
        ->patch(route('offers.update', $offer), ['status' => 'active'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('offers.index'));

    $offer->refresh();

    expect($offer->status)->toBe(OfferStatus::Active)
        ->and($offer->name)->toBe('Menukaart online');
});

test('an offer can be moved to a new niche', function () {
    $offer = Offer::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('offers.update', $offer), ['new_niche' => 'Accountants'])
        ->assertSessionHasNoErrors();

    expect($offer->refresh()->niche->name)->toBe('Accountants')
        ->and(Niche::count())->toBe(2);
});

test('an offer can be deleted', function () {
    $offer = Offer::factory()->create();
    $step = SequenceStep::factory()->for($offer)->create();

    $this->actingAs(User::factory()->create())
        ->from(route('offers.index'))
        ->delete(route('offers.destroy', $offer))
        ->assertRedirect(route('offers.index'));

    $this->assertModelMissing($offer);
    $this->assertModelMissing($step);
});
