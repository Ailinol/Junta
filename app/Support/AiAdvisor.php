<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Proposal;
use Illuminate\Validation\ValidationException;

class AiAdvisor
{
    public function __construct(private OpenAiClient $ai, private SupplierHistory $supplierHistory) {}

    /** @param array<string,mixed> $draft
     * @return array<string,mixed>
     */
    public function review(array $draft, ?Campaign $campaign): array
    {
        $context = $campaign ? $this->campaignState($campaign) : $draft;
        $result = $this->ai->evaluate(['campaign' => $this->redact($context), 'items' => ['draft' => $this->redact($draft)]], ['draft']);
        $evaluation = $result['evaluations']['draft'];
        $findings = $this->findings($evaluation);
        if ($campaign) {
            if (empty($draft['minimumQuantity'])) {
                $findings[] = 'Indique o mínimo comercial para o grupo saber quando a oferta pode avançar.';
            }
            if (empty($draft['reservedUntil'])) {
                $findings[] = 'Confirme até quando consegue reservar a quantidade indicada.';
            }
            if (! $draft['transportIncluded'] && ! isset($draft['transportUnitPrice'])) {
                $findings[] = 'Especifique o custo do transporte por unidade; não será assumido como gratuito.';
            }
            if ($draft['transportIncluded'] && (float) ($draft['transportUnitPrice'] ?? 0) > 0) {
                $findings[] = 'O transporte está marcado como incluído, mas tem um custo adicional. Corrija esta diferença.';
            }
            if ((int) $draft['availableQuantity'] < $campaign->target_quantity) {
                $findings[] = 'A quantidade disponível é inferior ao objectivo da campanha. Explique se pode aumentar o fornecimento.';
            }
        }

        return [
            'model' => $result['model'], 'findings' => $findings,
            'clarity' => round($evaluation['clarity'], 1), 'confidence' => round($evaluation['confidence'] * 100),
            'suggestion' => $campaign
                ? 'Nas observações, detalhe marca/especificação, estado do produto, local de entrega, transporte, impostos e condições de garantia. Prometa apenas o que consegue cumprir.'
                : 'Na descrição, detalhe produto, qualidade, unidade e local de entrega para que os fornecedores preparem ofertas comparáveis.',
        ];
    }

    /** @param array{cost:int,delivery:int,clarity:int,history:int} $weights
     * @return array<string,mixed>
     */
    public function compare(Campaign $campaign, array $weights): array
    {
        $proposals = $campaign->proposals()->with('supplier')->orderBy('id')->limit(21)->get();
        if ($proposals->count() > 20) {
            throw ValidationException::withMessages(['analysis' => 'A comparação assistida aceita até 20 propostas por campanha. Use a comparação manual.']);
        }
        if ($proposals->isEmpty()) {
            throw ValidationException::withMessages(['analysis' => 'Ainda não existem propostas para analisar.']);
        }
        $quantity = (int) $campaign->participations()->whereIn('status', ['pendente', 'confirmada'])->sum('quantity');
        $supplierIds = array_values($proposals->map(fn (Proposal $proposal): int => $proposal->supplier_id)->all());
        $histories = $this->supplierHistory->forComparison($supplierIds, $campaign->id);
        $items = [];
        foreach ($proposals as $proposal) {
            $items['p'.$proposal->id] = [...$this->proposalState($proposal), 'respondsToCampaign' => true, 'unit' => $campaign->unit];
        }
        $historyContext = [];
        foreach ($proposals as $proposal) {
            $historyContext['p'.$proposal->id] = $histories[$proposal->supplier_id];
        }
        $result = $this->ai->evaluate(['campaign' => $this->campaignState($campaign), 'items' => $items, 'supplierHistory' => $historyContext], array_keys($items));
        $rows = [];
        foreach ($proposals as $proposal) {
            $evaluation = $result['evaluations']['p'.$proposal->id];
            $warnings = $this->findings($evaluation);
            $issues = [];
            $cost = $this->knownCost($proposal);
            if ($quantity < 1) {
                $issues[] = 'Não existe procura registada para servir de base comum.';
            }
            if ($campaign->status !== 'em_avaliacao') {
                $issues[] = 'A campanha ainda não está em avaliação ou já tem uma decisão.';
            }
            if (! in_array($proposal->status, ['submetida', 'em_avaliacao'], true) || $proposal->valid_until->isPast()) {
                $issues[] = 'A proposta não está disponível para selecção ou expirou.';
            }
            if (! $proposal->supplier->verified) {
                $issues[] = 'Fornecedor sem verificação administrativa.';
            }
            if ($proposal->delivery_days < 1) {
                $issues[] = 'O prazo de entrega precisa de confirmação.';
            }
            if ($proposal->available_quantity < $quantity) {
                $issues[] = 'A quantidade não cobre a procura registada.';
            }
            if ($proposal->minimum_quantity === null || $proposal->minimum_quantity > $quantity) {
                $issues[] = 'O mínimo comercial está por confirmar ou supera a procura registada.';
            }
            if ($proposal->reserved_until === null || $proposal->reserved_until->isPast()) {
                $issues[] = 'A reserva de stock está por confirmar ou expirou.';
            }
            if ($cost === null) {
                $issues[] = 'Preço ou transporte por confirmar; custo incompleto não entra na classificação.';
            }
            if ($campaign->delivery_mode !== 'ambos' && $proposal->delivery_mode !== 'ambos' && $campaign->delivery_mode !== $proposal->delivery_mode) {
                $issues[] = 'A modalidade de entrega difere da campanha.';
            }
            $total = null;
            $productTotal = null;
            $transportTotal = null;
            if ($quantity > 0) {
                try {
                    $productTotal = Money::multiply(Money::cents($proposal->unit_price), $quantity);
                    if ($cost !== null) {
                        $total = Money::multiply($cost, $quantity);
                        $transportTotal = $total - $productTotal;
                    }
                } catch (ValidationException) {
                    $issues[] = 'O total ultrapassa o limite monetário suportado.';
                }
            }
            $rows[] = [
                'proposalId' => $proposal->id, 'supplierName' => $proposal->supplier->company_name, 'unitCostCents' => $cost, 'totalCents' => $total,
                'deliveryDays' => $proposal->delivery_days, 'clarity' => round($evaluation['clarity'], 1),
                'confidence' => round($evaluation['confidence'] * 100), 'findings' => $issues, 'warnings' => $warnings,
                'eligible' => $issues === [], 'score' => null, 'costScore' => null, 'deliveryScore' => null,
                'productTotalCents' => $productTotal, 'transportTotalCents' => $transportTotal,
                'supplierHistory' => $histories[$proposal->supplier_id], 'historyScore' => $histories[$proposal->supplier_id]['score'],
            ];
        }
        $eligible = array_values(array_filter($rows, fn (array $row): bool => $row['eligible']));
        if ($eligible !== []) {
            $lowestCost = min(array_column($eligible, 'unitCostCents'));
            $fastest = min(array_column($eligible, 'deliveryDays'));
            foreach ($rows as &$row) {
                if ($row['eligible']) {
                    $row['costScore'] = round(100 * $lowestCost / $row['unitCostCents'], 1);
                    $row['deliveryScore'] = round(100 * $fastest / $row['deliveryDays'], 1);
                    $row['score'] = round(($row['costScore'] * $weights['cost'] + $row['deliveryScore'] * $weights['delivery'] + $row['clarity'] * $weights['clarity'] + $row['historyScore'] * $weights['history']) / 100, 1);
                }
            }
            unset($row);
        }
        usort($rows, fn (array $left, array $right): int => (($right['score'] ?? -1) <=> ($left['score'] ?? -1))
            ?: (($left['totalCents'] ?? PHP_INT_MAX) <=> ($right['totalCents'] ?? PHP_INT_MAX))
            ?: ($left['deliveryDays'] <=> $right['deliveryDays']) ?: ($right['historyScore'] <=> $left['historyScore'])
            ?: ($right['clarity'] <=> $left['clarity']) ?: ($left['proposalId'] <=> $right['proposalId']));
        $ranked = array_values(array_filter($rows, fn (array $row): bool => $row['eligible']));
        $best = $ranked[0] ?? null;
        $next = $ranked[1] ?? null;
        $tie = $next !== null && $best['score'] === $next['score'] && $best['totalCents'] === $next['totalCents']
            && $best['deliveryDays'] === $next['deliveryDays'] && $best['historyScore'] === $next['historyScore'] && $best['clarity'] === $next['clarity'];
        $closeCall = $next !== null && ! $tie && abs($best['score'] - $next['score']) <= 1;
        $recommended = $best !== null && ! $tie ? $best['proposalId'] : null;
        $reasons = [];
        if ($eligible !== [] && $best !== null && ! $tie) {
            if (count($ranked) === 1) {
                $reasons[] = 'É a única proposta que cumpre as condições comerciais para esta compra.';
            } else {
                $reasons[] = 'Fica em primeiro lugar com os pesos definidos para custo, prazo, clareza e histórico.';
                if ($best['totalCents'] === min(array_column($eligible, 'totalCents'))) {
                    $reasons[] = 'Tem o menor custo total conhecido, incluindo o transporte declarado.';
                }
                if ($best['deliveryDays'] === min(array_column($eligible, 'deliveryDays'))) {
                    $reasons[] = 'Tem o menor prazo de entrega entre as propostas comparáveis.';
                }
            }
            $reasons[] = 'Cobre a procura registada e cumpre o mínimo comercial, a reserva e a validade.';
        }
        $headline = $recommended !== null
            ? 'Recomendamos a proposta #'.$best['proposalId'].' de '.$best['supplierName'].'. É a melhor opção com os critérios definidos.'
            : ($tie ? 'As primeiras propostas são equivalentes nos critérios avaliados. Ajuste os pesos ou escolha entre elas.' : 'Nenhuma proposta cumpre as condições comerciais. Corrija os impedimentos indicados para obter uma recomendação.');

        $expiresAt = now()->addMinutes(5);
        foreach ($proposals as $proposal) {
            foreach ([$proposal->valid_until, $proposal->reserved_until] as $expiry) {
                if ($expiry?->isFuture() && $expiry->lessThan($expiresAt)) {
                    $expiresAt = $expiry;
                }
            }
        }

        return [
            'model' => $result['model'], 'rows' => $rows, 'quantity' => $quantity, 'unit' => $campaign->unit, 'weights' => $weights,
            'expiresAt' => $expiresAt->toIso8601String(),
            'inputHash' => hash('sha256', json_encode([$this->campaignState($campaign), $items, $historyContext, $quantity, $weights, $campaign->status], JSON_THROW_ON_ERROR)),
            'recommendedProposalId' => $recommended,
            'recommendation' => ['status' => $recommended !== null ? 'recommended' : ($tie ? 'equivalent' : 'blocked'),
                'message' => $headline, 'reasons' => $reasons, 'warnings' => $recommended !== null ? $best['warnings'] : [],
                'closeCall' => $closeCall, 'alternativeProposalId' => $next['proposalId'] ?? null,
                'priceDifferenceCents' => $recommended !== null && $next !== null ? $next['totalCents'] - $best['totalCents'] : null],
            'findings' => [$headline],
            'suggestion' => 'Custo: menor custo conhecido ÷ custo da oferta, para a mesma quantidade. Prazo: menor prazo ÷ prazo da oferta. Clareza: avaliação da IA. Histórico: 100 × (recepções completas + 1) ÷ (ordens terminadas + 2); sem histórico, 50/100 neutros. Ordens ainda em curso não reduzem esta pontuação. Recepção registada não comprova pontualidade, qualidade ou responsabilidade por problemas. Cada parcela recebe o peso indicado. O custo inclui produto e transporte declarado até ao ponto; confirme impostos e outros encargos. A decisão final é sua.',
        ];
    }

    /** @return array<string,mixed> */
    public function campaignState(Campaign $campaign): array
    {
        return $this->redact(['title' => $campaign->title, 'description' => $campaign->description, 'unit' => $campaign->unit, 'targetQuantity' => $campaign->target_quantity, 'deliveryMode' => $campaign->delivery_mode]);
    }

    /** @return array<string,mixed> */
    private function proposalState(Proposal $proposal): array
    {
        return $this->redact([
            'unitPrice' => $proposal->unit_price, 'availableQuantity' => $proposal->available_quantity,
            'minimumQuantity' => $proposal->minimum_quantity, 'reservedUntil' => $proposal->reserved_until?->toIso8601String(),
            'transportIncluded' => $proposal->transport_included, 'transportUnitCents' => $proposal->transport_unit_cents,
            'deliveryDays' => $proposal->delivery_days, 'deliveryMode' => $proposal->delivery_mode,
            'validUntil' => $proposal->valid_until->toIso8601String(), 'notes' => $proposal->notes,
        ]);
    }

    private function knownCost(Proposal $proposal): ?int
    {
        if ($proposal->transport_included === null || ($proposal->transport_included === false && $proposal->transport_unit_cents === null)) {
            return null;
        }
        try {
            $price = Money::cents($proposal->unit_price);
        } catch (ValidationException) {
            return null;
        }
        $transport = $proposal->transport_included ? 0 : $proposal->transport_unit_cents;
        if ($price <= 0 || $transport < 0 || ($proposal->transport_included && ($proposal->transport_unit_cents ?? 0) > 0)) {
            return null;
        }
        if ($transport > Money::MAX_CENTS - $price) {
            return null;
        }

        return $price + $transport;
    }

    /** @param array{clarity:float,confidence:float,contradiction:float,alignment:float} $evaluation
     * @return list<string>
     */
    private function findings(array $evaluation): array
    {
        $findings = [];
        if ($evaluation['confidence'] < 0.7) {
            $findings[] = 'A avaliação de clareza tem baixa confiança. Reveja manualmente.';
        }
        if ($evaluation['clarity'] < 60) {
            $findings[] = 'Clarifique a especificação do produto e as condições de fornecimento.';
        }
        if ($evaluation['contradiction'] > 0.2) {
            $findings[] = 'Reveja possíveis diferenças entre o texto e os campos comerciais; a coerência não ficou confirmada.';
        }
        if ($evaluation['alignment'] < 0.8) {
            $findings[] = 'A correspondência com a especificação da campanha precisa de confirmação.';
        }

        return $findings;
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && in_array($key, ['title', 'description', 'context', 'notes'], true)) {
                $data[$key] = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}|\+?\d[\d\s()-]{7,}\d/u', '[contacto omitido]', $value);
            }
        }

        return $data;
    }
}
