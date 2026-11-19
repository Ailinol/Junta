<?php

namespace App\Support;

use App\Models\PaymentAttempt;
use App\Models\User;

class PaymentFollowUp
{
    public function __construct(private CollectivePurchase $purchases, private PlatformNotifier $notifier) {}

    public function process(): void
    {
        foreach (PaymentAttempt::query()->whereNull('confirmed_at')->where(fn ($query) => $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))->with('purchaseOrder.campaign')->lazyById(100) as $attempt) {
            $campaign = $attempt->purchaseOrder->campaign;
            $overdue = $campaign->payment_deadline !== null && ! $campaign->payment_deadline->isFuture();
            $urgent = $overdue && now()->greaterThanOrEqualTo($campaign->payment_deadline->copy()->addMinutes((int) config('purchases.urgent_after_minutes')));
            $interval = max(60, (int) config($urgent ? 'purchases.lookup_urgent_seconds' : ($overdue ? 'purchases.lookup_after_deadline_seconds' : 'purchases.lookup_before_deadline_seconds')));
            $claimed = PaymentAttempt::query()->whereKey($attempt->id)->whereNull('confirmed_at')
                ->where(fn ($query) => $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))
                ->update(['next_check_at' => now()->addSeconds($interval)]);
            if ($claimed !== 1) {
                continue;
            }
            $this->purchases->reconcile($attempt);
            $attempt->refresh();
            if (! $overdue || ! in_array($attempt->status, ['pending', 'review'], true)) {
                continue;
            }
            if ($campaign->organizer) {
                $this->notifier->send($campaign->organizer, 'payment.overdue:'.$campaign->id, 'Pagamento por confirmar depois do prazo',
                    'Há pagamentos por confirmar em '.$campaign->title.'. O sistema continua a verificar; o mínimo só será decidido quando houver resposta.',
                    route('organizador.compra.show', $campaign, absolute: false));
            }
            if (now()->lessThan($campaign->payment_deadline->copy()->addMinutes(max(1, (int) config('purchases.uncertain_escalation_minutes'))))) {
                continue;
            }
            if (! User::query()->where('is_admin', true)->exists()) {
                continue;
            }
            foreach (User::query()->where('is_admin', true)->lazyById(100) as $administrator) {
                $this->notifier->send($administrator, 'payment.escalated:'.$attempt->id, 'Pagamento exige acompanhamento',
                    'O prazo de '.$campaign->title.' terminou e um pagamento continua sem resposta definitiva. A quantidade permanece reservada.',
                    route('admin.compras.index', ['campaignId' => $campaign->id, 'orderId' => $attempt->purchase_order_id], absolute: false));
            }
            if ($campaign->organizer) {
                $this->notifier->send($campaign->organizer, 'payment.escalated.organizer:'.$attempt->id, 'Pagamento encaminhado à administração',
                    'A administração foi notificada de um pagamento por confirmar em '.$campaign->title.'. O sistema continua a verificar o resultado.',
                    route('organizador.compra.show', $campaign, absolute: false));
            }
            PaymentAttempt::query()->whereKey($attempt->id)->whereNull('escalated_at')->update(['escalated_at' => now()]);
        }
    }
}
