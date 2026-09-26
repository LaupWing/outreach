<?php

namespace App\Http\Requests\ScrapeRuns;

use App\Models\Niche;
use App\Models\ScrapeRun;
use App\Support\PlacesBudget;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreScrapeRunRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ScrapeRun::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Either an existing niche or the name of a new one; `pages` is one Places
     * request each, so it is also the cost of the run.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:255'],
            'place' => ['required', 'string', 'max:255'],
            'niche_id' => ['required_without:new_niche', 'nullable', 'integer', Rule::exists(Niche::class, 'id')],
            'new_niche' => ['required_without:niche_id', 'nullable', 'string', 'max:255'],
            'pages' => ['required', 'integer', 'min:1', 'max:3'],
        ];
    }

    /**
     * Refuse a run that would spend more than what is still free this month.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('pages')) {
                    return;
                }

                $left = PlacesBudget::left();

                if ($this->integer('pages') > $left) {
                    $validator->errors()->add('pages', __('Only :left free requests left this month.', ['left' => $left]));
                }
            },
        ];
    }
}
