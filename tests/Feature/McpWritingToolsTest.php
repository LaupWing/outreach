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
use App\Mcp\Tools\CreateOffer;
use App\Mcp\Tools\LeadContext;
use App\Mcp\Tools\ListLeads;
use App\Mcp\Tools\ListMailboxes;
use App\Mcp\Tools\ListNiches;
use App\Mcp\Tools\ListOffers;
use App\Mcp\Tools\PreviewMail;
use App\Mcp\Tools\SendDraft;
use App\Mcp\Tools\SendMail;
use App\Mcp\Tools\SendStep;
use App\Mcp\Tools\Stats;
use App\Mcp\Tools\UpdateLead;
use App\Mcp\Tools\UpdateNiche;
use App\Mcp\Tools\UpdateOffer;
use App\Mcp\Tools\WhoNeedsFollowUp;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Offer;
use App\Models\SequenceStep;
use App\Models\User;
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

test('lead_context bundles the lead, its sequence with tags, the thread and the notes', function () {
    $offer = offerWithTags();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create(['company' => 'Tandarts Bos', 'hook' => 'Site is uit 2019.', 'facts' => ['first_name' => 'Marieke']]);
    $lead->notes()->create(['user_id' => $lead->user_id, 'body' => 'Belde: beslist in oktober.']);
    app(SiteReader::class)->shouldReceive('fetch')->once()->andReturn('<html><body><h1>Welkom</h1><script>x()</script><p>Sinds 2009 in Haarlem.</p></body></html>');

    $response = OutreachServer::actingAs($this->user)->tool(LeadContext::class, ['lead_id' => $lead->id, 'with_site_text' => true]);

    $response->assertOk()->assertSee('Tandarts Bos')->assertSee('next step 1');

    $context = structured($response);

    expect($context['lead']['facts'])->toBe(['first_name' => 'Marieke'])
        ->and($context['offer']['tag_explanations'])->toBe(['compliment' => 'One honest sentence about their site.'])
        ->and($context['offer']['steps'][0]['tags'])->toBe(['hook_subject', 'first_name', 'compliment', 'hook'])
        ->and($context['offer']['steps'][0]['missing_tags'])->toBe(['compliment'])
        ->and($context['offer']['next_step'])->toBe(1)
        ->and($context['notes'][0]['body'])->toBe('Belde: beslist in oktober.')
        ->and($context['site_text'])->toBe("Welkom\nSinds 2009 in Haarlem.");
});

test('update_lead merges facts, sets the hook and adds a note', function () {
    $lead = Lead::factory()->create(['facts' => ['first_name' => 'Marieke', 'old' => 'x']]);

    $response = OutreachServer::actingAs($this->user)->tool(UpdateLead::class, [
        'lead_id' => $lead->id,
        'facts' => ['compliment' => 'Mooie reviews.', 'old' => ''],
        'hook' => 'Site is niet mobiel.',
        'note' => 'Eigenaar heet Marieke.',
    ]);

    $response->assertOk()->assertSee('facts, hook, note');

    $lead->refresh();

    expect($lead->facts)->toBe(['first_name' => 'Marieke', 'compliment' => 'Mooie reviews.'])
        ->and($lead->hook)->toBe('Site is niet mobiel.')
        ->and($lead->notes()->count())->toBe(1);
});

test('send_step refuses while a tag is unfilled, then fills and queues the step', function () {
    $offer = offerWithTags();
    Mailbox::factory()->create(['daily_limit' => 20]);
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create(['company' => 'Tandarts Bos', 'email' => 'info@bos.nl', 'hook' => 'Site is uit 2019.']);

    OutreachServer::actingAs($this->user)
        ->tool(SendStep::class, ['lead_id' => $lead->id, 'values' => ['first_name' => 'Marieke']])
        ->assertHasErrors(['Still unfilled: {{compliment}}. Pass them in values (see the offer\'s tag_explanations in lead_context).']);

    expect(Message::query()->count())->toBe(0)
        ->and($lead->refresh()->facts)->toBe(['first_name' => 'Marieke']);

    $response = OutreachServer::actingAs($this->user)->tool(SendStep::class, ['lead_id' => $lead->id, 'values' => ['compliment' => 'Mooie angstpagina.']]);

    $response->assertOk()->assertSee('Queued for Tandarts Bos');

    $message = Message::query()->sole();

    expect($message->status)->toBe(MessageStatus::Queued)
        ->and($message->step)->toBe(1)
        ->and($message->subject)->toBe('Site is uit 2019')
        ->and($message->body)->toBe("Hoi Marieke,\n\nMooie angstpagina.\n\nSite is uit 2019.")
        ->and($message->send_after)->not->toBeNull();
});

test('send_step continues the thread for a later step and can assign the offer first', function () {
    $offer = offerWithTags();
    $mailbox = Mailbox::factory()->create();
    $lead = Lead::factory()->for($offer->niche)->create(['offer_id' => null, 'email' => 'info@bos.nl', 'status' => LeadStatus::Emailed]);
    Message::factory()->for($lead)->for($mailbox)->create(['step' => 1, 'thread_id' => 'thr_first']);

    $response = OutreachServer::actingAs($this->user)->tool(SendStep::class, ['lead_id' => $lead->id, 'offer_id' => $offer->id]);

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
        ->tool(SendMail::class, ['lead_id' => $lead->id, 'subject' => 'Even iets anders', 'body' => 'Hoi,'])
        ->assertOk();
    OutreachServer::actingAs($this->user)
        ->tool(SendMail::class, ['lead_id' => $lead->id, 'subject' => 'Re: Jullie site', 'body' => 'Dank voor je antwoord.'])
        ->assertOk();

    $queued = Message::query()->where('status', MessageStatus::Queued)->orderBy('id')->get();

    expect($queued)->toHaveCount(2)
        ->and($queued[0]->thread_id)->not->toBe('thr_first')
        ->and($queued[0]->step)->toBe(0)
        ->and($queued[1]->thread_id)->toBe('thr_first');
});

test('a draft waits in the app until send_draft releases it', function () {
    Mailbox::factory()->create();
    $lead = Lead::factory()->create(['email' => 'info@bos.nl']);

    $draft = OutreachServer::actingAs($this->user)
        ->tool(SendMail::class, ['lead_id' => $lead->id, 'subject' => 'Concept', 'body' => 'Tekst', 'draft' => true]);

    $draft->assertOk()->assertSee('Draft');

    $id = structured($draft)['id'];

    expect(Message::query()->find($id)->status)->toBe(MessageStatus::Draft);

    OutreachServer::actingAs($this->user)
        ->tool(SendDraft::class, ['message_id' => $id, 'body' => 'Betere tekst'])
        ->assertOk()->assertSee('Queued');

    expect(Message::query()->find($id))->toBeNull()
        ->and(Message::query()->sole()->body)->toBe('Betere tekst')
        ->and(Message::query()->sole()->status)->toBe(MessageStatus::Queued);
});

test('send refuses a lead without an email or when every mailbox is full', function () {
    $noEmail = Lead::factory()->create(['email' => null]);
    Mailbox::factory()->create(['daily_limit' => 1, 'sent_today' => 1, 'sent_today_on' => today()]);
    $full = Lead::factory()->create(['email' => 'x@y.nl']);

    OutreachServer::actingAs($this->user)
        ->tool(SendMail::class, ['lead_id' => $noEmail->id, 'subject' => 'a', 'body' => 'b'])
        ->assertHasErrors(["{$noEmail->company} has no email address; enrich_lead or update it first."]);
    OutreachServer::actingAs($this->user)
        ->tool(SendMail::class, ['lead_id' => $full->id, 'subject' => 'a', 'body' => 'b'])
        ->assertHasErrors(['Every mailbox is full for today (or paused); try again tomorrow or raise a limit.']);
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
        ->tool(LeadContext::class, ['lead_id' => $foreign->id])
        ->assertHasErrors(["No lead with id {$foreign->id} on this account."]);
});

test('niches can be listed, created by name and updated', function () {
    $response = OutreachServer::actingAs($this->user)->tool(UpdateNiche::class, ['name' => 'Makelaars', 'why' => 'Veel verouderde sites.']);

    $response->assertOk()->assertSee('Created niche "Makelaars" (idea)');

    $id = structured($response)['id'];

    OutreachServer::actingAs($this->user)
        ->tool(UpdateNiche::class, ['niche_id' => $id, 'status' => 'testing', 'findings' => 'Eerste 20 gemaild.'])
        ->assertOk()->assertSee('Updated niche "Makelaars" (testing)');

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

    $response = OutreachServer::actingAs($this->user)->tool(PreviewMail::class, ['lead_id' => $lead->id, 'values' => ['compliment' => 'Mooie site.']]);

    $response->assertOk()->assertSee('Preview of step 1 for Tandarts Bos');

    $card = structured($response);

    expect($card['status'])->toBe('preview')
        ->and($card['body'])->toBe("Hoi Marieke,\n\nMooie site.\n\nSite is uit 2019.")
        ->and($card['missing_tags'])->toBe([])
        ->and($card['mailbox'])->toBe('loc@snelstack.com')
        ->and($card['edit_url'])->toEndWith('/leads?lead='.$lead->id)
        ->and($card['send_tool'])->toBe('send_step')
        ->and($card['send_arguments']['values'])->toBe(['compliment' => 'Mooie site.'])
        ->and($lead->refresh()->facts)->toBe(['first_name' => 'Marieke'])
        ->and(Message::query()->count())->toBe(0);
});

test('the mail card app renders as an MCP app', function () {
    $response = OutreachServer::actingAs($this->user)->resource(MailCardApp::class);

    $response->assertOk()->assertSee('createMcpApp')->assertSee('Edit in Snelreach');
});

test('send_step answers with a card that says when it goes', function () {
    Carbon::setTestNow('2026-09-28 10:00:00');
    $offer = offerWithTags();
    Mailbox::factory()->create();
    $lead = Lead::factory()->for($offer->niche)->for($offer)->create(['email' => 'info@bos.nl', 'hook' => 'Site is oud.', 'facts' => ['first_name' => 'M', 'compliment' => 'C']]);

    $card = structured(OutreachServer::actingAs($this->user)->tool(SendStep::class, ['lead_id' => $lead->id]));

    expect($card['status'])->toBe('queued')
        ->and($card['sends_at'])->toStartWith('Sends today at')
        ->and($card['edit_url'])->toEndWith('/leads?lead='.$lead->id);
});

test('every card app renders and every listing tool links back into the app', function () {
    foreach ([LeadCardApp::class, LeadListApp::class, StatsApp::class, CatalogApp::class] as $app) {
        OutreachServer::actingAs($this->user)->resource($app)->assertOk()->assertSee('createMcpApp')->assertSee('Open');
    }

    $lead = Lead::factory()->create();

    expect(structured(OutreachServer::actingAs($this->user)->tool(ListLeads::class, []))['url'])->toEndWith('/leads')
        ->and(structured(OutreachServer::actingAs($this->user)->tool(LeadContext::class, ['lead_id' => $lead->id]))['lead']['url'])->toEndWith('/leads?lead='.$lead->id)
        ->and(structured(OutreachServer::actingAs($this->user)->tool(Stats::class, []))['url'])->toEndWith('/dashboard')
        ->and(structured(OutreachServer::actingAs($this->user)->tool(ListOffers::class, []))['url'])->toEndWith('/offers')
        ->and(structured(OutreachServer::actingAs($this->user)->tool(ListNiches::class, []))['url'])->toEndWith('/niches')
        ->and(structured(OutreachServer::actingAs($this->user)->tool(WhoNeedsFollowUp::class, []))['url'])->toEndWith('/inbox');
});

test('create_offer builds the niche, the sequence and the tag explanations; update_offer changes them', function () {
    OutreachServer::actingAs($this->user)
        ->tool(CreateOffer::class, [
            'name' => 'Herhaalcheck', 'niche' => 'Opleiders',
            'steps' => [['subject' => '{{hook_subject}}', 'body' => 'Hoi {{first_name}}, {{compliment}}']],
        ])
        ->assertHasErrors(['Explain these custom tags in placeholders first: {{first_name}}, {{compliment}}.']);

    $response = OutreachServer::actingAs($this->user)->tool(CreateOffer::class, [
        'name' => 'Herhaalcheck', 'niche' => 'Opleiders', 'description' => 'Check of hun herhalingen kloppen.',
        'steps' => [
            ['subject' => '{{hook_subject}}', 'body' => 'Hoi {{first_name}}, {{compliment}}'],
            ['subject' => 'Re: {{hook_subject}}', 'body' => 'Nog even.', 'days_after_previous' => 6],
            ['subject' => 'Re: {{hook_subject}}', 'body' => 'Laatste keer.', 'days_after_previous' => 6],
        ],
        'placeholders' => ['first_name' => 'Owner first name', 'compliment' => 'One honest sentence.'],
    ]);

    $response->assertOk()->assertSee('Created offer "Herhaalcheck" for Opleiders with 3 steps');

    $offer = structured($response)['offers'][0];

    expect($offer['status'])->toBe('active')
        ->and($offer['steps'])->toHaveCount(3)
        ->and($offer['steps'][1]['days_after_previous'])->toBe(6)
        ->and($offer['steps'][0]['days_after_previous'])->toBe(0)
        ->and($this->user->niches()->where('name', 'Opleiders')->exists())->toBeTrue();

    $updated = OutreachServer::actingAs($this->user)->tool(UpdateOffer::class, [
        'offer_id' => $offer['id'], 'auto_follow_up' => false,
        'steps' => [['subject' => 'Nieuw', 'body' => 'Alleen {{company}}.']],
    ]);

    $updated->assertOk();

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
