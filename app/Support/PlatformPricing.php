<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PlatformPricingSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlatformPricing
{
    public const VERSION = 'platform-2026-11-v1';

    public function commissionBps(): int
    {
        $bps = PlatformPricingSetting::query()->find(1)->commission_bps
            ?? Money::cents((string) config('purchases.default_commission_percent', '2.50'));
        if ($bps < 0 || $bps > 10000) {
            throw ValidationException::withMessages(['commissionPercent' => 'A comissão da plataforma deve estar entre 0% e 100%.']);
        }

        return $bps;
    }

    public function updateCommission(User $actor, string $percent): void
    {
        Gate::forUser($actor)->authorize('administer-platform');
        $bps = Money::cents($percent);
        if ($bps > 10000) {
            throw ValidationException::withMessages(['commissionPercent' => 'A comissão não pode ultrapassar 100%.']);
        }
        DB::transaction(function () use ($actor, $bps): void {
            PlatformPricingSetting::query()->upsert([
                ['id' => 1, 'commission_bps' => $bps, 'updated_by' => $actor->id],
            ], ['id'], ['commission_bps', 'updated_by', 'updated_at']);
            $setting = PlatformPricingSetting::query()->findOrFail(1);
            PlatformAudit::record($actor, 'platform.pricing.updated', $setting, ['commission_bps' => $bps]);
        }, 5);
    }

    public function quoteDelivery(User $actor, Campaign $campaign, int $totalCents, string $reference): void
    {
        Gate::forUser($actor)->authorize('administer-platform');
        if ($totalCents < 0 || $totalCents > Money::MAX_CENTS) {
            throw ValidationException::withMessages(['deliveryTotal' => 'O transporte excede o intervalo monetário suportado.']);
        }
        DB::transaction(function () use ($actor, $campaign, $totalCents, $reference): void {
            $locked = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if ($locked->status !== 'proposta_selecionada' || $locked->purchase_phase !== 'interest' || $locked->hasFixedPurchaseTerms()) {
                throw ValidationException::withMessages(['purchase' => 'A cotação só pode ser definida depois da seleção e antes da abertura dos pagamentos.']);
            }
            $proposal = $locked->proposals()->where('status', 'aceite')->firstOrFail();
            $conditions = $locked->purchase_config ?? [];
            if (($conditions['transport_included'] ?? $proposal->transport_included) === true) {
                throw ValidationException::withMessages(['deliveryTotal' => 'O fornecedor já inclui o transporte até ao ponto. Não existe cobrança adicional.']);
            }
            $quote = [
                'proposal_id' => $proposal->id, 'total_cents' => $totalCents, 'reference' => $reference,
                'quoted_by' => $actor->id, 'quoted_at' => now()->toIso8601String(),
            ];
            $locked->purchase_config = [...$conditions, 'platform_delivery_quote' => $quote];
            $locked->save();
            PlatformAudit::record($actor, 'platform.delivery.quoted', $locked, $quote);
        }, 5);
    }

    /** @return list<array{id:int,quantity:int}> */
    private function participants(Campaign $campaign): array
    {
        $campaign->loadMissing('participations');

        return array_values($campaign->participations->filter(fn (Participation $participant): bool => $participant->status !== 'cancelada' && $participant->user_id !== null)->map(fn (Participation $participant): array => [
            'id' => $participant->id, 'quantity' => $participant->quantity,
        ])->all());
    }

    /** @return array<string, mixed>|null */
    public function preview(Campaign $campaign, ?int $commissionBps = null): ?array
    {
        if ($campaign->status !== 'proposta_selecionada' || $campaign->purchase_phase !== 'interest' || $campaign->hasFixedPurchaseTerms()) {
            return null;
        }
        $campaign->loadMissing('proposals');
        $proposal = $campaign->proposals->firstWhere('status', 'aceite');
        if (! $proposal) {
            return null;
        }
        $conditions = $campaign->purchase_config ?? [];
        $included = (bool) ($conditions['transport_included'] ?? $proposal->transport_included);
        $quote = $conditions['platform_delivery_quote'] ?? null;
        $ready = $included || (is_array($quote) && (int) ($quote['proposal_id'] ?? 0) === $proposal->id);
        $participants = $this->participants($campaign);
        $total = $included ? 0 : ($ready ? (int) $quote['total_cents'] : null);
        $bps = $commissionBps ?? $this->commissionBps();

        return [
            'commissionBps' => $bps, 'commissionPercent' => Money::decimal($bps),
            'transportIncluded' => $included, 'deliveryTotalCents' => $total, 'deliveryReference' => $quote['reference'] ?? null,
            'participantCount' => count($participants), 'totalQuantity' => array_sum(array_column($participants, 'quantity')),
            'ready' => $ready && ($total === 0 || $participants !== []),
        ];
    }

    /** @return array<string, mixed> */
    public function snapshot(Campaign $campaign): array
    {
        $preview = $this->preview($campaign);
        if (! $preview || ! $preview['ready']) {
            throw ValidationException::withMessages(['deliveryTotal' => 'A administração deve confirmar o custo total do transporte até ao ponto de recolha antes de abrir pagamentos.']);
        }
        $total = (int) $preview['deliveryTotalCents'];
        $shares = DeliveryFeeAllocation::allocate($total, $this->participants($campaign));

        return [
            'pricing_policy' => self::VERSION, 'commission_bps' => $preview['commissionBps'],
            'delivery_scope' => 'collection_point', 'delivery_provider' => $preview['transportIncluded'] ? 'supplier' : 'platform',
            'delivery_total_cents' => $total, 'delivery_shares_cents' => $shares,
            'delivery_fee_cents' => 0, 'delivery_zone' => '',
        ];
    }
}
