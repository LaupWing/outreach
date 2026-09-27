<?php

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Offer;
use App\Models\User;

test('an offer can be set on many leads at once', function () {
    $offer = Offer::factory()->create();
    $leads = Lead::factory()->count(3)->create();

    $this->actingAs($this->user)
        ->post(route('leads.bulk'), ['ids' => $leads->modelKeys(), 'action' => 'assign_offer', 'offer_id' => $offer->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Lead::query()->where('offer_id', $offer->id)->count())->toBe(3);
});

test('a status can be set and leads can be deleted in bulk', function () {
    $leads = Lead::factory()->count(2)->create();
    $kept = Lead::factory()->create();

    $this->actingAs($this->user)
        ->post(route('leads.bulk'), ['ids' => $leads->modelKeys(), 'action' => 'set_status', 'status' => 'no'])
        ->assertSessionHasNoErrors();

    expect($leads->first()->refresh()->status)->toBe(LeadStatus::No);

    $this->actingAs($this->user)
        ->post(route('leads.bulk'), ['ids' => $leads->modelKeys(), 'action' => 'delete'])
        ->assertSessionHasNoErrors();

    expect(Lead::query()->pluck('id')->all())->toBe([$kept->id]);
});

test('leads of another account cannot be touched in bulk', function () {
    $foreign = Lead::factory()->create(['user_id' => User::factory()->onboarded()->create()->id]);

    $this->actingAs($this->user)
        ->post(route('leads.bulk'), ['ids' => [$foreign->id], 'action' => 'delete'])
        ->assertSessionHasErrors('ids.0');

    expect($foreign->refresh()->exists)->toBeTrue();
});
