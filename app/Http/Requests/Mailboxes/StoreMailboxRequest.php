<?php

namespace App\Http\Requests\Mailboxes;

use App\Enums\MailboxType;
use App\Models\Mailbox;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMailboxRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Mailbox::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The IMAP hosts and app password are validated so the form fails early, but
     * they are not stored yet: credentials come with the mail connection work.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $imap = $this->input('type') === MailboxType::Imap->value;

        return [
            'type' => ['required', Rule::enum(MailboxType::class)],
            'address' => ['required', 'string', 'email', 'max:255', Rule::unique(Mailbox::class)],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:200'],
            'warm_up' => ['required', 'boolean'],
            'imap_host' => [Rule::when($imap, ['required'], ['nullable']), 'string', 'max:255'],
            'smtp_host' => [Rule::when($imap, ['required'], ['nullable']), 'string', 'max:255'],
            'password' => [Rule::when($imap, ['required'], ['nullable']), 'string', 'max:255'],
        ];
    }
}
