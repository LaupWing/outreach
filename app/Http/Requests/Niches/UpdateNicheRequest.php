<?php

namespace App\Http\Requests\Niches;

use App\Enums\NicheStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial updates: the panel's status buttons send only `status`.
 */
class UpdateNicheRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('niche')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(NicheStatus::class)],
            'why' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'findings' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
