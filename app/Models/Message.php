<?php

namespace App\Models;

use App\Enums\MessageStatus;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One mail to a lead, with the reply on the same row once one comes in.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lead_id
 * @property int $mailbox_id
 * @property int $step
 * @property string $subject
 * @property string $body
 * @property MessageStatus $status
 * @property string|null $thread_id
 * @property string|null $message_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $send_after
 * @property string|null $error
 * @property string|null $reply_body
 * @property Carbon|null $reply_received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read array{body: string, received_at: string}|null $reply
 */
#[Fillable(['user_id', 'lead_id', 'mailbox_id', 'step', 'subject', 'body', 'status', 'thread_id', 'message_id', 'sent_at', 'send_after', 'error', 'reply_body', 'reply_received_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToUser, HasFactory;

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = ['reply'];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => MessageStatus::Draft->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => 'integer',
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
            'send_after' => 'datetime',
            'reply_received_at' => 'datetime',
        ];
    }

    /**
     * The reply as one object, the shape the UI reads.
     *
     * @return Attribute<array{body: string, received_at: string}|null, never>
     */
    protected function reply(): Attribute
    {
        return Attribute::get(fn (): ?array => $this->reply_body === null ? null : [
            'body' => $this->reply_body,
            'received_at' => $this->reply_received_at?->toJSON(),
        ]);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }
}
