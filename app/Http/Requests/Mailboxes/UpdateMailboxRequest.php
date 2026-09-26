<?php

namespace App\Http\Requests\Mailboxes;

use App\Enums\MailboxStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMailboxRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('mailbox')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Partial: only the fields sent change. An empty password keeps the current one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(MailboxStatus::class)],
            'daily_limit' => ['sometimes', 'required', 'integer', 'min:1', 'max:200'],
            'warm_up' => ['sometimes', 'required', 'boolean'],
            'imap_host' => ['sometimes', 'required', 'string', 'max:255'],
            'imap_port' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
            'smtp_host' => ['sometimes', 'required', 'string', 'max:255'],
            'smtp_port' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
