<?php

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are sent to the login page', function () {
    $this->get(route('inbox.index'))->assertRedirect(route('login'));
});

test('the inbox sorts leads into replies, due follow-ups and bounces', function () {
    $replied = Lead::factory()->create(['status' => LeadStatus::Replied]);
    Message::factory()->for($replied)->replied()->create();
    $due = Lead::factory()->emailed()->create(['next_action_at' => now()->subHour()]);
    $bounced = Lead::factory()->create(['status' => LeadStatus::Undeliverable]);
    Lead::factory()->emailed()->create(['next_action_at' => now()->addDays(2)]);
    Lead::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inbox/index')
            ->has('leads', 3)
            ->has('queues.replies', 1, fn (Assert $row) => $row->where('id', $replied->id)->etc())
            ->has('queues.due', 1, fn (Assert $row) => $row->where('id', $due->id)->etc())
            ->has('queues.bounces', 1, fn (Assert $row) => $row->where('id', $bounced->id)->etc())
            ->has('messages', 1, fn (Assert $row) => $row
                ->where('lead_id', $replied->id)
                ->where('reply.body', 'Stuur maar een voorbeeld.')
                ->etc())
            ->has('mailboxes')
            ->has('niches')
            ->has('offers')
            ->has('steps'));
});
