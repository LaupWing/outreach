<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendingUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Whole hours on a 24-hour clock; the window has to be at least an hour long.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'send_timezone' => ['required', 'timezone:all'],
            'send_from' => ['required', 'integer', 'min:0', 'max:23'],
            'send_until' => ['required', 'integer', 'min:1', 'max:24', 'gt:send_from'],
            'send_weekdays_only' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'send_until.gt' => 'The window has to close after it opens.',
        ];
    }
}
