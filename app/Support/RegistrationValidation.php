<?php

namespace App\Support;

class RegistrationValidation
{
    /** @return array{name:list<string>,email:list<string>} */
    public static function rules(): array
    {
        return ['name' => ['bail', 'required', 'string', 'max:255'], 'email' => ['bail', 'required', 'string', 'email', 'max:255']];
    }

    /** @return array<string,string> */
    public static function messages(): array
    {
        return ['name.required' => 'Informe o seu nome.', 'email.required' => 'Introduza um email válido.', 'email.email' => 'Introduza um email válido.', 'email.string' => 'Introduza um email válido.'];
    }
}
