<?php

namespace App\Http\Requests\Messages;

use App\Enums\MailboxStatus;
use App\Models\Mailbox;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeadMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A null mailbox means "auto": the sender picks any box with room today. A
     * null step is a free-form mail outside the sequence.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'mailbox_id' => ['nullable', 'integer', Rule::exists(Mailbox::class, 'id')->where('user_id', $this->user()?->id)],
            'step' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * A box has to be able to send today, whether chosen by hand or picked for us.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('mailbox_id')) {
                    return;
                }

                if ($this->mailbox() === null) {
                    $validator->errors()->add(
                        'mailbox_id',
                        $this->filled('mailbox_id')
                            ? __('This mailbox is paused or has hit its limit for today.')
                            : __('Every mailbox is full for today; try again tomorrow or raise a limit.'),
                    );
                }
            },
        ];
    }

    /**
     * The chosen box when it can still send, or the first box with room when none was chosen.
     */
    public function mailbox(): ?Mailbox
    {
        return $this->user()->mailboxes()
            ->when($this->filled('mailbox_id'), fn ($query) => $query->whereKey($this->integer('mailbox_id')))
            ->where('status', '!=', MailboxStatus::Paused)
            ->whereColumn('sent_today', '<', 'daily_limit')
            ->orderBy('id')
            ->first();
    }
}
