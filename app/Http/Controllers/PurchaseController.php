<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseActionRequest;
use App\Models\Campaign;
use App\Models\PaymentAttempt;
use App\Models\PurchaseOrder;
use App\Models\TestGatewayTransaction;
use App\Support\CollectivePurchase;
use App\Support\PurchaseData;
use App\Support\TestPaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function checkout(Request $request, PurchaseOrder $order, PurchaseData $data): Response|RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $purchase = $data->consumer($order->campaign, $request->user());
        $purchase['preferredDelivery'] = $order->accepted_at !== null ? $order->delivery_method : ($request->query('delivery') === 'entrega' && $purchase['deliveryQuote'] !== null ? 'entrega' : 'recolha');
        if ($purchase['view']['isFinal'] || in_array($purchase['view']['code'], ['ready', 'refund_pending', 'issue'], true)) {
            return to_route('consumidor.acompanhamento.show', ['id' => $order->campaign_id]);
        }

        return Inertia::render('consumidor/checkout', ['purchase' => $purchase, 'retry' => $request->boolean('retry')]);
    }

    public function store(PurchaseActionRequest $request, Campaign $campaign, CollectivePurchase $purchases): RedirectResponse
    {
        $order = $purchases->issueOrder($request->user(), $campaign, $request->validated());
        if ($request->has('paymentMethod')) {
            $purchases->pay($request->user(), $order, $request->validated());
        }

        return back();
    }

    public function pay(PurchaseActionRequest $request, PurchaseOrder $order, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->pay($request->user(), $order, $request->validated());

        return back();
    }

    public function reconcile(PurchaseActionRequest $request, PurchaseOrder $order, CollectivePurchase $purchases): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->is_admin, 404);
        foreach (PaymentAttempt::query()->where('purchase_order_id', $order->id)->get() as $attempt) {
            $purchases->reconcile($attempt);
        }
        $purchases->processRefunds();

        return back();
    }

    public function simulate(PurchaseActionRequest $request, PurchaseOrder $order, TestPaymentGateway $gateway, CollectivePurchase $purchases): RedirectResponse
    {
        abort_unless($gateway->available(), 404);
        abort_unless($order->user_id === $request->user()->id, 404);
        $attempt = PaymentAttempt::query()->where('purchase_order_id', $order->id)->latest('id')->firstOrFail();
        $gateway->simulate($attempt->reference, (string) $request->validated('status'));
        $purchases->reconcile($attempt);
        $purchases->processRefunds();

        return back();
    }

    public function issue(PurchaseActionRequest $request, PurchaseOrder $order, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->reportIssue($request->user(), $order, $request->validated());

        return back();
    }

    public function prepare(PurchaseActionRequest $request, PurchaseOrder $order, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->prepareOrder($request->user(), $order);

        return back();
    }

    public function handoff(PurchaseActionRequest $request, PurchaseOrder $order, CollectivePurchase $purchases): RedirectResponse
    {
        $purchases->handoff($request->user(), $order, (string) $request->validated('code'));

        return back();
    }

    public function webhook(Request $request, TestPaymentGateway $gateway, CollectivePurchase $purchases): JsonResponse
    {
        abort_unless($gateway->available(), 404);
        $secret = (string) config('purchases.test_webhook_secret');
        abort_unless($secret !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), (string) $request->header('X-Junta-Test-Signature')), 403);
        $data = $request->validate(['reference' => ['required', 'string'], 'amount_cents' => ['required', 'integer', 'min:1'], 'currency' => ['required', 'string', 'size:3'], 'status' => ['required', 'in:paid,failed']]);
        $attempt = PaymentAttempt::query()->where('reference', $data['reference'])->firstOrFail();
        DB::transaction(function () use ($data): void {
            $transaction = TestGatewayTransaction::query()->where('reference', $data['reference'])->lockForUpdate()->firstOrFail();
            if ($transaction->status === 'pending') {
                $transaction->status = $data['status'];
                $transaction->amount_cents = $data['amount_cents'];
                $transaction->currency = $data['currency'];
                $transaction->settled_at = now();
                $transaction->save();
            }
        });
        $purchases->reconcile($attempt);
        $purchases->processRefunds();

        return response()->json(['received' => true]);
    }
}
