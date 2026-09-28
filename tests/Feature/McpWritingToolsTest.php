<?php

use App\Enums\LeadStatus;
use App\Enums\MessageStatus;
use App\Mcp\Resources\CatalogApp;
use App\Mcp\Resources\LeadCardApp;
use App\Mcp\Resources\LeadListApp;
use App\Mcp\Resources\MailCardApp;
use App\Mcp\Resources\StatsApp;
use App\Mcp\Servers\OutreachServer;
use App\Mcp\Tools\CheckInbox;
use App\Mcp\Tools\CreateLeads;
use App\Mcp\Tools\CreateOffers;
use App\Mcp\Tools\LeadsContext;
use App\Mcp\Tools\ListLeads;
use App\Mcp\Tools\ListMailboxes;
use App\Mcp\Tools\ListNiches;
use App\Mcp\Tools\ListOffers;
use App\Mcp\Tools\PreviewMails;
use App\Mcp\Tools\SendDrafts;
use App\Mcp\Tools\SendMails;
use App\Mcp\Tools\SendSteps;
use App\Mcp\Tools\Stats;
use App\Mcp\Tools\UpdateLeads;
use App\Mcp\Tools\UpdateMails;
use App\Mcp\Tools\UpdateNiches;
use App\Mcp\Tools\UpdateOffers;
use App\Mcp\Tools\WhoNeedsFollowUp;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Offer;
use App\Models\SequenceStep;
use App\Models\User;
use App\Support\Enrichment\Enricher;
use App\Support\Enrichment\SiteReader;
use App\Support\Mail\IncomingMail;
use App\Support\Mail\MailboxReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Server\Testing\TestResponse;

beforeEach(fn () => Carbon::setTestNow('2026-09-28 10:00:00')); // Monday noon in Amsterdam

/**
 * The structured content of a tool response; the test response only asserts on it.
 *
 * @return array<string, mixed>
 */
function structured(TestResponse $response): array
{
    return (fn () => $this->structuredContent())->call($response);
}

function offerWithTags(): Offer
{
    $offer = Offer::factory()->create(['placeholders' => ['compliment' => 'One honest sentence about their site.']]);
    SequenceStep::factory()->for($offer)->create(['step' => 1, 'subject' => '{{hook_subject}}', 'body' => "Hoi {{first_name}},\n\n{{compliment}}\n\n{{hook}}"]);
    SequenceStep::factory()->for($offer)->followUp(2)->create(['subject' => 'Re: {{hook_subject}}', 'body' => 'Nog even, {{company}}.']);

    return $offer;
}

test('leads_context bundles each lead, its sequence with tags, the thread and the notes', function () {
    $offer = offerWithTags();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create(['company' => 'Tandarts Bos', 'hook' => 'Site is uit 2019.', 'facts' => ['first_name' => 'Marieke']]);
    $lead->notes()->create(['user_id' => $lead->user_id, 'body' => 'Belde: beslist in oktober.']);
    app(SiteReader::class)->shouldReceive('fetch')->once()->andReturn('<html><body><h1>Welkom</h1><script>x()</script><p>Sinds 2009 in Haarlem.</p></body></html>');

    $response = OutreachServer::actingAs($this->user)->tool(LeadsContext::class, ['lead_ids' => [$lead->id], 'with_site_text' => true]);

    $response->assertOk()->assertSee('1 leads.')->assertSee('Tandarts Bos');

    $context = structured($response)['leads'][0];

    expect($context['facts'])->toBe(['first_name' => 'Marieke'])
        ->and($context['offer']['tag_explanations'])->toBe(['compliment' => 'One honest sentence about their site.'])
        ->and($context['offer']['steps'][0]['tags'])->toBe(['hook_subject', 'first_name', 'compliment', 'hook'])
        ->and($context['offer']['steps'][0]['missing_tags'])->toBe(['compliment'])
        ->and($context['offer']['next_step'])->toBe(1)
        ->and($context['notes'][0]['body'])->toBe('Belde: beslist in oktober.')
        ->and($context['site_text'])->toBe('Sinds 2009 in Haarlem.');
});

test('update_leads merges facts, sets the hook and adds a note, for many at once', function () {
    $lead = Lead::factory()->create(['facts' => ['first_name' => 'Marieke', 'old' => 'x']]);
    $other = Lead::factory()->create();

    $response = OutreachServer::actingAs($this->user)->tool(UpdateLeads::class, ['leads' => [
        ['lead_id' => $lead->id, 'facts' => ['compliment' => 'Mooie reviews.', 'old' => ''], 'hook' => 'Site is niet mobiel.', 'note' => 'Eigenaar heet Marieke.'],
        ['lead_id' => $other->id, 'status' => 'no'],
        ['lead_id' => 999999, 'status' => 'no'],
    ]]);

    $response->assertOk()->assertSee('2 leads updated')->assertSee('Not on this account: 999999');

    expect($other->refresh()->status)->toBe(LeadStatus::No);

    $lead->refresh();

    expect($lead->facts)->toBe(['first_name' => 'Marieke', 'compliment' => 'Mooie reviews.'])
        ->and($lead->hook)->toBe('Site is niet mobiel.')
        ->and($lead->notes()->count())->toBe(1);
});

test('send_steps skips a lead with an unfilled tag, then fills and queues the step', function () {
    $offer = offerWithTags();
    Mailbox::factory()->create(['daily_limit' => 20]);
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create(['company' => 'Tandarts Bos', 'email' => 'info@bos.nl', 'hook' => 'Site is uit 2019.']);

    OutreachServer::actingAs($this->user)
        ->tool(SendSteps::class, ['leads' => [['lead_id' => $lead->id, 'values' => ['first_name' => 'Marieke']]]])
        ->assertOk()
        ->assertSee('0 mails queued')
        ->assertSee('Tandarts Bos: unfilled {{compliment}}');

    expect(Message::query()->count())->toBe(0)
        ->and($lead->refresh()->facts)->toBe(['first_name' => 'Marieke']);

    $response = OutreachServer::actingAs($this->user)->tool(SendSteps::class, ['leads' => [['lead_id' => $lead->id, 'values' => ['compliment' => 'Mooie angstpagina.']]]]);

    $response->assertOk()->assertSee('1 mails queued');

    $message = Message::query()->sole();

    expect($message->status)->toBe(MessageStatus::Queued)
        ->and($message->step)->toBe(1)
        ->and($message->subject)->toBe('Site is uit 2019')
        ->and($message->body)->toBe("Hoi Marieke,\n\nMooie angstpagina.\n\nSite is uit 2019.")
        ->and($message->send_after)->not->toBeNull();
});

test('send_steps continues the thread for a later step and can assign the offer first', function () {
    $offer = offerWithTags();
    $mailbox = Mailbox::factory()->create();
    $lead = Lead::factory()->for($offer->niche)->create(['offer_id' => null, 'email' => 'info@bos.nl', 'status' => LeadStatus::Emailed]);
    Message::factory()->for($lead)->for($mailbox)->create(['step' => 1, 'thread_id' => 'thr_first']);

    $response = OutreachServer::actingAs($this->user)->tool(SendSteps::class, ['leads' => [['lead_id' => $lead->id]], 'offer_id' => $offer->id]);

    $response->assertOk();

    $queued = Message::query()->where('status', MessageStatus::Queued)->sole();

    expect($lead->refresh()->offer_id)->toBe($offer->id)
        ->and($queued->step)->toBe(2)
        ->and($queued->thread_id)->toBe('thr_first');
});

test('send queues a free-form mail and a "Re:" subject continues the thread', function () {
    Mailbox::factory()->create();
    $lead = Lead::factory()->create(['email' => 'info@bos.nl']);
    Message::factory()->for($lead)->create(['thread_id' => 'thr_first']);

    OutreachServer::actingAs($this->user)
        ->tool(SendMails::class, ['mails' => [['lead_id' => $lead->id, 'subject' => 'Even iets anders', 'body' => 'Hoi,']]])
        ->assertOk();
    OutreachServer::actingAs($this->user)
        ->tool(SendMails::class, ['mails' => [['lead_id' => $lead->id, 'subject' => 'Re: Jullie site', 'body' => 'Dank voor je antwoord.']]])
        ->assertOk();

    $queued = Message::query()->where('status', MessageStatus::Queued)->orderBy('id')->get();

    expect($queued)->toHaveCount(2)
        ->and($queued[0]->thread_id)->not->toBe('thr_first')
        ->and($queued[0]->step)->toBe(0)
        ->and($queued[1]->thread_id)->toBe('thr_first');
});

test('a draft waits in the app until send_drafts releases it', function () {
    Mailbox::factory()->create();
    $lead = Lead::factory()->create(['email' => 'info@bos.nl']);

    $draft = OutreachServer::actingAs($this->user)
        ->tool(SendMails::class, ['mails' => [['lead_id' => $lead->id, 'subject' => 'Concept', 'body' => 'Tekst']], 'draft' => true]);

    $draft->assertOk()->assertSee('1 mails saved as drafts');

    $id = structured($draft)['mails'][0]['id'];

    expect(Message::query()->find($id)->status)->toBe(MessageStatus::Draft);

    OutreachServer::actingAs($this->user)
        ->tool(SendDrafts::class, ['drafts' => [['message_id' => $id, 'body' => 'Betere tekst']]])
        ->assertOk()->assertSee('1 drafts queued');

    expect(Message::query()->find($id))->toBeNull()
        ->and(Message::query()->sole()->body)->toBe('Betere tekst')
        ->and(Message::query()->sole()->status)->toBe(MessageStatus::Queued);
});

test('send_mails skips a lead without an email or when every mailbox is full', function () {
    $noEmail = Lead::factory()->create(['email' => null]);
    Mailbox::factory()->create(['daily_limit' => 1, 'sent_today' => 1, 'sent_today_on' => today()]);
    $full = Lead::factory()->create(['email' => 'x@y.nl']);

    OutreachServer::actingAs($this->user)
        ->tool(SendMails::class, ['mails' => [['lead_id' => $noEmail->id, 'subject' => 'a', 'body' => 'b']]])
        ->assertOk()->assertSee("{$noEmail->company}: no email address");
    OutreachServer::actingAs($this->user)
        ->tool(SendMails::class, ['mails' => [['lead_id' => $full->id, 'subject' => 'a', 'body' => 'b']]])
        ->assertOk()->assertSee("{$full->company}: every mailbox is full for today");
});

test('check_inbox reads the boxes and lists replies waiting for an answer', function () {
    $lead = Lead::factory()->emailed()->create(['email' => 'info@bos.nl']);
    $mailbox = Mailbox::factory()->create();
    Message::factory()->for($lead)->for($mailbox)->create(['message_id' => '<first@snelstack.com>', 'thread_id' => 'thr_a']);

    // The onboarded test account has a box of its own too; only ours has mail.
    $this->instance(MailboxReader::class, tap(Mockery::mock(MailboxReader::class), function ($fake) use ($mailbox): void {
        $fake->shouldReceive('newMail')->andReturnUsing(fn (Mailbox $box) => $box->is($mailbox)
            ? [new IncomingMail(5, 'info@bos.nl', 'Re: Jullie site', 'Stuur maar.', '<r@bos.nl>', ['<first@snelstack.com>'], CarbonImmutable::now())]
            : []);
    }));

    $response = OutreachServer::actingAs($this->user)->tool(CheckInbox::class, []);

    $response->assertOk()->assertSee('1 new reply')->assertSee('1 reply waiting');

    expect(structured($response)['waiting'][0]['reply']['body'])->toBe('Stuur maar.')
        ->and($lead->refresh()->status)->toBe(LeadStatus::Replied);
});

test('who_needs_follow_up explains why each due lead waits', function () {
    $offer = offerWithTags();
    Mailbox::factory()->create();
    $waiting = Lead::factory()->for($offer->niche)->for($offer)->emailed()->create(['next_action_at' => now()->subDay(), 'company' => 'Wachtend']);
    Message::factory()->for($waiting)->create(['step' => 1]);
    $done = Lead::factory()->for($offer->niche)->for($offer)->create(['status' => LeadStatus::FollowedUp, 'next_action_at' => now()->subDay(), 'company' => 'Klaar']);
    Message::factory()->for($done)->create(['step' => 2]);
    Lead::factory()->for($offer->niche)->for($offer)->create(['status' => LeadStatus::Replied, 'company' => 'Antwoord']);

    $response = OutreachServer::actingAs($this->user)->tool(WhoNeedsFollowUp::class, []);

    $response->assertOk()->assertSee('2 leads due')->assertSee('1 replied');

    $due = collect(structured($response)['due'])->keyBy('company');

    // Step 2 only needs {{company}}, so nothing is missing and it goes out by itself.
    expect($due['Wachtend']['reason'])->toBe('will go out automatically within the hour')
        ->and($due['Wachtend']['next_step'])->toBe(2)
        ->and($due['Klaar']['reason'])->toStartWith('sequence finished');
});

test('stats sums leads and mails per niche, offer and mailbox', function () {
    $offer = offerWithTags();
    $mailbox = Mailbox::factory()->create(['daily_limit' => 20]);
    $a = Lead::factory()->for($offer->niche)->for($offer)->create(['status' => LeadStatus::Customer]);
    $b = Lead::factory()->for($offer->niche)->for($offer)->create();
    Message::factory()->for($a)->for($mailbox)->replied()->create();
    Message::factory()->for($b)->for($mailbox)->create();
    Message::factory()->for($b)->for($mailbox)->bounced()->create();

    $response = OutreachServer::actingAs($this->user)->tool(Stats::class, ['days' => 30]);

    $response->assertOk()->assertSee('3 mails sent, 1 replies (33%), 1 customers, 1 bounced');

    $stats = structured($response);

    expect($stats['offers'][0]['sent'])->toBe(3)
        ->and($stats['offers'][0]['reply_rate'])->toBe(0.333)
        ->and(collect($stats['mailboxes'])->firstWhere('id', $mailbox->id)['limit_today'])->toBe(20)
        ->and($stats['niches'][0]['customers'])->toBe(1);
});

test('list_offers shows the sequences with their tags', function () {
    offerWithTags();

    $response = OutreachServer::actingAs($this->user)->tool(ListOffers::class, []);

    $response->assertOk()->assertSee('1 offers');

    expect(structured($response)['offers'][0]['steps'][0]['tags'])->toBe(['hook_subject', 'first_name', 'compliment', 'hook']);
});

test('tools refuse a lead of another account', function () {
    $foreign = Lead::factory()->create(['user_id' => User::factory()->onboarded()->create()->id]);

    OutreachServer::actingAs($this->user)
        ->tool(LeadsContext::class, ['lead_ids' => [$foreign->id]])
        ->assertOk()
        ->assertSee("Not on this account: {$foreign->id}");
});

test('niches can be listed, created by name and updated', function () {
    $response = OutreachServer::actingAs($this->user)->tool(UpdateNiches::class, ['niches' => [['name' => 'Makelaars', 'why' => 'Veel verouderde sites.']]]);

    $response->assertOk()->assertSee('1 niches saved (1 new)');

    $id = structured($response)['niches'][0]['id'];

    OutreachServer::actingAs($this->user)
        ->tool(UpdateNiches::class, ['niches' => [['niche_id' => $id, 'status' => 'testing', 'findings' => 'Eerste 20 gemaild.']]])
        ->assertOk()->assertSee('1 niches saved (0 new)');

    Lead::factory()->count(2)->create(['niche_id' => $id, 'email' => 'x@y.nl']);

    $list = OutreachServer::actingAs($this->user)->tool(ListNiches::class, []);

    $niche = collect(structured($list)['niches'])->firstWhere('id', $id);

    expect($niche['status'])->toBe('testing')
        ->and($niche['findings'])->toBe('Eerste 20 gemaild.')
        ->and($niche['leads'])->toBe(2)
        ->and($niche['with_email'])->toBe(2);
});

test('preview_mail shows the filled step without saving anything', function () {
    $offer = offerWithTags();
    Mailbox::factory()->create(['address' => 'loc@snelstack.com']);
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create(['company' => 'Tandarts Bos', 'email' => 'info@bos.nl', 'hook' => 'Site is uit 2019.', 'facts' => ['first_name' => 'Marieke']]);

    $response = OutreachServer::actingAs($this->user)->tool(PreviewMails::class, ['mails' => [['lead_id' => $lead->id, 'values' => ['compliment' => 'Mooie site.']]]]);

    $response->assertOk()->assertSee('1 previews');

    $card = structured($response)['mails'][0];

    expect($card['status'])->toBe('preview')
        ->and($card['body'])->toBe("Hoi Marieke,\n\nMooie site.\n\nSite is uit 2019.")
        ->and($card['missing_tags'])->toBe([])
        ->and($card['mailbox'])->toBe('loc@snelstack.com')
        ->and($card['edit_url'])->toEndWith('/leads?lead='.$lead->id)
        ->and($card['send_tool'])->toBe('send_steps')
        ->and($card['send_arguments']['leads'][0]['values'])->toBe(['compliment' => 'Mooie site.'])
        ->and($lead->refresh()->facts)->toBe(['first_name' => 'Marieke'])
        ->and(Message::query()->count())->toBe(0);
});

test('the mail card app renders as an MCP app', function () {
    $response = OutreachServer::actingAs($this->user)->resource(MailCardApp::class);

    $response->assertOk()->assertSee('createMcpApp')->assertSee('Edit in Snelreach');
});

test('send answers with a card that says when it goes', function () {
    Carbon::setTestNow('2026-09-28 10:00:00');
    Mailbox::factory()->create();
    $lead = Lead::factory()->create(['email' => 'info@bos.nl']);

    $card = structured(OutreachServer::actingAs($this->user)->tool(SendMails::class, ['mails' => [['lead_id' => $lead->id, 'subject' => 'Hoi', 'body' => 'Tekst']]]))['mails'][0];

    expect($card['status'])->toBe('queued')
        ->and($card['sends_at'])->toStartWith('Sends today at')
        ->and($card['edit_url'])->toEndWith('/leads?lead='.$lead->id);
});

test('send_steps refuses step 1 while a follow-up still has an unfilled tag', function () {
    $offer = Offer::factory()->create(['placeholders' => ['sketch_url' => 'Link to the sketch']]);
    SequenceStep::factory()->for($offer)->create(['step' => 1, 'subject' => 'Hoi', 'body' => 'Voor {{company}}.']);
    SequenceStep::factory()->for($offer)->followUp(2)->create(['subject' => 'Re: Hoi', 'body' => 'De schets: {{sketch_url}}']);
    Mailbox::factory()->create();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create(['company' => 'Bos', 'email' => 'info@bos.nl']);

    OutreachServer::actingAs($this->user)
        ->tool(SendSteps::class, ['leads' => [['lead_id' => $lead->id]]])
        ->assertOk()
        ->assertSee('Bos: unfilled {{sketch_url}} (in this step or a follow-up)');

    OutreachServer::actingAs($this->user)
        ->tool(SendSteps::class, ['leads' => [['lead_id' => $lead->id, 'values' => ['sketch_url' => 'https://x.nl/s']]]])
        ->assertOk()
        ->assertSee('1 mails queued');
});

test('a tool answer carries its data in the text, for hosts that only show the model the text', function () {
    $lead = Lead::factory()->create(['company' => 'Tandarts Bos', 'email' => 'info@bos.nl', 'website' => 'bos.nl']);

    OutreachServer::actingAs($this->user)
        ->tool(ListLeads::class, [])
        ->assertOk()
        ->assertSee('1 leads.')
        ->assertSee('"email": "info@bos.nl"')
        ->assertSee('"website": "bos.nl"');
});

test('every card app renders and every listing tool links back into the app', function () {
    foreach ([LeadCardApp::class, LeadListApp::class, StatsApp::class, CatalogApp::class] as $app) {
        OutreachServer::actingAs($this->user)->resource($app)->assertOk()->assertSee('createMcpApp')->assertSee('Open');
    }

    $lead = Lead::factory()->create();

    expect(structured(OutreachServer::actingAs($this->user)->tool(ListLeads::class, []))['url'])->toEndWith('/leads')
        ->and(structured(OutreachServer::actingAs($this->user)->tool(LeadsContext::class, ['lead_ids' => [$lead->id]]))['leads'][0]['url'])->toEndWith('/leads?lead='.$lead->id)
        ->and(structured(OutreachServer::actingAs($this->user)->tool(Stats::class, []))['url'])->toEndWith('/dashboard')
        ->and(structured(OutreachServer::actingAs($this->user)->tool(ListOffers::class, []))['url'])->toEndWith('/offers')
        ->and(structured(OutreachServer::actingAs($this->user)->tool(ListNiches::class, []))['url'])->toEndWith('/niches')
        ->and(structured(OutreachServer::actingAs($this->user)->tool(WhoNeedsFollowUp::class, []))['url'])->toEndWith('/inbox');
});

test('create_offers builds niches, sequences and tag explanations; update_offers changes them', function () {
    OutreachServer::actingAs($this->user)
        ->tool(CreateOffers::class, ['offers' => [[
            'name' => 'Herhaalcheck', 'niche' => 'Opleiders',
            'steps' => [['subject' => '{{hook_subject}}', 'body' => 'Hoi {{first_name}}, {{compliment}}']],
        ]]])
        ->assertOk()
        ->assertSee('0 offers created')
        ->assertSee('Herhaalcheck: explain {{first_name}}, {{compliment}} in placeholders');

    $response = OutreachServer::actingAs($this->user)->tool(CreateOffers::class, ['offers' => [
        [
            'name' => 'Herhaalcheck', 'niche' => 'Opleiders', 'description' => 'Check of hun herhalingen kloppen.',
            'steps' => [
                ['subject' => '{{hook_subject}}', 'body' => 'Hoi {{first_name}}, {{compliment}}'],
                ['subject' => 'Re: {{hook_subject}}', 'body' => 'Nog even.', 'days_after_previous' => 6],
                ['subject' => 'Re: {{hook_subject}}', 'body' => 'Laatste keer.', 'days_after_previous' => 6],
            ],
            'placeholders' => ['first_name' => 'Owner first name', 'compliment' => 'One honest sentence.'],
        ],
        ['name' => 'Keuringscheck', 'niche' => 'Keuringsbedrijven', 'steps' => [['subject' => 'Hoi', 'body' => 'Voor {{company}}.']]],
    ]]);

    $response->assertOk()->assertSee('2 offers created');

    $offer = structured($response)['offers'][0];

    expect($offer['status'])->toBe('active')
        ->and($offer['steps'])->toHaveCount(3)
        ->and($offer['steps'][1]['days_after_previous'])->toBe(6)
        ->and($offer['steps'][0]['days_after_previous'])->toBe(0)
        ->and($this->user->niches()->whereIn('name', ['Opleiders', 'Keuringsbedrijven'])->count())->toBe(2);

    $updated = OutreachServer::actingAs($this->user)->tool(UpdateOffers::class, ['offers' => [[
        'offer_id' => $offer['id'], 'auto_follow_up' => false,
        'steps' => [['subject' => 'Nieuw', 'body' => 'Alleen {{company}}.']],
    ]]]);

    $updated->assertOk()->assertSee('1 offers updated');

    expect(structured($updated)['offers'][0]['steps'])->toHaveCount(1)
        ->and(structured($updated)['offers'][0]['auto_follow_up'])->toBeFalse()
        ->and(structured($updated)['offers'][0]['tag_explanations'])->toBe(['first_name' => 'Owner first name', 'compliment' => 'One honest sentence.']);
});

test('create_leads adds many leads at once and skips known addresses', function () {
    Lead::factory()->create(['email' => 'known@bos.nl']);

    $response = OutreachServer::actingAs($this->user)->tool(CreateLeads::class, [
        'niche' => 'Tandartsen',
        'leads' => [
            ['company' => 'Tandarts Bos', 'email' => 'Info@Bos.nl', 'website' => 'https://www.tandartsbos.nl/contact', 'facts' => ['first_name' => 'Marieke']],
            ['company' => 'Mondzorg Zuid', 'niche' => 'Mondhygiëne', 'city' => 'Amsterdam'],
            ['company' => 'Bekend', 'email' => 'known@bos.nl'],
        ],
    ]);

    $response->assertOk()->assertSee('2 leads added')->assertSee('skipped): Bekend');

    $bos = $this->user->leads()->where('company', 'Tandarts Bos')->first();

    expect($bos->email)->toBe('info@bos.nl')
        ->and($bos->website)->toBe('tandartsbos.nl')
        ->and($bos->facts)->toBe(['first_name' => 'Marieke'])
        ->and($bos->niche->name)->toBe('Tandartsen')
        ->and($this->user->leads()->where('company', 'Mondzorg Zuid')->first()->niche->name)->toBe('Mondhygiëne')
        ->and(structured($response)['leads'])->toHaveCount(2)
        ->and($this->user->leads()->count())->toBe(3);
});

test('list_mailboxes shows room and login state', function () {
    Mailbox::factory()->create(['address' => 'loc@snelstack.com', 'daily_limit' => 30]);

    $response = OutreachServer::actingAs($this->user)->tool(ListMailboxes::class, []);

    $box = collect(structured($response)['mailboxes'])->firstWhere('address', 'loc@snelstack.com');

    expect($box['limit_today'])->toBe(30)->and($box['login_ok'])->toBeTrue();
});

test('object arguments sent as JSON strings are accepted', function () {
    $response = OutreachServer::actingAs($this->user)->tool(CreateLeads::class, [
        'niche' => 'Keuringsbedrijven',
        'leads' => '[{"company":"Drost","email":"info@drost.nl","facts":"{\"first_name\":\"Tim\"}"}]',
    ]);

    $response->assertOk()->assertSee('1 leads added');

    expect($this->user->leads()->where('company', 'Drost')->first()->facts)->toBe(['first_name' => 'Tim']);
});

test('update_mails changes or cancels mail that has not gone out, and leaves sent mail alone', function () {
    $lead = Lead::factory()->create(['company' => 'Bos']);
    $queued = Message::factory()->queued()->for($lead)->create(['subject' => 'Oud']);
    $other = Message::factory()->queued()->for($lead)->create();
    $sent = Message::factory()->for($lead)->create();

    $response = OutreachServer::actingAs($this->user)->tool(UpdateMails::class, ['mails' => [
        ['message_id' => $queued->id, 'subject' => 'Nieuw', 'send_at' => '2026-09-29 10:30'],
        ['message_id' => $other->id, 'cancel' => true],
        ['message_id' => $sent->id, 'body' => 'x'],
    ]]);

    $response->assertOk()->assertSee('1 mails changed, 1 cancelled')->assertSee('Bos: already sent');

    $queued->refresh();

    expect($queued->subject)->toBe('Nieuw')
        ->and($queued->send_after->setTimezone('Europe/Amsterdam')->format('Y-m-d H:i'))->toBe('2026-09-29 10:30')
        ->and(Message::query()->find($other->id))->toBeNull();
});

test('enriching saves what the homepage and about page say, without the menu', function () {
    $lead = Lead::factory()->create(['website' => 'bos.nl', 'site_text' => null]);
    app(SiteReader::class)->shouldReceive('fetch')->andReturnUsing(fn (string $url) => match ($url) {
        'https://bos.nl' => '<html><body><nav>Home Diensten Contact</nav><p>Wij zijn al 25 jaar de tandarts van Haarlem-Noord.</p><div class="cookie-banner"><p>Wij gebruiken cookies om uw ervaring te verbeteren.</p></div></body></html>',
        'https://bos.nl/over-ons' => '<html><body><p>Marieke en Sander runnen de praktijk samen sinds 1999.</p></body></html>',
        default => null,
    });

    app(Enricher::class)->enrich($lead);

    expect($lead->refresh()->site_text)->toBe("Home:\nWij zijn al 25 jaar de tandarts van Haarlem-Noord.\n\nAbout:\nMarieke en Sander runnen de praktijk samen sinds 1999.");
});
