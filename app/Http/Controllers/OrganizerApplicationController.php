<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizerApplicationRequest;
use App\Models\User;
use App\Support\PlatformAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrganizerApplicationController extends Controller
{
    public function store(OrganizerApplicationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            if ($user->is_organizer || $user->is_admin) {
                throw ValidationException::withMessages(['application' => 'Esta conta já pode organizar campanhas.']);
            }
            $application = $user->organizerApplication()->first();
            if ($application && $application->status !== 'rejected') {
                throw ValidationException::withMessages(['application' => 'Já existe uma candidatura pendente para esta conta.']);
            }
            $application ??= $user->organizerApplication()->make();
            $application->fill(['full_name' => $request->validated('fullName'), 'phone' => $request->validated('phone'), 'location' => $request->validated('location'), 'motivation' => $request->validated('motivation')]);
            $application->status = 'pending';
            $application->reviewed_by = null;
            $application->reviewed_at = null;
            $application->decision_reason = null;
            $application->save();
            PlatformAudit::record($user, 'organizer.application.submitted', $application);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Candidatura enviada para avaliação. Ainda mantém apenas os acessos actuais.']);

        return to_route('consumidor.candidatura');
    }
}
