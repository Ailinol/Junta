<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['phone' => ['nullable', 'string', 'max:40'], 'city' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:500'], 'notifProposal' => ['required', 'boolean'], 'notifReminder' => ['required', 'boolean'], 'notifPickup' => ['required', 'boolean']];
    }
}
