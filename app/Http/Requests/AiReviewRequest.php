<?php

namespace App\Http\Requests;

use App\Support\CommercialDate;
use App\Support\PlatformRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiReviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['deadline', 'validUntil', 'reservedUntil'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => CommercialDate::normalize($this->input($field))]);
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can($this->routeIs('ai.campaign') ? 'organize-campaigns' : 'supply-campaigns') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string,array<mixed>>
     */
    public function rules(): array
    {
        $common = ['deliveryMode' => ['required', Rule::in(PlatformRules::DELIVERY_MODES)]];
        if ($this->routeIs('ai.campaign')) {
            return [...$common,
                'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'],
                'unit' => ['required', 'string', 'max:60'], 'targetQuantity' => ['required', 'integer', 'between:1,2147483647'],
                'deadline' => ['required', 'date', 'after:now'], 'context' => ['nullable', 'string', 'max:1000'],
            ];
        }

        return [...$common,
            'campaignId' => ['required', 'integer', 'exists:campaigns,id'],
            'unitPrice' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'gt:0'],
            'availableQuantity' => ['required', 'integer', 'between:1,2147483647'],
            'minimumQuantity' => ['nullable', 'integer', 'min:1', 'lte:availableQuantity'],
            'reservedUntil' => ['nullable', 'date', 'after:now'],
            'transportIncluded' => ['required', 'boolean'],
            'transportUnitPrice' => ['nullable', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/D', 'numeric', 'min:0'],
            'deliveryDays' => ['required', 'integer', 'between:1,2147483647'],
            'validUntil' => ['required', 'date', 'after:now'], 'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
