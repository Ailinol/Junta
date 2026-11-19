<?php

namespace App\Support;

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\Participation;
use App\Models\PaymentAttempt;
use App\Models\PurchaseIssue;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRefund;
use App\Models\SupplyOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CollectivePurchase
{
    public function __construct(private PaymentGateway $gateway, private PlatformNotifier $notifier) {}

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['purchase' => $message]);
    }

    /** Must be called inside a transaction, always before locking dependent records. */
    private function lockCampaign(int $id): Campaign
    {
        Campaign::query()->whereKey($id)->update(['held_quantity' => DB::raw('held_quantity')]);

        return Campaign::query()->lockForUpdate()->findOrFail($id);
    }

    public function closeInterest(User $actor, Campaign $campaign): void
    {
        DB::transaction(function () use ($actor, $campaign): void {
            $locked = $this->lockCampaign($campaign->id);
            Gate::forUser($actor)->authorize('manage-campaign', $locked);
            if ($locked->status !== 'ativa' || $locked->purchase_phase !== 'interest' || $locked->purchase_config !== null) {
                $this->reject('A fase de interesse já está fechada.');
            }
            $locked->status = 'em_avaliacao';
            $locked->deadline = now();
            $locked->save();
            PlatformAudit::record($actor, 'interest.closed', $locked);
        }, 5);
    }

    /** @param array<string,mixed> $data */
    public function openPayments(User $actor, Campaign $campaign, array $data): void
    {
        if (! $this->gateway->available()) {
            $this->reject('Checkout indisponível: falta um prestador de pagamentos autorizado.');
        }
        DB::transaction(function () use ($actor, $campaign, $data): void {
            $locked = $this->lockCampaign($campaign->id);
            Gate::forUser($actor)->authorize('manage-campaign', $locked);
            $proposal = $locked->proposals()->where('status', 'aceite')->lockForUpdate()->firstOrFail();
            $deadline = CarbonImmutable::parse((string) $data['paymentDeadline']);
            if ($locked->status !== 'proposta_selecionada' || $locked->purchase_phase !== 'interest' || $locked->hasFixedPurchaseTerms() || ! $proposal->supplier->verified) {
                $this->reject('A campanha não permite abrir pagamentos ou o fornecedor precisa de verificação administrativa.');
            }
            $conditions = $locked->purchase_config ?? ProposalValidation::conditions($proposal);
            if (($message = ProposalValidation::conditionsBlocker($conditions)) !== null) {
                $this->reject($message);
            }
            if (! $deadline->isFuture() || $deadline->greaterThan(CarbonImmutable::parse($conditions['price_valid_until'])) || $deadline->greaterThan(CarbonImmutable::parse($conditions['reserved_until']))) {
                throw ValidationException::withMessages(['paymentDeadline' => 'O prazo de pagamento tem de terminar dentro da validade da proposta e da reserva.']);
            }
            $config = [
                ...$conditions,
                'commission_bps' => Money::cents((string) $data['commissionPercent']),
                'pickup_point' => (string) $data['pickupPoint'],
                'delivery_zone' => (string) ($data['deliveryZone'] ?? ''),
                'delivery_fee_cents' => Money::cents((string) ($data['deliveryFee'] ?? '0')),
                'terms_version' => (string) config('purchases.terms_version'), 'terms' => (string) config('purchases.terms'),
            ];
            if ($config['delivery_fee_cents'] > 0 && $config['delivery_zone'] === '') {
                $this->reject('Defina a zona da entrega opcional.');
            }
            $locked->purchase_config = $config;
            $locked->payment_deadline = $deadline;
            $locked->purchase_phase = 'payment';
            $locked->save();
            PlatformAudit::record($actor, 'payments.opened', $locked, $config);
            foreach ($locked->participations()->with('user')->where('status', '!=', 'cancelada')->get() as $participation) {
                if ($participation->user) {
                    $order = $this->createOrder($locked, $participation, 'recolha', null, false);
                    $this->notifier->send($participation->user, 'payments.opened:'.$locked->id, 'Pague a sua compra', 'O pagamento de '.$locked->title.' está aberto. Escolha como pagar e aceite as condições.', route('consumidor.pedidos.checkout', ['order' => $order->id], absolute: false));
                }
            }
        }, 5);
    }

    /** @return array{merchandise_cents:int,commission_cents:int,supplier_transport_cents:int,delivery_fee_cents:int,total_cents:int} */
    public function quote(Campaign $campaign, int $quantity, string $method): array
    {
        $config = $campaign->purchase_config ?? [];
        if ($config === []) {
            $this->reject('Os pagamentos ainda não estão abertos.');
        }
        if ($method === 'entrega' && empty($config['delivery_zone'])) {
            $this->reject('Esta campanha só permite recolha.');
        }
        $subtotal = Money::multiply((int) $config['unit_cents'], $quantity);
        $commission = Money::commission($subtotal, (int) $config['commission_bps']);
        $transport = Money::multiply((int) $config['transport_unit_cents'], $quantity);
        $delivery = $method === 'entrega' ? (int) $config['delivery_fee_cents'] : 0;
        $total = $subtotal + $commission + $transport + $delivery;
        if ($total > Money::MAX_CENTS) {
            $this->reject('O pedido excede o limite monetário do piloto.');
        }

        return ['merchandise_cents' => $subtotal, 'commission_cents' => $commission, 'supplier_transport_cents' => $transport, 'delivery_fee_cents' => $delivery, 'total_cents' => $total];
    }

    private function assertPaymentWindow(Campaign $campaign): void
    {
        $config = $campaign->purchase_config ?? [];
        if (! $this->gateway->available() || $campaign->purchase_phase !== 'payment' || ! $campaign->payment_deadline || now()->greaterThanOrEqualTo($campaign->payment_deadline) || now()->greaterThanOrEqualTo(CarbonImmutable::parse((string) ($config['price_valid_until'] ?? '1970-01-01'))) || now()->greaterThanOrEqualTo(CarbonImmutable::parse((string) ($config['reserved_until'] ?? '1970-01-01')))) {
            $this->reject('O prazo ou as condições de pagamento expiraram, ou o prestador está indisponível.');
        }
    }

    /** @param array<string,mixed> $data */
    public function issueOrder(User $actor, Campaign $campaign, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $campaign, $data): PurchaseOrder {
            $locked = $this->lockCampaign($campaign->id);
            $this->assertPaymentWindow($locked);
            $participation = $locked->participations()->where('user_id', $actor->id)->where('status', '!=', 'cancelada')->lockForUpdate()->firstOrFail();
            $config = $locked->purchase_config ?? [];
            if ($data['termsVersion'] !== $config['terms_version'] || ! $data['acceptTerms']) {
                $this->reject('Aceite a versão actual das condições de compra.');
            }
            $method = (string) $data['deliveryMethod'];
            $existing = PurchaseOrder::query()->where('participation_id', $participation->id)->first();
            if ($existing) {
                if ($existing->accepted_at === null && ! $existing->attempts()->exists()) {
                    $existing->fill(['delivery_method' => $method, 'delivery_address' => $method === 'entrega' ? ($data['deliveryAddress'] ?? null) : null,
                        ...$this->quote($locked, $participation->quantity, $method), 'accepted_at' => now()])->save();
                }

                return $existing;
            }
            $order = $this->createOrder($locked, $participation, $method, $data['deliveryAddress'] ?? null, true);
            PlatformAudit::record($actor, 'order.issued', $order, ['total_cents' => $order->total_cents, 'terms_version' => $order->terms_version]);

            return $order;
        }, 5);
    }

    private function createOrder(Campaign $campaign, Participation $participation, string $method, ?string $address, bool $accepted): PurchaseOrder
    {
        $config = $campaign->purchase_config ?? [];

        return PurchaseOrder::create([
            'reference' => (string) Str::uuid(), 'campaign_id' => $campaign->id,
            'participation_id' => $participation->id, 'user_id' => $participation->user_id,
            'quantity' => $participation->quantity, 'status' => 'awaiting_payment', 'receipt_status' => 'waiting',
            'delivery_method' => $method, 'delivery_address' => $method === 'entrega' ? $address : null,
            'currency' => 'MZN', ...$this->quote($campaign, $participation->quantity, $method),
            'conditions' => $config, 'terms_version' => $config['terms_version'],
            'accepted_at' => $accepted ? now() : null, 'payment_deadline' => $campaign->payment_deadline,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function pay(User $actor, PurchaseOrder $order, array $data = []): PaymentAttempt
    {
        abort_unless($order->user_id === $actor->id, 404);
        $unresolved = PaymentAttempt::query()->where('purchase_order_id', $order->id)->whereIn('status', ['pending', 'review'])->latest('id')->first();
        if ($unresolved) {
            $this->reconcile($unresolved);
        }

        $attempt = DB::transaction(function () use ($actor, $order, $data): PaymentAttempt {
            $campaign = $this->lockCampaign($order->campaign_id);
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertPaymentWindow($campaign);
            if (! in_array($locked->status, ['awaiting_payment', 'payment_pending'], true)) {
                $this->reject('O pedido já foi pago ou encerrado.');
            }
            $pending = PaymentAttempt::query()->where('purchase_order_id', $locked->id)->whereIn('status', ['pending', 'review'])->first();
            if ($pending) {
                return $pending;
            }
            if ($data !== []) {
                if (($data['termsVersion'] ?? null) !== $locked->terms_version || ! ($data['acceptTerms'] ?? false)) {
                    $this->reject('Aceite a versão actual das condições de compra.');
                }
                $method = collect($this->gateway->availableMethods())->firstWhere('code', $data['paymentMethod'] ?? null);
                if (! $method || ($method['requiresPhone'] && ! preg_match('/^\+258[0-9]{9}$/D', (string) ($data['paymentPhone'] ?? '')))) {
                    $this->reject('Escolha um método disponível e indique o número no formato +258 seguido de 9 dígitos.');
                }
                if ($locked->accepted_at === null) {
                    $delivery = (string) ($data['deliveryMethod'] ?? 'recolha');
                    if (! in_array($delivery, ['recolha', 'entrega'], true) || ($delivery === 'entrega' && trim((string) ($data['deliveryAddress'] ?? '')) === '')) {
                        $this->reject('Indique a modalidade e a morada para entrega.');
                    }
                    $locked->fill(['delivery_method' => $delivery, 'delivery_address' => $delivery === 'entrega' ? $data['deliveryAddress'] : null,
                        ...$this->quote($campaign, $locked->quantity, $delivery), 'accepted_at' => now()]);
                }
            } elseif ($locked->accepted_at === null) {
                $this->reject('Aceite as condições de compra e escolha como pagar.');
            }
            $capacity = (int) ($campaign->purchase_config['available_quantity'] ?? 0);
            $reserved = Campaign::query()->whereKey($campaign->id)->where('held_quantity', '<=', $capacity - $locked->quantity)->increment('held_quantity', $locked->quantity);
            if ($reserved !== 1) {
                $this->reject('A quantidade reservada esgotou. Não foi iniciada uma cobrança.');
            }
            $attempt = PaymentAttempt::create(['purchase_order_id' => $locked->id, 'reference' => (string) Str::uuid(), 'provider' => (string) config('purchases.provider'), 'amount_cents' => $locked->total_cents, 'currency' => $locked->currency, 'status' => 'pending', 'holds_stock' => true,
                'payment_method' => $data['paymentMethod'] ?? null, 'payment_phone' => ($method['requiresPhone'] ?? false) ? ($data['paymentPhone'] ?? null) : null]);
            $locked->status = 'payment_pending';
            $locked->save();
            PlatformAudit::record($actor, 'payment.initiated', $attempt, ['order' => $locked->reference]);

            return $attempt;
        }, 5);

        if ($attempt->wasRecentlyCreated) {
            try {
                $this->gateway->initiate($attempt->reference, $attempt->amount_cents, $attempt->currency, $attempt->payment_method, $attempt->payment_phone);
            } catch (\Exception $exception) {
                report(new \RuntimeException('Payment initiation failed; reference '.$attempt->reference.' requires reconciliation.'));
                throw ValidationException::withMessages(['payment' => 'O resultado do pagamento é incerto. A referência foi preservada; confira o pagamento antes de voltar a tentar.']);
            }
        }

        return $attempt;
    }

    public function reconcile(PaymentAttempt $attempt): void
    {
        try {
            $result = $this->gateway->lookup($attempt->reference);
            if ($result === null && $attempt->status === 'pending' && $attempt->holds_stock) {
                $this->assertPaymentWindow($attempt->purchaseOrder->campaign);
                $this->gateway->initiate($attempt->reference, $attempt->amount_cents, $attempt->currency, $attempt->payment_method, $attempt->payment_phone);
                $result = $this->gateway->lookup($attempt->reference);
            }
        } catch (ValidationException) {
            return;
        } catch (\Exception $exception) {
            report(new \RuntimeException('Payment lookup failed; reference '.$attempt->reference.' remains unresolved.'));

            return;
        }
        if ($result === null) {
            return;
        }
        DB::transaction(function () use ($attempt, $result): void {
            $order = $attempt->purchaseOrder;
            $campaign = $this->lockCampaign($order->campaign_id);
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $payment = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($payment->confirmed_at !== null) {
                return;
            }
            if ($result['reference'] !== $payment->reference || $result['amount_cents'] !== $payment->amount_cents || $result['currency'] !== $payment->currency) {
                $payment->status = 'review';
                $payment->save();
                $this->event($campaign, $locked, 'payment_mismatch', 0, 'mismatch:'.$payment->reference, $payment->reference, 'Referência, valor ou moeda divergente: exige conferência.');

                return;
            }
            if ($result['status'] === 'pending') {
                return;
            }
            if ($result['status'] === 'failed') {
                $payment->status = 'failed';
                $this->releaseStock($campaign, $payment, $locked->quantity);
                $payment->save();
                if ($locked->status === 'payment_pending') {
                    $locked->status = $campaign->purchase_phase === 'payment' ? 'awaiting_payment' : 'cancelled';
                    $locked->save();
                }

                return;
            }
            if ($result['status'] !== 'paid') {
                return;
            }
            $alreadyPaid = PaymentAttempt::query()->where('purchase_order_id', $locked->id)->whereNotNull('confirmed_at')->exists();
            if ($result['settled_at'] === null) {
                $payment->status = 'review';
                $payment->save();

                return;
            }
            $settledAt = CarbonImmutable::parse($result['settled_at']);
            $late = ! in_array($campaign->purchase_phase, ['payment', 'review'], true) || $alreadyPaid
                || $settledAt->greaterThanOrEqualTo($locked->payment_deadline)
                || $settledAt->greaterThanOrEqualTo(CarbonImmutable::parse((string) $locked->conditions['reserved_until']))
                || $settledAt->greaterThanOrEqualTo(CarbonImmutable::parse((string) $locked->conditions['price_valid_until']));
            if (! $payment->holds_stock && ! $late) {
                $capacity = (int) ($campaign->purchase_config['available_quantity'] ?? 0);
                $acquired = Campaign::query()->whereKey($campaign->id)->where('held_quantity', '<=', $capacity - $locked->quantity)->increment('held_quantity', $locked->quantity);
                $late = $acquired !== 1;
                $payment->holds_stock = ! $late;
            }
            $payment->status = 'paid';
            $payment->confirmed_at = now();
            $payment->save();
            if (! $alreadyPaid) {
                $locked->status = $late ? 'refund_pending' : 'paid';
                $locked->save();
            }
            $this->event($campaign, $locked, 'payment_received', $payment->amount_cents, 'payment:'.$payment->reference, $payment->reference, 'Pagamento confirmado pelo prestador de teste.');
            $this->notifyOrder($locked, 'payment.confirmed:'.$payment->reference, $late ? 'Pagamento depois do prazo. Foi iniciado o reembolso integral.' : 'Pagamento confirmado. A compra aguarda a verificação do mínimo.');
            if ($late) {
                $this->requestRefund($payment, 'Pagamento identificado depois do encerramento ou duplicação de cobrança.');
            }
        }, 5);
    }

    private function releaseStock(Campaign $campaign, PaymentAttempt $attempt, int $quantity): void
    {
        if ($attempt->holds_stock) {
            Campaign::query()->whereKey($campaign->id)->where('held_quantity', '>=', $quantity)->decrement('held_quantity', $quantity);
            $attempt->holds_stock = false;
        }
    }

    private function requestRefund(PaymentAttempt $payment, string $reason): void
    {
        PurchaseRefund::firstOrCreate(['payment_attempt_id' => $payment->id], ['reference' => (string) Str::uuid(), 'amount_cents' => $payment->amount_cents, 'status' => 'requested', 'reason' => $reason]);
    }

    public function closeCampaign(Campaign $campaign, ?User $actor = null, bool $cancel = false, string $reason = '', bool $refreshPayments = true): void
    {
        if ($actor) {
            Gate::forUser($actor)->authorize($cancel ? 'administer-platform' : 'manage-campaign', $campaign);
        }
        if ($refreshPayments) {
            foreach (PaymentAttempt::query()->whereHas('purchaseOrder', fn ($q) => $q->where('campaign_id', $campaign->id))->whereIn('status', ['pending', 'review', 'failed'])->get() as $attempt) {
                $this->reconcile($attempt);
            }
        }
        DB::transaction(function () use ($campaign, $actor, $cancel, $reason): void {
            $locked = $this->lockCampaign($campaign->id);
            if (! in_array($locked->purchase_phase, ['payment', 'review'], true)) {
                return;
            }
            if (! $cancel && (! $locked->payment_deadline || now()->lessThan($locked->payment_deadline))) {
                $this->reject('O prazo de pagamento ainda não terminou.');
            }
            $orders = PurchaseOrder::query()->where('campaign_id', $locked->id);
            $uncertain = PaymentAttempt::query()->whereIn('purchase_order_id', (clone $orders)->select('id'))->whereIn('status', ['pending', 'review'])->exists();
            if ($uncertain && ! $cancel) {
                $locked->purchase_phase = 'review';
                $locked->save();
                $this->event($locked, null, 'reconciliation_required', 0, 'review:'.$locked->id, (string) $locked->id, 'Tentativas incertas: conferir antes da decisão final.', $actor);

                return;
            }
            $quantity = (int) (clone $orders)->where('status', 'paid')->sum('quantity');
            $config = $locked->purchase_config ?? [];
            if ($cancel || $quantity < (int) $config['minimum_quantity']) {
                $locked->purchase_phase = 'cancelled';
                $locked->status = 'cancelada';
                foreach ((clone $orders)->where('status', 'paid')->get() as $order) {
                    $order->status = 'refund_pending';
                    $order->save();
                    foreach (PaymentAttempt::query()->where('purchase_order_id', $order->id)->whereNotNull('confirmed_at')->get() as $payment) {
                        $this->requestRefund($payment, $reason !== '' ? $reason : 'O mínimo comercial não foi atingido.');
                    }
                    $this->notifyOrder($order, 'purchase.cancelled:'.$locked->id, 'Compra cancelada. O pedido cobrado será reembolsado integralmente.');
                }
            } else {
                $supply = SupplyOrder::firstOrCreate(['campaign_id' => $locked->id], ['supplier_id' => $config['supplier_id'], 'reference' => (string) Str::uuid(), 'quantity' => $quantity, 'status' => 'issued', 'conditions' => $config]);
                $locked->purchase_phase = 'supply';
                $locked->status = 'confirmada';
                $supplierDue = (int) (clone $orders)->where('status', 'paid')->sum('merchandise_cents') + (int) (clone $orders)->where('status', 'paid')->sum('supplier_transport_cents');
                $this->event($locked, null, 'supplier_due', $supplierDue, 'supplier_due:'.$supply->id, $supply->reference, 'Valor contratual devido ao fornecedor; liquidação ainda não registada.', $actor);
                foreach ((clone $orders)->where('status', 'paid')->with('user')->get() as $order) {
                    $this->notifyOrder($order, 'supply.issued:'.$supply->id, 'O mínimo foi atingido e a ordem de fornecimento foi emitida.');
                }
            }
            (clone $orders)->where('status', 'awaiting_payment')->update(['status' => 'cancelled']);
            $locked->save();
            $this->event($locked, null, 'campaign_decided', 0, 'decision:'.$locked->id, (string) $locked->id, $locked->purchase_phase.': '.$reason, $actor);
        }, 5);
        $this->processRefunds();
    }

    public function processRefunds(): void
    {
        foreach (PurchaseRefund::query()->where('status', 'requested')->with('paymentAttempt.purchaseOrder')->lazyById(100) as $refund) {
            $payment = $refund->paymentAttempt;
            try {
                $refunded = $this->gateway->refund($payment->reference, $refund->reference, $refund->amount_cents);
            } catch (\Exception $exception) {
                report($exception);

                continue;
            }
            if (! $refunded) {
                continue;
            }
            DB::transaction(function () use ($refund, $payment): void {
                $order = $payment->purchaseOrder;
                $campaign = $this->lockCampaign($order->campaign_id);
                $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
                $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($payment->id);
                $record = PurchaseRefund::query()->lockForUpdate()->findOrFail($refund->id);
                if ($record->status === 'refunded') {
                    return;
                }
                $record->status = 'refunded';
                $record->save();
                $this->releaseStock($campaign, $attempt, $locked->quantity);
                $attempt->save();
                if ($locked->status === 'refund_pending') {
                    $locked->status = 'refunded';
                    $locked->save();
                }
                $this->event($campaign, $locked, 'refund_paid', $record->amount_cents, 'refund:'.$record->reference, $record->reference, $record->reason);
                $this->notifyOrder($locked, 'refund.confirmed:'.$record->reference, 'Reembolso integral confirmado pelo prestador de teste.');
            }, 5);
        }
    }

    /** @param array<string,mixed> $data */
    public function receiveSupply(User $actor, Campaign $campaign, array $data): void
    {
        DB::transaction(function () use ($actor, $campaign, $data): void {
            $locked = $this->lockCampaign($campaign->id);
            Gate::forUser($actor)->authorize('manage-campaign', $locked);
            $supply = SupplyOrder::query()->where('campaign_id', $locked->id)->lockForUpdate()->firstOrFail();
            $quantity = (int) $data['receivedQuantity'];
            if ($locked->purchase_phase !== 'supply' || $quantity < $supply->received_quantity || $quantity > $supply->quantity) {
                $this->reject('Informe a quantidade recebida acumulada, sem exceder a ordem de fornecimento.');
            }
            $supply->received_quantity = $quantity;
            $supply->status = $quantity === $supply->quantity ? 'received' : 'partial';
            $supply->save();
            $locked->status = 'em_fornecimento';
            $locked->save();
            PlatformAudit::record($actor, 'supply.received', $supply, ['quantity' => $quantity, 'reference' => $data['reference'], 'reason' => $data['reason']]);
        }, 5);
    }

    public function prepareOrder(User $actor, PurchaseOrder $order): void
    {
        DB::transaction(function () use ($actor, $order): void {
            $campaign = $this->lockCampaign($order->campaign_id);
            Gate::forUser($actor)->authorize('manage-campaign', $campaign);
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $supply = SupplyOrder::query()->where('campaign_id', $campaign->id)->firstOrFail();
            $allocated = (int) PurchaseOrder::query()->where('campaign_id', $campaign->id)->whereIn('receipt_status', ['ready', 'received'])->sum('quantity');
            if ($campaign->purchase_phase !== 'supply' || $locked->status !== 'paid' || $locked->receipt_status !== 'waiting' || $allocated + $locked->quantity > $supply->received_quantity) {
                $this->reject('A mercadoria recebida não permite separar este pedido ou ele já foi separado.');
            }
            $code = Str::upper(Str::random(12));
            $locked->collection_code = $code;
            $locked->collection_code_hash = hash('sha256', $code);
            $locked->receipt_status = 'ready';
            $locked->save();
            PlatformAudit::record($actor, 'order.prepared', $locked);
            $this->notifier->send($locked->user, 'pickup.ready:'.$locked->id, 'Pedido pronto para recolha ou entrega', 'Pedido separado. Consulte o código de recolha ou entrega no acompanhamento.', route('consumidor.acompanhamento.show', ['id' => $campaign->id], absolute: false), 'notifPickup');
        }, 5);
    }

    public function handoff(User $actor, PurchaseOrder $order, string $code): void
    {
        DB::transaction(function () use ($actor, $order, $code): void {
            $campaign = $this->lockCampaign($order->campaign_id);
            Gate::forUser($actor)->authorize('manage-campaign', $campaign);
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($campaign->purchase_phase !== 'supply' || $locked->status !== 'paid' || $locked->receipt_status !== 'ready' || ! hash_equals($locked->collection_code_hash ?? '', hash('sha256', $code))) {
                $this->reject('Código inválido, já utilizado ou pedido indisponível.');
            }
            $locked->receipt_status = 'received';
            $locked->received_at = now();
            $locked->collection_code = null;
            $locked->collection_code_hash = null;
            $locked->save();
            PlatformAudit::record($actor, 'order.received', $locked, ['method' => $locked->delivery_method]);
        }, 5);
    }

    /** @param array<string,mixed> $data */
    public function reportIssue(User $actor, PurchaseOrder $order, array $data): void
    {
        abort_unless($order->user_id === $actor->id, 404);
        DB::transaction(function () use ($actor, $order, $data): void {
            $this->lockCampaign($order->campaign_id);
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== 'paid' || PurchaseIssue::query()->where('purchase_order_id', $locked->id)->where('status', 'open')->exists()) {
                $this->reject('O pedido não permite um novo reporte. Acompanhe a ocorrência existente.');
            }
            $issue = PurchaseIssue::create(['purchase_order_id' => $locked->id, 'user_id' => $actor->id, 'type' => $data['type'], 'description' => $data['description'], 'status' => 'open']);
            PlatformAudit::record($actor, 'issue.reported', $issue);
        }, 5);
    }

    /** @param array<string,mixed> $data */
    public function resolveIssue(User $actor, PurchaseIssue $issue, array $data): void
    {
        Gate::forUser($actor)->authorize('administer-platform');
        DB::transaction(function () use ($actor, $issue, $data): void {
            $order = $issue->purchaseOrder;
            $campaign = $this->lockCampaign($order->campaign_id);
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            $record = PurchaseIssue::query()->lockForUpdate()->findOrFail($issue->id);
            if ($record->status !== 'open') {
                $this->reject('A ocorrência já foi resolvida.');
            }
            $record->status = $data['resolutionType'] === 'refund' ? 'refund_approved' : 'resolved';
            $record->resolution = (string) $data['reason'];
            $record->save();
            if ($data['resolutionType'] === 'refund' && $locked->status === 'paid') {
                $locked->status = 'refund_pending';
                $locked->save();
                $payment = PaymentAttempt::query()->where('purchase_order_id', $locked->id)->whereNotNull('confirmed_at')->firstOrFail();
                $this->requestRefund($payment, (string) $data['reason']);
            }
            PlatformAudit::record($actor, 'issue.resolved', $record, ['reason' => $data['reason'], 'reference' => $data['reference']]);
            $this->notifyOrder($locked, 'issue.resolved:'.$record->id, 'Ocorrência actualizada: '.$record->resolution);
        }, 5);
        $this->processRefunds();
    }

    /** @param array<string,mixed> $data */
    public function finance(User $actor, Campaign $campaign, array $data): void
    {
        Gate::forUser($actor)->authorize('administer-platform');
        DB::transaction(function () use ($actor, $campaign, $data): void {
            $locked = $this->lockCampaign($campaign->id);
            if ($locked->purchase_phase !== 'supply' || ! SupplyOrder::query()->where('campaign_id', $locked->id)->exists()) {
                $this->reject('Registe os valores durante o fornecimento, antes do encerramento financeiro.');
            }
            $kind = (string) $data['kind'];
            $amount = Money::cents((string) $data['amount']);
            $entries = FinancialEntry::query()->where('campaign_id', $locked->id);
            if (in_array($kind, ['supplier_settled', 'carrier_settled'], true)) {
                $dueKind = $kind === 'supplier_settled' ? 'supplier_due' : 'carrier_due';
                $due = (int) (clone $entries)->where('kind', $dueKind)->sum('amount_cents');
                $settled = (int) (clone $entries)->where('kind', $kind)->sum('amount_cents');
                if ($amount > $due - $settled) {
                    $this->reject('O valor excede o saldo devido. Registe primeiro os custos comprovados.');
                }
            }
            $key = $kind.':'.$locked->id.':'.(string) $data['reference'];
            if (FinancialEntry::query()->where('event_key', $key)->exists()) {
                $this->reject('Esta referência já foi registada.');
            }
            $this->event($locked, null, $kind, $amount, $key, (string) $data['reference'], (string) $data['reason'], $actor);
            PlatformAudit::record($actor, 'finance.recorded', $locked, ['kind' => $kind, 'amount_cents' => $amount, 'reason' => $data['reason'], 'reference' => $data['reference']]);
        }, 5);
    }

    public function complete(User $actor, Campaign $campaign): void
    {
        Gate::forUser($actor)->authorize('administer-platform');
        DB::transaction(function () use ($actor, $campaign): void {
            $locked = $this->lockCampaign($campaign->id);
            $orders = PurchaseOrder::query()->where('campaign_id', $locked->id);
            $issues = PurchaseIssue::query()->whereIn('purchase_order_id', (clone $orders)->select('id'))->where('status', 'open')->exists();
            $notReceived = (clone $orders)->where('status', 'paid')->where('receipt_status', '!=', 'received')->exists();
            $refundPending = (clone $orders)->where('status', 'refund_pending')->exists();
            $entries = FinancialEntry::query()->where('campaign_id', $locked->id);
            $balance = (int) (clone $entries)->whereIn('kind', ['supplier_due', 'carrier_due'])->sum('amount_cents') - (int) (clone $entries)->whereIn('kind', ['supplier_settled', 'carrier_settled'])->sum('amount_cents');
            if ($locked->purchase_phase !== 'supply' || $issues || $notReceived || $refundPending || $balance !== 0) {
                $this->reject('Conclua as entregas, ocorrências, reembolsos e a conferência financeira antes de encerrar.');
            }
            $locked->purchase_phase = 'completed';
            $locked->status = 'concluida';
            $locked->save();
            PlatformAudit::record($actor, 'purchase.completed', $locked);
        }, 5);
    }

    private function notifyOrder(PurchaseOrder $order, string $key, string $message): void
    {
        $this->notifier->send($order->user, $key, 'Compra colectiva', $message, route('consumidor.acompanhamento.show', ['id' => $order->campaign_id], absolute: false));
    }

    private function event(Campaign $campaign, ?PurchaseOrder $order, string $kind, int $amount, string $key, string $reference, string $reason, ?User $actor = null): void
    {
        $entry = FinancialEntry::firstOrCreate(['event_key' => $key], ['campaign_id' => $campaign->id, 'purchase_order_id' => $order?->id, 'user_id' => $actor?->id, 'kind' => $kind, 'amount_cents' => $amount, 'currency' => 'MZN', 'reference' => $reference, 'reason' => $reason]);
        if ($entry->wasRecentlyCreated) {
            AuditEntry::create(['user_id' => $actor?->id, 'actor_name' => $actor === null ? 'Sistema de compras (teste)' : $actor->name, 'action' => 'purchase.'.$kind, 'subject_type' => 'FinancialEntry', 'subject_id' => $entry->id, 'changes' => ['reference' => $reference, 'reason' => $reason, 'amount_cents' => $amount]]);
        }
    }
}
