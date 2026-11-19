<?php

namespace App\Http\Controllers;

use App\Support\PlatformRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupplierProfileController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['companyName' => ['required', 'string', 'max:255'], 'contactName' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'phone' => ['required', 'string', 'max:40'], 'location' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000']]);
        $supplier = $request->user()->supplier()->firstOrFail();
        $categories = $request->validate(['categories' => ['sometimes', 'array', 'min:1'], 'categories.*' => ['required', 'string', Rule::in(PlatformRules::CATEGORIES)]]);
        if (isset($categories['categories'])) {
            $supplier->categories = array_values(array_unique($categories['categories']));
        }
        $supplier->update(['company_name' => $data['companyName'], 'contact_name' => $data['contactName'], 'email' => $data['email'], 'phone' => $data['phone'], 'location' => $data['location'], 'description' => $data['description'] ?? null]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perfil comercial actualizado. A verificação é gerida pelo administrador.']);

        return back();
    }
}
