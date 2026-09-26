<?php

namespace App\Http\Requests\Offers;

use App\Enums\OfferStatus;
use App\Models\Niche;
use App\Models\Offer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An offer needs a niche: either an existing `niche_id` or a `new_niche` name to create one.
 * The first mail can come along as `first_step`.
 */
class StoreOfferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Offer::class) ?? false;
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
            'niche_id' => ['required_without:new_niche', 'nullable', 'integer', Rule::exists(Niche::class, 'id')->where('user_id', $this->user()?->id)],
            'new_niche' => ['required_without:niche_id', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(OfferStatus::class)],
            'first_step' => ['sometimes', 'array:subject,body'],
            'first_step.subject' => ['required_with:first_step', 'string', 'max:255'],
            'first_step.body' => ['required_with:first_step', 'string', 'max:10000'],
        ];
    }
}
