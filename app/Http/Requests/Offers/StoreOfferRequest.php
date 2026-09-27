<?php

namespace App\Http\Requests\Offers;

use App\Enums\OfferStatus;
use App\Models\Niche;
use App\Models\Offer;
use App\Support\Mail\Placeholders;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // The sequence, first mail first. Optional: without mails the offer is an idea.
            'steps' => ['sometimes', 'array', 'max:20'],
            'steps.*.subject' => ['required', 'string', 'max:255'],
            'steps.*.body' => ['required', 'string', 'max:10000'],
            'steps.*.days_after_previous' => ['sometimes', 'integer', 'min:0', 'max:365'],
            // What each custom {{tag}} in the mails should say; required for every custom tag used.
            'placeholders' => ['sometimes', 'array'],
            'placeholders.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Every tag the mails use that is not a lead field needs an explanation, or
     * whoever fills it later has to guess.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $text = collect($this->input('steps', []))
                    ->map(fn (array $step) => ($step['subject'] ?? '').' '.($step['body'] ?? ''))
                    ->implode(' ');

                $custom = array_diff(Placeholders::tagsIn($text), Placeholders::BUILT_IN);
                $described = array_filter($this->input('placeholders', []), fn ($value) => is_string($value) && trim($value) !== '');

                foreach ($custom as $tag) {
                    if (! isset($described[$tag])) {
                        $validator->errors()->add("placeholders.{$tag}", __('Explain what {{:tag}} should say.', ['tag' => $tag]));
                    }
                }
            },
        ];
    }
}
