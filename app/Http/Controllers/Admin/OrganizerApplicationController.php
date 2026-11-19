<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplicationDecisionRequest;
use App\Models\OrganizerApplication;
use App\Models\User;
use App\Notifications\OrganizerApplicationDecision;
use App\Support\PlatformAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizerApplicationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/candidaturas');
    }

    public function update(ApplicationDecisionRequest $request, OrganizerApplication $application): RedirectResponse
    {
        DB::transaction(function () use ($request, $application): void {
            $locked = OrganizerApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Esta candidatura já foi decidida.']);
            }
            $user = User::query()->lockForUpdate()->findOrFail($locked->user_id);
            $decision = $request->string('decision')->toString();
            $locked->status = $decision;
            $locked->decision_reason = $request->validated('reason');
            $locked->reviewed_by = $request->user()->id;
            $locked->reviewed_at = now();
            $locked->save();
            if ($decision === 'approved') {
                $user->is_organizer = true;
                $user->save();
            }
            PlatformAudit::record($request->user(), 'organizer.application.'.$locked->status, $locked, ['status' => $locked->status, 'reason' => $locked->decision_reason]);
            $user->notify(new OrganizerApplicationDecision($locked));
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Decisão registada e comunicada na conta do candidato.']);

        return back();
    }
}
