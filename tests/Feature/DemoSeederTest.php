<?php

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\ScrapeRun;
use App\Models\SequenceStep;
use Database\Seeders\DemoSeeder;

test('the demo seeder fills every table the screens read', function () {
    $this->seed(DemoSeeder::class);

    expect(Niche::query()->count())->toBe(4)
        ->and(Offer::query()->count())->toBe(3)
        ->and(SequenceStep::query()->count())->toBe(5)
        ->and(ScrapeRun::query()->count())->toBe(7)
        ->and(Lead::query()->count())->toBe(12)
        // The onboarded test account already has a box, so the demo sends from that one.
        ->and(Mailbox::query()->count())->toBe(1)
        ->and(Message::query()->count())->toBe(10);
});

test('a replied message exposes its reply as one object', function () {
    $this->seed(DemoSeeder::class);

    $lead = Lead::query()->where('company', 'Mondzorg Zuid')->firstOrFail();

    expect($lead->status)->toBe(LeadStatus::Replied)
        ->and($lead->messages->first()->reply['body'])->toContain('Karin');
});
