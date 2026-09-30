<?php

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Mcp\Servers\OutreachServer;
use App\Mcp\Tools\CreateLeads;
use App\Mcp\Tools\SendMails;
use App\Mcp\Tools\UpdateLeads;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Support\Blocklist;
use App\Support\Mail\InboxCheck;
use App\Support\Mail\IncomingMail;
use App\Support\Mail\MailboxReader;
use App\Support\Mail\MailSender;
use App\Support\Mail\Outbox;
use Carbon\CarbonImmutable;

test('do_not_contact blocks the address and domain, sets no and cancels waiting mail', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'info@waterland.nl', 'website' => 'waterland.nl']);
    Message::factory()->queued()->for($lead)->create();

    OutreachServer::actingAs($this->user)
        ->tool(UpdateLeads::class, ['leads' => [['lead_id' => $lead->id, 'do_not_contact' => true, 'note' => 'Graag uit het bestand.']]])
        ->assertOk();

    expect($lead->refresh()->status)->toBe(LeadStatus::No)
        ->and($lead->next_action_at)->toBeNull()
        ->and(Message::query()->where('status', MessageStatus::Queued)->count())->toBe(0)
        ->and($this->user->blockedContacts()->pluck('domain')->filter()->values()->all())->toBe(['waterland.nl']);
});

test('a blocked lead is refused by every send route', function () {
    Mailbox::factory()->create();
    $lead = Lead::factory()->create(['email' => 'info@waterland.nl']);
    Blocklist::block($lead);
    $lead->update(['status' => LeadStatus::Emailed]);

    OutreachServer::actingAs($this->user)
        ->tool(SendMails::class, ['mails' => [['lead_id' => $lead->id, 'subject' => 'Hoi', 'body' => 'x']]])
        ->assertOk()->assertSee('asked not to be mailed');

    $this->post(route('leads.messages.store', $lead), ['subject' => 'Hoi', 'body' => 'x'])->assertSessionHasErrors('body');

    // Even mail that got into the outbox some other way does not leave.
    $message = Message::factory()->queued()->for($lead)->create();
    $this->mock(MailSender::class)->shouldNotReceive('send');

    expect(app(Outbox::class)->send($message))->toBe(MessageStatus::Failed)
        ->and($message->refresh()->error)->toBe('This address asked not to be mailed.');
});

test('a new lead from a blocked domain starts as no, a gmail address blocks only itself', function () {
    Blocklist::block(Lead::factory()->create(['email' => 'info@waterland.nl']));
    Blocklist::block(Lead::factory()->create(['email' => 'piet@gmail.com']));

    OutreachServer::actingAs($this->user)->tool(CreateLeads::class, ['niche' => 'Keuring', 'leads' => [
        ['company' => 'Waterland Nieuw', 'email' => 'sales@waterland.nl'],
        ['company' => 'Andere Gmail', 'email' => 'jan@gmail.com'],
    ]])->assertOk();

    expect($this->user->leads()->where('company', 'Waterland Nieuw')->first()->status)->toBe(LeadStatus::No)
        ->and($this->user->leads()->where('company', 'Andere Gmail')->first()->status)->toBe(LeadStatus::New);
});

test('a reply asking to be removed blocks the lead by itself', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'info@waterland.nl']);
    $mailbox = Mailbox::factory()->create();
    Message::factory()->for($lead)->for($mailbox)->create(['message_id' => '<one@x>']);

    $this->instance(MailboxReader::class, tap(Mockery::mock(MailboxReader::class), function ($fake) use ($mailbox): void {
        $fake->shouldReceive('newMail')->andReturnUsing(fn (Mailbox $box) => $box->is($mailbox)
            ? [new IncomingMail(3, 'info@waterland.nl', 'Re: Hoi', 'Geen interesse, graag uit het mailbestand halen.', '<r@w>', ['<one@x>'], CarbonImmutable::now())]
            : []);
    }));

    app(InboxCheck::class)->run($mailbox);

    expect($lead->refresh()->status)->toBe(LeadStatus::No)
        ->and(Blocklist::blocks($this->user, $lead))->toBeTrue();
});

test('the bulk action never mails the selected leads again', function () {
    $leads = Lead::factory()->count(2)->create(['email' => fn () => fake()->unique()->companyEmail()]);

    $this->post(route('leads.bulk'), ['ids' => $leads->modelKeys(), 'action' => 'block'])->assertSessionHasNoErrors();

    expect($leads->every(fn ($lead) => Blocklist::blocks($this->user, $lead->refresh())))->toBeTrue();
});
