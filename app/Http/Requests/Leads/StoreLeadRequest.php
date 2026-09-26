<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Niche;
use App\Models\Offer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /**
     * The website is stored as a bare host; the form's URL field adds a scheme.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('website')) {
            $this->merge(['website' => preg_replace('#^https?://|/$#', '', $this->string('website')->trim()->toString())]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A lead picks an existing niche or names a new one; an offer must belong to
     * the picked niche, so a new niche cannot carry one yet.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:255'],
            'niche_id' => ['required_without:new_niche', 'nullable', 'integer', Rule::exists(Niche::class, 'id')],
            'new_niche' => ['required_without:niche_id', 'nullable', 'string', 'max:255'],
            'offer_id' => [
                'nullable',
                'integer',
                Rule::exists(Offer::class, 'id')->where('niche_id', $this->input('niche_id')),
            ],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(LeadStatus::class)->only([LeadStatus::New, LeadStatus::Emailed])],
            'hook' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
