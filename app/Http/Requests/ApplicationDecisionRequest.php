<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer-platform') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'rejected'])], 'reason' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['reason.required_if' => 'Indique o motivo da rejeição.', 'decision.in' => 'Escolha uma decisão válida.'];
    }
}
