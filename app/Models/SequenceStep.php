<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Database\Factories\SequenceStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One mail in an offer's sequence; the body carries {{placeholders}} for the hook.
 *
 * @property int $id
 * @property int $user_id
 * @property int $offer_id
 * @property int $step
 * @property int $days_after_previous
 * @property string $subject
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'offer_id', 'step', 'days_after_previous', 'subject', 'body'])]
class SequenceStep extends Model
{
    /** @use HasFactory<SequenceStepFactory> */
    use BelongsToUser, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'days_after_previous' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
