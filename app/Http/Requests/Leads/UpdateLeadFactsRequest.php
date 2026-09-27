<?php

namespace App\Http\Requests\Leads;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadFactsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    /**
     * The whole facts object; an empty or null value drops that fact.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facts' => ['present', 'array'],
            'facts.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
