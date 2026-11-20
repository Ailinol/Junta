<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Support\Money;
use App\Support\PlatformPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function index(PlatformPricing $pricing): Response
    {
        $bps = $pricing->commissionBps();
        $campaigns = Campaign::query()->where('status', 'proposta_selecionada')->where('purchase_phase', 'interest')
            ->with(['proposals', 'participations'])->orderBy('id')->paginate(15)->through(fn (Campaign $campaign): array => [
                'id' => $campaign->id, 'title' => $campaign->title, 'unit' => $campaign->unit,
                'location' => $campaign->location, 'pricing' => $pricing->preview($campaign, $bps),
            ]);

        return Inertia::render('admin/tarifas', ['commissionPercent' => Money::decimal($bps), 'campaigns' => $campaigns]);
    }

    public function update(Request $request, PlatformPricing $pricing): RedirectResponse
    {
        $data = $request->validate(['commissionPercent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2']]);
        $pricing->updateCommission($request->user(), (string) $data['commissionPercent']);

        return back();
    }

    public function delivery(Request $request, Campaign $campaign, PlatformPricing $pricing): RedirectResponse
    {
        $data = $request->validate([
            'deliveryTotal' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'reference' => ['required', 'string', 'max:120'],
        ]);
        $pricing->quoteDelivery($request->user(), $campaign, Money::cents((string) $data['deliveryTotal']), $data['reference']);

        return back();
    }
}
