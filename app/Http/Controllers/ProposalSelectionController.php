<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Proposal;
use App\Support\ProposalSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProposalSelectionController extends Controller
{
    public function store(Request $request, Campaign $campaign, Proposal $proposal, ProposalSelection $selection): RedirectResponse
    {
        $selection->select($request->user(), $campaign, $proposal);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proposta seleccionada. A compra ainda não está concluída.']);

        return back();
    }
}
