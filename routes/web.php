<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LeadBulkController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadFactController;
use App\Http\Controllers\LeadMessageController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\MailboxConnectionController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MessageReplyController;
use App\Http\Controllers\NicheController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ScrapeRunController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SequenceStepController;
use App\Http\Middleware\EnsureOnboarded;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Onboarding and the mailbox endpoints stay reachable before the account is set up.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/key', [OnboardingController::class, 'storeKey'])->name('onboarding.key');
    Route::post('onboarding/finish', [OnboardingController::class, 'finish'])->name('onboarding.finish');

    Route::resource('mailboxes', MailboxController::class)->only(['store', 'update', 'destroy']);
    Route::post('mailboxes/{mailbox}/test', MailboxConnectionController::class)->name('mailboxes.test');
});

Route::middleware(['auth', 'verified', EnsureOnboarded::class])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('inbox', InboxController::class)->name('inbox.index');
    Route::get('search', SearchController::class)->name('search');

    Route::resource('niches', NicheController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::resource('offers', OfferController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('offers/{offer}/steps', [SequenceStepController::class, 'store'])->name('offers.steps.store');
    Route::put('offers/{offer}/steps/order', [SequenceStepController::class, 'reorder'])->name('offers.steps.reorder');
    Route::patch('steps/{step}', [SequenceStepController::class, 'update'])->name('steps.update');
    Route::delete('steps/{step}', [SequenceStepController::class, 'destroy'])->name('steps.destroy');

    Route::resource('leads', LeadController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('leads/bulk', LeadBulkController::class)->name('leads.bulk');
    Route::post('leads/{lead}/notes', [LeadNoteController::class, 'store'])->name('leads.notes.store');
    Route::patch('leads/{lead}/facts', [LeadFactController::class, 'update'])->name('leads.facts.update');
    Route::post('leads/{lead}/messages', [LeadMessageController::class, 'store'])->name('leads.messages.store');

    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('messages/{message}/reply', [MessageReplyController::class, 'store'])->name('messages.reply.store');

    Route::resource('mailboxes', MailboxController::class)->only(['index']);

    Route::resource('scrape', ScrapeRunController::class)->only(['index', 'store'])->parameters(['scrape' => 'scrapeRun']);
});

require __DIR__.'/settings.php';
