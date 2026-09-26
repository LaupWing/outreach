<?php

namespace App\Models;

use App\Enums\ScrapeRunStatus;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\ScrapeRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One Google Places search: what was asked, what it cost and what came out.
 *
 * @property int $id
 * @property int $user_id
 * @property int $niche_id
 * @property string $query
 * @property string $place
 * @property ScrapeRunStatus $status
 * @property int $requests
 * @property int $found
 * @property int $with_email
 * @property int $blocked
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'niche_id', 'query', 'place', 'status', 'requests', 'found', 'with_email', 'blocked', 'started_at', 'finished_at'])]
class ScrapeRun extends Model
{
    /** @use HasFactory<ScrapeRunFactory> */
    use BelongsToUser, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ScrapeRunStatus::Queued->value,
        'requests' => 0,
        'found' => 0,
        'with_email' => 0,
        'blocked' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScrapeRunStatus::class,
            'requests' => 'integer',
            'found' => 'integer',
            'with_email' => 'integer',
            'blocked' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
