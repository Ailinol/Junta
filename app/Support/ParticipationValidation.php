<?php

namespace App\Support;

class ParticipationValidation
{
    /** @return array{quantity:list<string>} */
    public static function rules(): array
    {
        return ['quantity' => ['bail', 'required', 'integer', 'min:1', 'max:2147483647']];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return ['quantity.required' => 'A quantidade deve ser um número inteiro maior que zero.', 'quantity.integer' => 'A quantidade deve ser um número inteiro maior que zero.', 'quantity.min' => 'A quantidade deve ser um número inteiro maior que zero.'];
    }
}
