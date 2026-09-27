<?php

namespace App\Providers;

use App\Support\Enrichment\HttpSiteReader;
use App\Support\Enrichment\SiteReader;
use App\Support\Mail\ImapMailboxReader;
use App\Support\Mail\MailboxReader;
use App\Support\Mail\MailSender;
use App\Support\Mail\SmtpMailSender;
use App\Support\MailboxConnection;
use App\Support\Places\GooglePlacesSearch;
use App\Support\Places\PlacesSearch;
use App\Support\SmtpImapConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MailboxConnection::class, SmtpImapConnection::class);
        $this->app->bind(PlacesSearch::class, GooglePlacesSearch::class);
        $this->app->bind(SiteReader::class, HttpSiteReader::class);
        $this->app->bind(MailSender::class, SmtpMailSender::class);
        $this->app->bind(MailboxReader::class, ImapMailboxReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // The consent page an MCP client (Claude) lands on after login, in our own UI.
        Passport::authorizationView(fn (array $parameters) => Inertia::render('auth/oauth/authorize', [
            'client' => ['id' => $parameters['client']->id, 'name' => $parameters['client']->name],
            'authToken' => $parameters['authToken'],
            'state' => $parameters['request']->state,
            'scopes' => array_map(fn ($scope) => ['id' => $scope->id, 'description' => $scope->description], $parameters['scopes']),
        ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
