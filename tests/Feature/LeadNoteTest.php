<?php

use App\Models\Lead;
use App\Models\User;

test('a note can be typed on a lead', function () {
    $lead = Lead::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.notes.store', $lead), ['body' => 'Belde: de praktijkmanager beslist.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($lead->notes()->sole()->body)->toBe('Belde: de praktijkmanager beslist.');
});

test('an empty note is refused', function () {
    $lead = Lead::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('leads.notes.store', $lead), ['body' => ''])
        ->assertSessionHasErrors('body');

    expect($lead->notes()->count())->toBe(0);
});
