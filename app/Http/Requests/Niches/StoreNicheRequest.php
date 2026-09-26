<?php

namespace App\Http\Requests\Niches;

use App\Enums\NicheStatus;
use App\Models\Niche;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNicheRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Niche::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(NicheStatus::class)],
            'why' => ['nullable', 'string', 'max:5000'],
            'findings' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
