<?php

namespace App\Http\Requests\SequenceSteps;

use App\Models\Offer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The new order of an offer's steps: every step id of that offer, exactly once.
 */
class ReorderSequenceStepsRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['order', 'order.*'])) {
                    return;
                }

                /** @var Offer $offer */
                $offer = $this->route('offer');

                $expected = $offer->steps()->pluck('id')->sort()->values()->all();
                $given = collect($this->input('order'))->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

                if ($expected !== $given) {
                    $validator->errors()->add('order', 'The order must contain every step of this offer exactly once.');
                }
            },
        ];
    }

    /**
     * The step ids in their new order.
     *
     * @return list<int>
     */
    public function orderedIds(): array
    {
        return array_map(fn (mixed $id): int => (int) $id, $this->validated('order'));
    }
}
