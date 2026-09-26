<?php

namespace App\Http\Requests\Mailboxes;

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
     * Every box is IMAP/SMTP with an app password; Gmail is just a preset of hosts.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'email', 'max:255', Rule::unique(Mailbox::class)->where('user_id', $this->user()?->id)],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:200'],
            'warm_up' => ['required', 'boolean'],
        ];
    }
}
