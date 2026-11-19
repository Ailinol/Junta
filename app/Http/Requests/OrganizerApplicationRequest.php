<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrganizerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['fullName' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:40'], 'location' => ['required', 'string', 'max:255'], 'motivation' => ['required', 'string', 'max:3000']];
    }

    public function messages(): array
    {
        return ['required' => 'Preencha este campo para enviar a candidatura.', 'motivation.max' => 'A motivação deve ter no máximo 3000 caracteres.'];
    }
}
