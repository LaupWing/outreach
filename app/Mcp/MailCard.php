<?php

namespace App\Mcp;

use App\Models\Lead;
use App\Models\Message;
use App\Models\User;

/**
 * The structured content the mail card app renders, for a preview or a message.
 */
class MailCard
{
    /**
     * @param  list<string>  $missing
     * @param  array<string, mixed>  $sendArguments
     * @return array<string, mixed>
     */
    public static function preview(User $user, Lead $lead, ?string $mailbox, string $subject, string $body, array $missing, string $sendTool, array $sendArguments): array
    {
        return [
            'status' => 'preview',
            'status_label' => 'Preview',
            'lead' => ['id' => $lead->id, 'company' => $lead->company, 'email' => $lead->email],
            'mailbox' => $mailbox,
            'subject' => $subject,
            'body' => $body,
            'missing_tags' => $missing,
            'sends_at' => null,
            'hint' => $missing === [] ? 'Nothing is sent until you press Send.' : 'Fill the tags first (update_leads, or Edit in Snelreach).',
            'edit_url' => self::editUrl($lead),
            'send_tool' => $sendTool,
            'send_arguments' => $sendArguments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(User $user, Message $message): array
    {
        $lead = $message->lead;
        $sendsAt = $message->send_after?->setTimezone($user->send_timezone);

        return [
            ...MessageSummary::from($message),
            'status_label' => ucfirst($message->status->value),
            'lead' => ['id' => $lead->id, 'company' => $lead->company, 'email' => $lead->email],
            'mailbox' => $message->mailbox?->address,
            'missing_tags' => [],
            'sends_at' => $sendsAt === null ? null : ($sendsAt->isToday() ? 'Sends today at '.$sendsAt->format('H:i') : 'Sends '.$sendsAt->format('D j M H:i')),
            'hint' => $message->status->value === 'draft' ? 'Saved as a draft; send it with send_drafts or from the app.' : null,
            'edit_url' => self::editUrl($lead),
        ];
    }

    public static function editUrl(Lead $lead): string
    {
        return rtrim(config('app.url'), '/').'/leads?lead='.$lead->id;
    }
}
