<?php

namespace App\Http\Requests;

use App\Support\ParticipationValidation;
use Illuminate\Foundation\Http\FormRequest;

class ParticipationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [...ParticipationValidation::rules(), 'notes' => ['nullable', 'string', 'max:3000']];
    }

    public function messages(): array
    {
        return ParticipationValidation::messages();
    }
}
