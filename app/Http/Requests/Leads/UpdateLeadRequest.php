<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Niche;
use App\Models\Offer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
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
     * Partial: only the fields sent change. An offer must belong to the niche the
     * lead ends up in, whether that niche is the current one or a new pick.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Lead $lead */
        $lead = $this->route('lead');

        $nicheId = $this->filled('new_niche') ? null : $this->input('niche_id', $lead->niche_id);

        return [
            'company' => ['sometimes', 'required', 'string', 'max:255'],
            'niche_id' => ['sometimes', 'required_without:new_niche', 'nullable', 'integer', Rule::exists(Niche::class, 'id')->where('user_id', $this->user()?->id)],
            'new_niche' => ['sometimes', 'nullable', 'string', 'max:255'],
            'offer_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists(Offer::class, 'id')->where('user_id', $this->user()?->id)->where('niche_id', $nicheId),
            ],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::enum(LeadStatus::class)],
            'hook' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
