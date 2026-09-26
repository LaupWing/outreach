<?php

use App\Enums\NicheStatus;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $this->get(route('niches.index'))->assertRedirect(route('login'));
});

test('the niches page renders the niches with what the counts derive from', function () {
    $niche = Niche::factory()->create();
    $offer = Offer::factory()->for($niche)->create();
    $lead = Lead::factory()->for($niche)->for($offer)->create();
    Message::factory()->for($lead)->create();

    $this->actingAs(User::factory()->onboarded()->create())
        ->get(route('niches.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('niches/index')
            ->has('niches', 1, fn (AssertableInertia $item) => $item
                ->where('id', $niche->id)
                ->where('status', $niche->status->value)
                ->hasAll(['name', 'why', 'findings']))
            ->has('leads', 1, fn (AssertableInertia $item) => $item
                ->where('niche_id', $niche->id)
                ->hasAll(['id', 'company', 'email', 'offer_id', 'status']))
            ->has('messages', 1, fn (AssertableInertia $item) => $item
                ->hasAll(['id', 'lead_id', 'sent_at', 'reply']))
            ->has('offers', 1));
});

test('a niche can be added', function () {
    $this->actingAs(User::factory()->onboarded()->create())
        ->from(route('niches.index'))
        ->post(route('niches.store'), [
            'name' => 'Tandartsen',
            'status' => 'testing',
            'why' => 'Money and pain.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('niches.index'));

    $this->assertDatabaseHas('niches', [
        'name' => 'Tandartsen',
        'status' => NicheStatus::Testing->value,
        'why' => 'Money and pain.',
    ]);
});

test('a niche needs a name', function () {
    $this->actingAs(User::factory()->onboarded()->create())
        ->post(route('niches.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect(Niche::count())->toBe(0);
});

test('a niche can be updated with only a status', function () {
    $niche = Niche::factory()->create(['name' => 'Kappers']);

    $this->actingAs(User::factory()->onboarded()->create())
        ->from(route('niches.index'))
        ->patch(route('niches.update', $niche), ['status' => 'proven'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('niches.index'));

    $niche->refresh();

    expect($niche->status)->toBe(NicheStatus::Proven)
        ->and($niche->name)->toBe('Kappers');
});

test('a niche rejects an unknown status', function () {
    $niche = Niche::factory()->create();

    $this->actingAs(User::factory()->onboarded()->create())
        ->patch(route('niches.update', $niche), ['status' => 'golden'])
        ->assertSessionHasErrors('status');
});

test('a niche can be deleted', function () {
    $niche = Niche::factory()->create();

    $this->actingAs(User::factory()->onboarded()->create())
        ->from(route('niches.index'))
        ->delete(route('niches.destroy', $niche))
        ->assertRedirect(route('niches.index'));

    $this->assertModelMissing($niche);
});
