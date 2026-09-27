<?php

use App\Enums\NicheStatus;
use App\Enums\OfferStatus;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\SequenceStep;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    auth()->logout();
    $this->get(route('offers.index'))->assertRedirect(route('login'));
});

test('the offers page renders the offers with their steps and what the counts derive from', function () {
    $offer = Offer::factory()->create();
    SequenceStep::factory()->for($offer)->create();
    SequenceStep::factory()->for($offer)->followUp(2)->create();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create();
    Message::factory()->for($lead)->create();

    $this->actingAs($this->user)
        ->get(route('offers.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('offers/index')
            ->has('offers', 1, fn (AssertableInertia $item) => $item
                ->where('id', $offer->id)
                ->where('niche_id', $offer->niche_id)
                ->where('status', $offer->status->value)
                ->hasAll(['name', 'description', 'placeholders', 'auto_follow_up']))
            ->has('niches', 1)
            ->has('leads', 1)
            ->has('messages', 1)
            ->has('steps', 2, fn (AssertableInertia $item) => $item
                ->where('offer_id', $offer->id)
                ->where('step', 1)
                ->hasAll(['id', 'days_after_previous', 'subject', 'body'])));
});

test('an offer can be added to an existing niche with its whole sequence and tag explanations', function () {
    $niche = Niche::factory()->create();

    $this->actingAs($this->user)
        ->from(route('offers.index'))
        ->post(route('offers.store'), [
            'name' => 'Nieuwe website in 2 weken',
            'niche_id' => $niche->id,
            'description' => 'A site, fast.',
            'steps' => [
                ['subject' => '{{hook_subject}}', 'body' => "Hoi {{first_name}},\n\n{{compliment}} {{hook}}"],
                ['subject' => 'Re: {{hook_subject}}', 'body' => 'Nog even hierop terugkomen.', 'days_after_previous' => 4],
            ],
            'placeholders' => ['first_name' => 'Owner first name', 'compliment' => 'One honest sentence.', 'hook' => ''],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('offers.index'));

    $offer = Offer::query()->sole();

    expect($offer->niche_id)->toBe($niche->id)
        ->and($offer->status)->toBe(OfferStatus::Idea)
        ->and($offer->placeholders)->toBe(['first_name' => 'Owner first name', 'compliment' => 'One honest sentence.'])
        ->and($offer->steps)->toHaveCount(2)
        ->and($offer->steps->pluck('step')->all())->toBe([1, 2])
        ->and($offer->steps->pluck('days_after_previous')->all())->toBe([0, 4])
        ->and($offer->steps->first()->subject)->toBe('{{hook_subject}}');
});

test('a custom tag in the mails needs an explanation; built-ins do not', function () {
    $niche = Niche::factory()->create();

    $this->actingAs($this->user)
        ->post(route('offers.store'), [
            'name' => 'Nieuwe website',
            'niche_id' => $niche->id,
            'steps' => [['subject' => '{{hook_subject}}', 'body' => 'Hoi, {{compliment}} {{company}}']],
            'placeholders' => ['compliment' => '  '],
        ])
        ->assertSessionHasErrors(['placeholders.compliment'])
        ->assertSessionDoesntHaveErrors(['placeholders.company', 'placeholders.hook_subject']);

    expect(Offer::query()->count())->toBe(0);
});

test('an offer can be added to a new niche without a first mail', function () {
    $this->actingAs($this->user)
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
    $this->actingAs($this->user)
        ->post(route('offers.store'), ['name' => ''])
        ->assertSessionHasErrors(['name', 'niche_id', 'new_niche']);

    expect(Offer::count())->toBe(0);
});

test('an offer can be updated with only a status', function () {
    $offer = Offer::factory()->create(['name' => 'Menukaart online']);

    $this->actingAs($this->user)
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

    $this->actingAs($this->user)
        ->patch(route('offers.update', $offer), ['new_niche' => 'Accountants'])
        ->assertSessionHasNoErrors();

    expect($offer->refresh()->niche->name)->toBe('Accountants')
        ->and(Niche::count())->toBe(2);
});

test('an offer can be deleted', function () {
    $offer = Offer::factory()->create();
    $step = SequenceStep::factory()->for($offer)->create();

    $this->actingAs($this->user)
        ->from(route('offers.index'))
        ->delete(route('offers.destroy', $offer))
        ->assertRedirect(route('offers.index'));

    $this->assertModelMissing($offer);
    $this->assertModelMissing($step);
});

test('the offers page carries the tag explanations and the auto follow-up switch', function () {
    Offer::factory()->create(['placeholders' => ['aanhef' => 'Voornaam'], 'auto_follow_up' => true]);

    $this->actingAs($this->user)
        ->get(route('offers.index'))
        ->assertInertia(fn ($page) => $page
            ->where('offers.0.placeholders', ['aanhef' => 'Voornaam'])
            ->where('offers.0.auto_follow_up', true));
});
