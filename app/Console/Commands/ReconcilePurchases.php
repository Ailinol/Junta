<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Support\CollectivePurchase;
use App\Support\PaymentFollowUp;
use App\Support\PaymentGateway;
use Illuminate\Console\Command;

class ReconcilePurchases extends Command
{
    protected $signature = 'junta:reconcile-purchases';

    protected $description = 'Consulta tentativas, fecha prazos vencidos e retoma reembolsos idempotentes.';

    public function handle(CollectivePurchase $purchases, PaymentGateway $gateway, PaymentFollowUp $followUp): int
    {
        if (! $gateway->available()) {
            if (config('purchases.provider') === 'disabled') {
                $this->info('Pagamentos desativados. Reconciliação ignorada.');

                return self::SUCCESS;
            }

            $this->warn('Prestador indisponível. Nenhum pagamento foi confirmado.');

            return self::FAILURE;
        }
        $followUp->process();
        foreach (Campaign::query()->whereIn('purchase_phase', ['payment', 'review'])->where('payment_deadline', '<=', now())->lazyById(100) as $campaign) {
            $purchases->closeCampaign($campaign, refreshPayments: false);
        }
        $purchases->processRefunds();
        $this->info('Conferência concluída. Tentativas incertas permanecem para revisão.');

        return self::SUCCESS;
    }
}
