<?php

namespace App\Http\Requests\Offers;

use App\Enums\OfferStatus;
use App\Models\Niche;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial updates: the panel's Activate/Stop buttons send only `status`; the edit
 * dialog can move the offer to another niche or to a `new_niche` it creates.
 */
class UpdateOfferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('offer')) ?? false;
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
            'niche_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Niche::class, 'id')->where('user_id', $this->user()?->id)],
            'new_niche' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // {tag: what it should say}; tag names as they appear inside {{ }}.
            'placeholders' => ['sometimes', 'nullable', 'array'],
            'placeholders.*' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', Rule::enum(OfferStatus::class)],
        ];
    }
}
