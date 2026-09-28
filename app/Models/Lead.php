<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A business we reach out to.
 *
 * @property int $id
 * @property int $user_id
 * @property int $niche_id
 * @property int|null $offer_id
 * @property int|null $scrape_run_id
 * @property string $company
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $city
 * @property LeadStatus $status
 * @property LeadSource $source
 * @property string|null $hook
 * @property array{copyright_year: int|null, viewport: bool|null, software: list<string>, last_news_at: string|null, blocked: bool, javascript_only: bool}|null $signals
 * @property Carbon|null $last_contact_at
 * @property Carbon|null $next_action_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'niche_id', 'offer_id', 'scrape_run_id', 'company', 'email', 'phone', 'website', 'city', 'status', 'source', 'hook', 'signals', 'site_text', 'facts', 'last_contact_at', 'next_action_at'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToUser, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => LeadStatus::New->value,
        'source' => LeadSource::Manual->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => LeadSource::class,
            'signals' => 'array',
            'facts' => 'array',
            'last_contact_at' => 'datetime',
            'next_action_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Niche, $this>
     */
    public function niche(): BelongsTo
    {
        return $this->belongsTo(Niche::class);
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * @return BelongsTo<ScrapeRun, $this>
     */
    public function scrapeRun(): BelongsTo
    {
        return $this->belongsTo(ScrapeRun::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('step');
    }

    /**
     * @return HasMany<LeadNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }
}
