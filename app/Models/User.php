<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $google_places_key
 * @property string $send_timezone
 * @property int $send_from
 * @property int $send_until
 * @property bool $send_weekdays_only
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'google_places_key'])]
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @return HasMany<Niche, $this> */
    public function niches(): HasMany
    {
        return $this->hasMany(Niche::class);
    }

    /** @return HasMany<Offer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /** @return HasMany<SequenceStep, $this> */
    public function sequenceSteps(): HasMany
    {
        return $this->hasMany(SequenceStep::class);
    }

    /** @return HasMany<ScrapeRun, $this> */
    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(ScrapeRun::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<Mailbox, $this> */
    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<LeadNote, $this> */
    public function leadNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    /**
     * Whether the account can run the app: a Places key and at least one mailbox.
     */
    public function isOnboarded(): bool
    {
        return $this->google_places_key !== null && $this->mailboxes()->exists();
    }

    /**
     * The model's default values for attributes.
     *
     * Also the defaults on the users table; here so a fresh model, like one a
     * factory just created, sends in the same window without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'send_timezone' => 'Europe/Amsterdam',
        'send_from' => 9,
        'send_until' => 17,
        'send_weekdays_only' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google_places_key' => 'encrypted',
            'send_from' => 'integer',
            'send_until' => 'integer',
            'send_weekdays_only' => 'boolean',
        ];
    }
}
