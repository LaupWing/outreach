<?php

namespace App\Models;

use App\Enums\NicheStatus;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\NicheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A market we test offers on.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property NicheStatus $status
 * @property string|null $why
 * @property string|null $findings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'status', 'why', 'findings'])]
class Niche extends Model
{
    /** @use HasFactory<NicheFactory> */
    use BelongsToUser, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => NicheStatus::Idea->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NicheStatus::class,
        ];
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return HasMany<ScrapeRun, $this>
     */
    public function scrapeRuns(): HasMany
    {
        return $this->hasMany(ScrapeRun::class);
    }
}
