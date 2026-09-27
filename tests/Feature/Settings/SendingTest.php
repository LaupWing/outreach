<?php

use App\Models\User;

test('the sending hours are saved on the account', function () {
    $user = User::factory()->onboarded()->create();

    $this->actingAs($user)
        ->patch(route('sending.update'), [
            'send_timezone' => 'Europe/London',
            'send_from' => 8,
            'send_until' => 18,
            'send_weekdays_only' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user->refresh();

    expect($user->send_timezone)->toBe('Europe/London')
        ->and($user->send_from)->toBe(8)
        ->and($user->send_until)->toBe(18)
        ->and($user->send_weekdays_only)->toBeFalse();
});

test('a window that closes before it opens is refused', function () {
    $user = User::factory()->onboarded()->create();

    $this->actingAs($user)
        ->patch(route('sending.update'), [
            'send_timezone' => 'Europe/Amsterdam',
            'send_from' => 17,
            'send_until' => 9,
            'send_weekdays_only' => true,
        ])
        ->assertSessionHasErrors('send_until');

    expect($user->refresh()->send_from)->toBe(9);
});

test('an unknown timezone is refused', function () {
    $user = User::factory()->onboarded()->create();

    $this->actingAs($user)
        ->patch(route('sending.update'), [
            'send_timezone' => 'Mars/Olympus',
            'send_from' => 9,
            'send_until' => 17,
            'send_weekdays_only' => true,
        ])
        ->assertSessionHasErrors('send_timezone');
});

test('the shared props carry the sending hours', function () {
    $user = User::factory()->onboarded()->create(['send_from' => 10]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.sending.send_from', 10)->where('auth.sending.send_timezone', 'Europe/Amsterdam'));
});
