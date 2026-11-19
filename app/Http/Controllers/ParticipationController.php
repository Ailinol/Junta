<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParticipationRequest;
use App\Models\Campaign;
use App\Models\Participation;
use App\Support\PlatformAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ParticipationController extends Controller
{
    public function update(ParticipationRequest $request, Participation $participation): RedirectResponse
    {
        $this->change($request, $participation, false);

        return back();
    }

    public function destroy(Request $request, Participation $participation): RedirectResponse
    {
        $this->change($request, $participation, true);

        return back();
    }

    private function change(Request $request, Participation $participation, bool $cancel): void
    {
        abort_unless($participation->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($request, $participation, $cancel): void {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($participation->campaign_id);
            if ($campaign->status !== 'ativa' || ! $campaign->deadline->isFuture() || $campaign->purchase_phase !== 'interest') {
                throw ValidationException::withMessages(['quantity' => 'A fase de interesse está fechada.']);
            }
            $locked = Participation::query()->lockForUpdate()->findOrFail($participation->id);
            if ($cancel) {
                $locked->status = 'cancelada';
            } else {
                $locked->quantity = $request->integer('quantity');
                $locked->status = 'pendente';
            }
            $locked->save();
            PlatformAudit::record($request->user(), $cancel ? 'participation.cancelled' : 'participation.updated', $locked, ['quantity' => $locked->quantity]);
        }, 5);
    }

    public function store(ParticipationRequest $request, Campaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($request, $campaign): void {
            $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if ($locked->status !== 'ativa' || ! $locked->deadline->isFuture() || $locked->purchase_phase !== 'interest' || $locked->purchase_config !== null) {
                throw ValidationException::withMessages(['quantity' => 'Esta campanha já não aceita participações.']);
            }
            $existing = $locked->participations()->where('user_id', $request->user()->id)->lockForUpdate()->first();
            if ($existing && $existing->status !== 'cancelada') {
                throw ValidationException::withMessages(['quantity' => 'Já participa nesta campanha. Consulte o acompanhamento.']);
            }
            if ($existing) {
                $existing->quantity = $request->integer('quantity');
                $existing->notes = $request->validated('notes');
                $existing->status = 'pendente';
                $existing->save();
                PlatformAudit::record($request->user(), 'participation.reactivated', $existing, ['quantity' => $existing->quantity]);

                return;
            }
            $participation = $locked->participations()->make(['quantity' => $request->integer('quantity'), 'notes' => $request->validated('notes')]);
            $participation->user_id = $request->user()->id;
            $participation->status = 'pendente';
            $participation->save();
            PlatformAudit::record($request->user(), 'participation.created', $participation);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Participação registada. Isto não confirma pagamento.']);

        return back();
    }
}
