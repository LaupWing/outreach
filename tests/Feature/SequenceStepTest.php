<?php

use App\Models\Offer;
use App\Models\SequenceStep;

/**
 * An offer with a three-mail sequence; returns [offer, steps keyed by step number].
 *
 * @return array{0: Offer, 1: array<int, SequenceStep>}
 */
function sequenceOfThree(): array
{
    $offer = Offer::factory()->create();

    return [$offer, [
        1 => SequenceStep::factory()->for($offer)->create(),
        2 => SequenceStep::factory()->for($offer)->followUp(2)->create(),
        3 => SequenceStep::factory()->for($offer)->followUp(3)->create(),
    ]];
}

test('guests are redirected to the login page', function () {
    auth()->logout();
    $offer = Offer::factory()->create();

    $this->post(route('offers.steps.store', $offer), ['subject' => 'Hi', 'body' => 'There'])
        ->assertRedirect(route('login'));
});

test('a step is appended as the next number', function () {
    [$offer] = sequenceOfThree();

    $this->actingAs($this->user)
        ->from(route('offers.index'))
        ->post(route('offers.steps.store', $offer), [
            'subject' => 'Re: {{hook_subject}}',
            'body' => 'Laatste keer.',
            'days_after_previous' => 5,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('offers.index'));

    $latest = $offer->steps->last();

    expect($latest->step)->toBe(4)
        ->and($latest->days_after_previous)->toBe(5)
        ->and($latest->subject)->toBe('Re: {{hook_subject}}');
});

test('a step needs a subject and a body', function () {
    $offer = Offer::factory()->create();

    $this->actingAs($this->user)
        ->post(route('offers.steps.store', $offer), ['subject' => '', 'body' => ''])
        ->assertSessionHasErrors(['subject', 'body']);

    expect($offer->steps()->count())->toBe(0);
});

test('a step can be updated', function () {
    [, $steps] = sequenceOfThree();

    $this->actingAs($this->user)
        ->patch(route('steps.update', $steps[2]), [
            'subject' => 'New subject',
            'body' => 'New body',
            'days_after_previous' => 7,
        ])
        ->assertSessionHasNoErrors();

    $steps[2]->refresh();

    expect($steps[2]->subject)->toBe('New subject')
        ->and($steps[2]->body)->toBe('New body')
        ->and($steps[2]->days_after_previous)->toBe(7)
        ->and($steps[2]->step)->toBe(2);
});

test('deleting a step renumbers the ones after it', function () {
    [$offer, $steps] = sequenceOfThree();

    $this->actingAs($this->user)
        ->delete(route('steps.destroy', $steps[1]))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($steps[1]);

    expect($offer->steps()->pluck('step', 'id')->all())->toBe([
        $steps[2]->id => 1,
        $steps[3]->id => 2,
    ]);
});

test('steps can be reordered', function () {
    [$offer, $steps] = sequenceOfThree();

    $this->actingAs($this->user)
        ->put(route('offers.steps.reorder', $offer), [
            'order' => [$steps[3]->id, $steps[1]->id, $steps[2]->id],
        ])
        ->assertSessionHasNoErrors();

    expect($offer->steps()->orderBy('step')->pluck('id')->all())
        ->toBe([$steps[3]->id, $steps[1]->id, $steps[2]->id]);
});

test('a reorder must list every step of the offer exactly once', function () {
    [$offer, $steps] = sequenceOfThree();
    $foreign = SequenceStep::factory()->create();

    $user = $this->user;

    $this->actingAs($user)
        ->put(route('offers.steps.reorder', $offer), ['order' => [$steps[1]->id, $steps[2]->id]])
        ->assertSessionHasErrors('order');

    $this->actingAs($user)
        ->put(route('offers.steps.reorder', $offer), ['order' => [$steps[1]->id, $steps[2]->id, $foreign->id]])
        ->assertSessionHasErrors('order');

    expect($offer->steps()->orderBy('step')->pluck('id')->all())
        ->toBe([$steps[1]->id, $steps[2]->id, $steps[3]->id]);
});
