<?php

namespace Database\Seeders;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MailboxStatus;
use App\Enums\MailboxType;
use App\Enums\MessageStatus;
use App\Enums\NicheStatus;
use App\Enums\OfferStatus;
use App\Enums\ScrapeRunStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\SequenceStep;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The demo data the UI was designed on: four niches, three offers, a dozen leads
 * with mails in every state, so every screen has something to show.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the demo data.
     */
    public function run(): void
    {
        $niches = $this->niches();
        $offers = $this->offers($niches);
        $this->steps($offers);
        $runs = $this->scrapeRuns($niches);
        $leads = $this->leads($niches, $offers, $runs);
        $mailboxes = $this->mailboxes();
        $this->messages($leads, $mailboxes);
    }

    /**
     * @return array<string, Niche>
     */
    private function niches(): array
    {
        $rows = [
            'dentists' => ['Tandartsen', NicheStatus::Testing, 'Veel praktijken met verouderde sites en een vaste geldstroom.', 'Eerste batch van 20 mails: twee reacties, allebei op de hook over mobiel. De praktijkmanager beslist, niet de tandarts. Vaste prijs en "in twee weken live" doen het goed; een gratis check wordt genegeerd.'],
            'physio' => ['Fysiotherapeuten', NicheStatus::Testing, 'Sterk lokaal, concurreren op vindbaarheid.', 'Veel praktijken zitten in een keten met een centrale site, dus filteren op zelfstandige praktijken. De snelheidscheck levert opens op maar nog geen reacties.'],
            'restaurants' => ['Restaurants', NicheStatus::Idea, 'Groot volume, maar lage marges.', 'Nog niet gemaild. Eerste scrape laat zien dat de meeste zaken alleen Instagram en een Google-profiel hebben; de site is vaak van de franchise.'],
            'lawyers' => ['Advocatenkantoren', NicheStatus::Idea, 'Hoge orderwaarde, sites vaak uit 2015.', 'Nog niet gemaild. Vrijwel elk kantoor heeft een info@-adres; de kans is groot dat de mail bij het secretariaat blijft hangen.'],
        ];

        $niches = [];

        foreach ($rows as $key => [$name, $status, $why, $findings]) {
            $niches[$key] = Niche::query()->create(['name' => $name, 'status' => $status, 'why' => $why, 'findings' => $findings]);
        }

        return $niches;
    }

    /**
     * @param  array<string, Niche>  $niches
     * @return array<string, Offer>
     */
    private function offers(array $niches): array
    {
        return [
            'website' => Offer::query()->create([
                'niche_id' => $niches['dentists']->id,
                'name' => 'Nieuwe website in 2 weken',
                'description' => 'Snelle, mobiele praktijksite met online afspraken. Vaste prijs.',
                'status' => OfferStatus::Active,
            ]),
            'speedcheck' => Offer::query()->create([
                'niche_id' => $niches['physio']->id,
                'name' => 'Gratis snelheidscheck',
                'description' => 'Rapport over laadtijd en mobiel gebruik, met een aanbod erachter.',
                'status' => OfferStatus::Active,
            ]),
            'menu' => Offer::query()->create([
                'niche_id' => $niches['restaurants']->id,
                'name' => 'Menukaart online + reserveren',
                'description' => null,
                'status' => OfferStatus::Idea,
            ]),
        ];
    }

    /**
     * @param  array<string, Offer>  $offers
     */
    private function steps(array $offers): void
    {
        $rows = [
            [$offers['website'], 1, 0, '{{hook_subject}}', "Hoi,\n\n{{hook}}\n\nIk bouw praktijksites die in twee weken live staan, vaste prijs, met online afspraken. Zal ik laten zien hoe die van {{company}} eruit zou zien?\n\nLoc"],
            [$offers['website'], 2, 4, 'Re: {{hook_subject}}', "Hoi,\n\nNog even hierop terugkomen. Ik heb een schets gemaakt van hoe {{company}} er op mobiel uit zou zien. Zal ik hem sturen?\n\nLoc"],
            [$offers['website'], 3, 7, 'Re: {{hook_subject}}', "Hoi,\n\nLaatste keer dat ik stoor. Als het nu niet uitkomt, prima. Mocht de site later aan de beurt zijn, dan weet je me te vinden.\n\nLoc"],
            [$offers['speedcheck'], 1, 0, '{{hook_subject}}', "Hoi,\n\n{{hook}}\n\nIk doe gratis een check van laadtijd en mobiel gebruik voor {{company}}, met een kort rapport. Zal ik hem sturen?\n\nLoc"],
            [$offers['speedcheck'], 2, 5, 'Re: {{hook_subject}}', "Hoi,\n\nHet rapport staat klaar, het kost jullie niks. Zal ik het sturen?\n\nLoc"],
        ];

        foreach ($rows as [$offer, $step, $days, $subject, $body]) {
            SequenceStep::query()->create([
                'offer_id' => $offer->id,
                'step' => $step,
                'days_after_previous' => $days,
                'subject' => $subject,
                'body' => $body,
            ]);
        }
    }

    /**
     * @param  array<string, Niche>  $niches
     * @return array<string, ScrapeRun>
     */
    private function scrapeRuns(array $niches): array
    {
        $rows = [
            'dentists_amsterdam' => [$niches['dentists'], 'tandarts', 'Amsterdam', ScrapeRunStatus::Done, 3, 60, 37, 2, '2026-09-20 08:00', '2026-09-20 08:12'],
            'physio_thehague' => [$niches['physio'], 'fysiotherapie', 'Den Haag', ScrapeRunStatus::Failed, 1, 20, 0, 0, '2026-09-20 16:20', '2026-09-20 16:21'],
            'physio_rotterdam' => [$niches['physio'], 'fysiotherapie', 'Rotterdam', ScrapeRunStatus::Done, 3, 60, 41, 3, '2026-09-21 07:50', '2026-09-21 08:01'],
            'dentists_utrecht' => [$niches['dentists'], 'tandarts', 'Utrecht', ScrapeRunStatus::Done, 3, 60, 34, 2, '2026-09-25 09:15', '2026-09-25 09:24'],
            'restaurants_noord' => [$niches['restaurants'], 'restaurant', 'Amsterdam Noord', ScrapeRunStatus::Done, 3, 60, 22, 9, '2026-09-25 11:30', '2026-09-25 11:41'],
            'lawyers_utrecht' => [$niches['lawyers'], 'advocaat', 'Utrecht', ScrapeRunStatus::Done, 3, 58, 31, 4, '2026-09-24 08:02', '2026-09-24 08:09'],
            'dentists_haarlem' => [$niches['dentists'], 'tandarts', 'Haarlem', ScrapeRunStatus::Running, 2, 40, 11, 1, '2026-09-26 09:41', null],
        ];

        $runs = [];

        foreach ($rows as $key => [$niche, $query, $place, $status, $requests, $found, $withEmail, $blocked, $startedAt, $finishedAt]) {
            $runs[$key] = ScrapeRun::query()->create([
                'niche_id' => $niche->id,
                'query' => $query,
                'place' => $place,
                'status' => $status,
                'requests' => $requests,
                'found' => $found,
                'with_email' => $withEmail,
                'blocked' => $blocked,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
            ]);
        }

        return $runs;
    }

    /**
     * @param  array<string, Niche>  $niches
     * @param  array<string, Offer>  $offers
     * @param  array<string, ScrapeRun>  $runs
     * @return array<string, Lead>
     */
    private function leads(array $niches, array $offers, array $runs): array
    {
        $signals = fn (array $overrides = []): array => [
            'copyright_year' => 2019,
            'viewport' => true,
            'software' => ['WordPress'],
            'last_news_at' => null,
            'blocked' => false,
            'javascript_only' => false,
            ...$overrides,
        ];

        $rows = [
            'linde' => ['Tandartspraktijk De Linde', 'info@tandartsdelinde.nl', '020 123 4567', 'tandartsdelinde.nl', 'Amsterdam', 'dentists', 'website', LeadStatus::Emailed, LeadSource::Places, 'dentists_amsterdam', 'Copyright staat nog op 2017 en de site is niet mobiel.', $signals(['copyright_year' => 2017, 'viewport' => false]), '2026-09-22 09:10', '2026-09-26 09:00', '2026-09-20 08:00'],
            'mondzorg' => ['Mondzorg Zuid', 'praktijk@mondzorgzuid.nl', '020 987 6543', 'mondzorgzuid.nl', 'Amsterdam', 'dentists', 'website', LeadStatus::Replied, LeadSource::Places, 'dentists_amsterdam', 'Laatste nieuwsbericht is uit 2021.', $signals(['last_news_at' => '2021-03-12', 'software' => ['Joomla']]), '2026-09-24 14:30', null, '2026-09-20 08:00'],
            'vecht' => ['Tandartsen aan de Vecht', null, '030 222 1111', 'tandartsenaandevecht.nl', 'Utrecht', 'dentists', null, LeadStatus::New, LeadSource::Places, 'dentists_utrecht', null, $signals(['javascript_only' => true]), null, null, '2026-09-25 10:00'],
            'haarlem' => ['Dental Clinic Haarlem', 'hello@dentalclinichaarlem.nl', null, 'dentalclinichaarlem.nl', 'Haarlem', 'dentists', 'website', LeadStatus::FollowedUp, LeadSource::Places, 'dentists_haarlem', 'Site draait op Wix en laadt traag op mobiel.', $signals(['software' => ['Wix'], 'copyright_year' => 2022]), '2026-09-25 08:00', '2026-09-29 08:00', '2026-09-18 08:00'],
            'fysiocentrum' => ['Praktijk Fysio Centrum', 'info@fysiocentrum.nl', '010 444 5555', 'fysiocentrum.nl', 'Rotterdam', 'physio', 'speedcheck', LeadStatus::Emailed, LeadSource::Places, 'physio_rotterdam', 'Geen viewport-tag, dus onleesbaar op telefoon.', $signals(['viewport' => false, 'copyright_year' => 2016]), '2026-09-23 11:00', '2026-09-27 09:00', '2026-09-21 08:00'],
            'fysiofit' => ['FysioFit Kralingen', 'contact@fysiofitkralingen.nl', '010 111 2222', 'fysiofitkralingen.nl', 'Rotterdam', 'physio', 'speedcheck', LeadStatus::Customer, LeadSource::Places, 'physio_rotterdam', 'Copyright 2018, nieuws stopt in 2020.', $signals(['copyright_year' => 2018, 'last_news_at' => '2020-11-02']), '2026-09-15 16:00', null, '2026-09-01 08:00'],
            'vandijk' => ['Fysiotherapie Van Dijk', 'info@fysiovandijk.nl', null, 'fysiovandijk.nl', 'Den Haag', 'physio', 'speedcheck', LeadStatus::No, LeadSource::Places, 'physio_thehague', 'Site uit 2015 zonder https.', $signals(['copyright_year' => 2015]), '2026-09-19 10:00', null, '2026-09-10 08:00'],
            'rugkliniek' => ['Rugkliniek Delft', 'receptie@rugkliniekdelft.nl', '015 333 4444', 'rugkliniekdelft.nl', 'Delft', 'physio', 'speedcheck', LeadStatus::Undeliverable, LeadSource::Places, null, 'Verouderd thema, geen mobiele weergave.', $signals(['viewport' => false]), '2026-09-22 09:00', null, '2026-09-12 08:00'],
            'kade' => ['Bistro De Kade', 'reserveren@bistrodekade.nl', '020 555 6666', 'bistrodekade.nl', 'Amsterdam', 'restaurants', null, LeadStatus::New, LeadSource::Places, 'restaurants_noord', null, $signals(['software' => ['Squarespace'], 'copyright_year' => 2023]), null, null, '2026-09-25 12:00'],
            'noord' => ['Restaurant Noord', null, null, 'restaurantnoord.nl', 'Amsterdam', 'restaurants', null, LeadStatus::New, LeadSource::Places, 'restaurants_noord', null, $signals(['blocked' => true]), null, null, '2026-09-25 12:00'],
            'vanleeuwen' => ['Van Leeuwen Advocaten', 'info@vanleeuwenadvocaten.nl', '030 777 8888', 'vanleeuwenadvocaten.nl', 'Utrecht', 'lawyers', null, LeadStatus::New, LeadSource::Register, null, null, $signals(['copyright_year' => 2014, 'viewport' => false]), null, null, '2026-09-24 08:00'],
            'bos' => ['Tandarts Bos & Partners', 'info@tandartsbos.nl', '023 111 9999', 'tandartsbos.nl', 'Haarlem', 'dentists', 'website', LeadStatus::Emailed, LeadSource::Manual, null, 'Site is van 2019 en heeft geen online afspraken.', $signals(['copyright_year' => 2019]), '2026-09-25 09:00', '2026-09-28 09:00', '2026-09-23 08:00'],
        ];

        $leads = [];

        foreach ($rows as $key => [$company, $email, $phone, $website, $city, $niche, $offer, $status, $source, $run, $hook, $leadSignals, $lastContact, $nextAction, $createdAt]) {
            $leads[$key] = Lead::query()->create([
                'niche_id' => $niches[$niche]->id,
                'offer_id' => $offer === null ? null : $offers[$offer]->id,
                'scrape_run_id' => $run === null ? null : $runs[$run]->id,
                'company' => $company,
                'email' => $email,
                'phone' => $phone,
                'website' => $website,
                'city' => $city,
                'status' => $status,
                'source' => $source,
                'hook' => $hook,
                'signals' => $leadSignals,
                'last_contact_at' => $lastContact,
                'next_action_at' => $nextAction,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        return $leads;
    }

    /**
     * @return array<string, Mailbox>
     */
    private function mailboxes(): array
    {
        return [
            'com' => Mailbox::query()->create(['address' => 'loc@snelstack.com', 'type' => MailboxType::Gmail, 'status' => MailboxStatus::Active, 'daily_limit' => 40, 'sent_today' => 12, 'sent_today_on' => today()]),
            'nl' => Mailbox::query()->create(['address' => 'loc@snelstack.nl', 'type' => MailboxType::Gmail, 'status' => MailboxStatus::WarmingUp, 'daily_limit' => 20, 'sent_today' => 3, 'sent_today_on' => today(), 'warm_up_started_at' => now()->subDays(3)]),
            'io' => Mailbox::query()->create(['address' => 'hallo@snelstack.io', 'type' => MailboxType::Imap, 'status' => MailboxStatus::Paused, 'daily_limit' => 30]),
        ];
    }

    /**
     * @param  array<string, Lead>  $leads
     * @param  array<string, Mailbox>  $mailboxes
     */
    private function messages(array $leads, array $mailboxes): void
    {
        $rows = [
            ['linde', 'com', 1, 'Jullie site op een telefoon', "Hoi,\n\nIk keek net op tandartsdelinde.nl op mijn telefoon en de tekst valt buiten het scherm. Het copyright onderaan staat nog op 2017.\n\nIk bouw praktijksites die in twee weken live staan, vaste prijs. Zal ik laten zien hoe die van jullie eruit zou zien?\n\nLoc", '2026-09-22 09:10', MessageStatus::Sent, 'thr_18c2a', null, null],
            ['mondzorg', 'com', 1, 'Laatste nieuws op jullie site is uit 2021', "Hoi,\n\nOp mondzorgzuid.nl staat het laatste nieuwsbericht in maart 2021. Zo lijkt de praktijk stil te staan, terwijl dat vast niet zo is.\n\nIk maak praktijksites waar jullie zelf in vijf minuten iets op zetten. Interesse in een voorbeeld?\n\nLoc", '2026-09-21 08:30', MessageStatus::Replied, 'thr_2f91b', "Hoi Loc, klopt, daar komen we niet aan toe. Stuur maar een voorbeeld, dan leg ik het voor aan mijn collega.\n\nGroet, Karin", '2026-09-24 14:30'],
            ['haarlem', 'com', 1, 'Wix en laadtijd', "Hoi,\n\nJullie site draait op Wix en doet er op mobiel ruim zes seconden over. Patiënten haken dan af voordat de pagina er staat.\n\nIk bouw snelle praktijksites in twee weken. Zal ik een snelheidsrapport sturen?\n\nLoc", '2026-09-20 09:00', MessageStatus::Sent, 'thr_77a0c', null, null],
            ['haarlem', 'nl', 2, 'Re: Wix en laadtijd', "Hoi,\n\nNog even hierop terugkomen. Ik heb het rapport alvast gemaakt, het staat klaar. Zal ik het sturen?\n\nLoc", '2026-09-25 08:00', MessageStatus::Sent, 'thr_77a0c', null, null],
            ['fysiocentrum', 'nl', 1, 'Jullie site is onleesbaar op een telefoon', "Hoi,\n\nfysiocentrum.nl mist een viewport-tag, waardoor de site op een telefoon piepklein wordt weergegeven. De meeste mensen zoeken een fysio op hun telefoon.\n\nIk doe gratis een snelheids- en mobielcheck. Zal ik die sturen?\n\nLoc", '2026-09-23 11:00', MessageStatus::Sent, 'thr_9b3d1', null, null],
            ['fysiofit', 'com', 1, 'Nieuws stopt in 2020', "Hoi,\n\nHet laatste bericht op fysiofitkralingen.nl is uit november 2020 en het copyright staat op 2018.\n\nIk doe gratis een check van laadtijd en mobiel. Zal ik hem sturen?\n\nLoc", '2026-09-03 09:00', MessageStatus::Replied, 'thr_c41e8', 'Ja graag! We wilden er al een tijd iets aan doen.', '2026-09-04 10:15'],
            ['vandijk', 'com', 1, 'Site zonder https', "Hoi,\n\nfysiovandijk.nl laadt zonder https, waardoor Chrome \"niet veilig\" toont naast jullie naam.\n\nIk doe gratis een snelheids- en mobielcheck. Interesse?\n\nLoc", '2026-09-12 09:00', MessageStatus::Sent, 'thr_e02f7', null, null],
            ['vandijk', 'com', 2, 'Re: Site zonder https', "Hoi,\n\nNog even hierop terugkomen. De check kost jullie niks. Zal ik hem sturen?\n\nLoc", '2026-09-19 10:00', MessageStatus::Replied, 'thr_e02f7', 'Geen interesse, bedankt.', '2026-09-19 15:40'],
            ['rugkliniek', 'nl', 1, 'Jullie site op mobiel', "Hoi,\n\nrugkliniekdelft.nl heeft geen mobiele weergave.\n\nIk doe gratis een snelheids- en mobielcheck. Zal ik hem sturen?\n\nLoc", '2026-09-22 09:00', MessageStatus::Bounced, 'thr_5d7a9', null, null],
            ['bos', 'com', 1, 'Online afspraken', "Hoi,\n\nOp tandartsbos.nl kun je niet online een afspraak maken, terwijl de site verder netjes is.\n\nIk bouw praktijksites met online afspraken in twee weken. Zal ik een voorbeeld sturen?\n\nLoc", '2026-09-25 09:00', MessageStatus::Sent, 'thr_a8b12', null, null],
        ];

        foreach ($rows as [$lead, $mailbox, $step, $subject, $body, $sentAt, $status, $thread, $replyBody, $replyAt]) {
            Message::query()->create([
                'lead_id' => $leads[$lead]->id,
                'mailbox_id' => $mailboxes[$mailbox]->id,
                'step' => $step,
                'subject' => $subject,
                'body' => $body,
                'status' => $status,
                'thread_id' => $thread,
                'sent_at' => $sentAt,
                'reply_body' => $replyBody,
                'reply_received_at' => $replyAt,
            ]);
        }
    }
}
