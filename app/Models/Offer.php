<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What we propose to a niche; every mail a lead gets comes from its sequence.
 *
 * @property int $id
 * @property int $niche_id
 * @property string $name
 * @property string|null $description
 * @property OfferStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['niche_id', 'name', 'description', 'status'])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => OfferStatus::Idea->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
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
     * @return HasMany<SequenceStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(SequenceStep::class)->orderBy('step');
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
