<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseActionRequest;
use App\Models\Campaign;
use App\Support\CollectivePurchase;
use App\Support\OrganizerData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CampaignPurchaseController extends Controller
{
    public function show(Request $request, Campaign $campaign, OrganizerData $organizer): Response
    {
        Gate::authorize('manage-campaign', $campaign);

        return Inertia::render('organizador/campanhas/compra', $organizer->summary($request, $campaign));
    }

    public function closeInterest(PurchaseActionRequest $request, Campaign $campaign, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->closeInterest($request->user(), $campaign);

        return back();
    }

    public function open(PurchaseActionRequest $request, Campaign $campaign, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->openPayments($request->user(), $campaign, $request->validated());

        return back();
    }

    public function close(PurchaseActionRequest $request, Campaign $campaign, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->closeCampaign($campaign, $request->user());

        return back();
    }

    public function receive(PurchaseActionRequest $request, Campaign $campaign, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->receiveSupply($request->user(), $campaign, $request->validated());

        return back();
    }

    public function cancel(PurchaseActionRequest $request, Campaign $campaign, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->closeCampaign($campaign, $request->user(), true, (string) $request->validated('reason'));

        return back();
    }

    public function complete(PurchaseActionRequest $request, Campaign $campaign, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->complete($request->user(), $campaign);

        return back();
    }
}
