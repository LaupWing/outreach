<?php

use App\Models\Lead;
use App\Models\Offer;
use App\Support\Mail\Placeholders;

test('facts are replaced as a whole and blanks are dropped', function () {
    $lead = Lead::factory()->create(['facts' => ['old' => 'gone']]);

    $this->actingAs($this->user)
        ->patch(route('leads.facts.update', $lead), ['facts' => ['compliment' => ' Mooie site. ', 'first_name' => '', 'empty' => null]])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($lead->refresh()->facts)->toBe(['compliment' => 'Mooie site.']);
});

test('clearing every fact leaves null', function () {
    $lead = Lead::factory()->create(['facts' => ['compliment' => 'x']]);

    $this->actingAs($this->user)->patch(route('leads.facts.update', $lead), ['facts' => []])->assertSessionHasNoErrors();

    expect($lead->refresh()->facts)->toBeNull();
});

test('an offer stores what each tag should say', function () {
    $offer = Offer::factory()->create();

    $this->actingAs($this->user)
        ->patch(route('offers.update', $offer), ['placeholders' => ['compliment' => 'One honest sentence.']])
        ->assertSessionHasNoErrors();

    expect($offer->refresh()->placeholders)->toBe(['compliment' => 'One honest sentence.']);
});

test('tags come from lead fields and facts; unknown ones stay visible', function () {
    $lead = Lead::factory()->make(['company' => 'Fysio Noord', 'city' => 'Haarlem', 'hook' => null, 'facts' => ['compliment' => 'Fijne reviews.']]);
    $text = "Hoi {{first_name}},\n\n{{compliment}} {{ company }} in {{city}}: {{hook_subject}}";

    expect(Placeholders::tagsIn($text))->toBe(['first_name', 'compliment', 'company', 'city', 'hook_subject'])
        ->and(Placeholders::missing($text, $lead))->toBe(['first_name'])
        ->and(Placeholders::fill($text, $lead))->toBe("Hoi {{first_name}},\n\nFijne reviews. Fysio Noord in Haarlem: Jullie website, Fysio Noord");
});
