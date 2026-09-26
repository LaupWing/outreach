<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GoogleUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * A Places API key: Google's keys start with "AIza" and are 39 characters.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'google_places_key' => ['required', 'string', 'regex:/^AIza[0-9A-Za-z_-]{35}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'google_places_key.regex' => 'That does not look like a Google API key. They start with "AIza" and are 39 characters long.',
        ];
    }
}
