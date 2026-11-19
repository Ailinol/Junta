<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ContactPreferencesController extends Controller
{
    public function update(ContactPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->contact_preferences = $request->validated();
        $user->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contactos e preferências guardados.']);

        return back();
    }
}
