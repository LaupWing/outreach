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
     * Partial: only the fields sent change. New IMAP credentials are validated but
     * not stored yet; that comes with the mail connection work.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(MailboxStatus::class)],
            'daily_limit' => ['sometimes', 'required', 'integer', 'min:1', 'max:200'],
            'warm_up' => ['sometimes', 'required', 'boolean'],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
