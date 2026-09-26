<?php

namespace App\Models;

use App\Enums\MailboxStatus;
use App\Enums\MailboxType;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An address we send from over SMTP and read over IMAP. The sender picks whichever
 * box still has room today.
 *
 * @property int $id
 * @property int $user_id
 * @property string $address
 * @property MailboxType $type
 * @property MailboxStatus $status
 * @property int $daily_limit
 * @property int $sent_today
 * @property Carbon|null $sent_today_on
 * @property Carbon|null $warm_up_started_at
 * @property string $imap_host
 * @property int $imap_port
 * @property string $smtp_host
 * @property int $smtp_port
 * @property string $username
 * @property string $password
 * @property Carbon|null $connection_checked_at
 * @property string|null $connection_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'address', 'type', 'status', 'daily_limit', 'sent_today', 'sent_today_on', 'warm_up_started_at', 'imap_host', 'imap_port', 'smtp_host', 'smtp_port', 'username', 'password', 'connection_checked_at', 'connection_error'])]
#[Hidden(['password'])]
class Mailbox extends Model
{
    /** @use HasFactory<MailboxFactory> */
    use BelongsToUser, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => MailboxStatus::Active->value,
        'sent_today' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MailboxType::class,
            'status' => MailboxStatus::class,
            'daily_limit' => 'integer',
            'sent_today' => 'integer',
            'sent_today_on' => 'date',
            'warm_up_started_at' => 'datetime',
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
            'password' => 'encrypted',
            'connection_checked_at' => 'datetime',
        ];
    }

    /**
     * Where a paused box goes when it resumes: back to warming up while the
     * two-week ramp is still running, otherwise straight to active.
     */
    public function statusAfterResume(): MailboxStatus
    {
        $stillWarming = $this->warm_up_started_at !== null
            && $this->warm_up_started_at->greaterThan(now()->subDays(14));

        return $stillWarming ? MailboxStatus::WarmingUp : MailboxStatus::Active;
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
