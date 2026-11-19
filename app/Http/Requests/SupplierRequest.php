<?php

namespace App\Http\Requests;

use App\Support\PlatformRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('fornecedor.adesao.store') ? $this->user() !== null : ($this->user()?->can('administer-platform') ?? false);
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $selfService = $this->routeIs('fornecedor.adesao.store');

        return [
            'userId' => [Rule::excludeIf($selfService), 'nullable', 'integer', 'exists:users,id', Rule::unique('suppliers', 'user_id')->ignore($supplier)],
            'companyName' => ['required', 'string', 'max:255'],
            'nuit' => ['required', 'string', 'max:40', Rule::unique('suppliers', 'nuit')->ignore($supplier)],
            'contactName' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'], 'location' => ['required', 'string', 'max:255'],
            'categories' => ['required', 'array', 'min:1'], 'categories.*' => ['required', Rule::in(PlatformRules::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:5000'], 'verified' => [Rule::excludeIf($selfService), 'required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'Preencha este campo.', 'userId.unique' => 'Esta conta já está associada a outro fornecedor.', 'nuit.unique' => 'Já existe um fornecedor com este NUIT.', 'email.email' => 'Indique um email válido.'];
    }

    /** @return array<string,mixed> */
    public function supplierData(): array
    {
        $data = $this->validated();
        if ($this->routeIs('fornecedor.adesao.store')) {
            $data['userId'] = $this->user()->id;
            $data['verified'] = false;
        }

        return ['user_id' => $data['userId'] ?? null, 'company_name' => $data['companyName'], 'nuit' => $data['nuit'], 'contact_name' => $data['contactName'], 'email' => $data['email'], 'phone' => $data['phone'], 'location' => $data['location'], 'categories' => $data['categories'], 'description' => $data['description'] ?? null, 'verified' => $data['verified']];
    }
}
