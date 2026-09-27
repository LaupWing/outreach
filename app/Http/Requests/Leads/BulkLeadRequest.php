<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Offer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * One action on a set of the user's own leads; ids of other accounts fail validation.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', Rule::exists(Lead::class, 'id')->where('user_id', $userId)],
            'action' => ['required', Rule::in(['assign_offer', 'set_status', 'delete'])],
            'offer_id' => ['required_if:action,assign_offer', 'nullable', 'integer', Rule::exists(Offer::class, 'id')->where('user_id', $userId)],
            'status' => ['required_if:action,set_status', Rule::enum(LeadStatus::class)],
        ];
    }
}
