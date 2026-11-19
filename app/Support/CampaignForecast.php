<?php

namespace App\Support;

use App\Models\AuditEntry;
use App\Models\Campaign;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignForecast
{
    public function __construct(private OpenAiClient $ai) {}

    /** @return array<string,mixed> */
    public function analyse(Campaign $campaign): array
    {
        $snapshot = DB::transaction(fn (): array => $this->snapshot($campaign));
        $features = $snapshot['features'];
        $result = [
            'state' => 'insufficient_data', 'model' => null, 'features' => $features,
            'probabilityPercent' => null, 'expectedQuantity' => null, 'lowerQuantity' => null, 'upperQuantity' => null,
            'inputHash' => $snapshot['version'], 'findings' => [],
            'method' => 'Não foi calculada uma previsão nem enviado um pedido à OpenAI. Estes valores descrevem apenas a procura registada.',
        ];
        if ($campaign->status !== 'ativa' || $campaign->purchase_phase !== 'interest' || $campaign->purchase_config !== null || ! $campaign->deadline->isFuture()) {
            return [...$result, 'state' => 'closed', 'findings' => ['A previsão aplica-se à fase de interesse aberta. Consulte o resultado e as operações da campanha.']];
        }
        if ($features['currentQuantity'] >= $campaign->target_quantity) {
            return [...$result, 'state' => 'target_reached', 'findings' => ['A procura registada já atingiu a meta. As adesões continuam a ser manifestações de interesse, sujeitas a alteração e sem confirmação de pagamento.']];
        }
        if ($features['participantCount'] < 3 || $features['elapsedHours'] < 24 || $features['currentQuantity'] > 2147483647) {
            return [...$result, 'findings' => ['Aguarde pelo menos 24 horas de observação e três participantes activos para obter uma estimativa. Quantidades acima do limite suportado exigem análise manual.']];
        }
        $analysis = $this->ai->forecast($features);
        if ($this->version($campaign->fresh() ?? $campaign) !== $snapshot['version']) {
            throw ValidationException::withMessages(['forecast' => 'As adesões ou as condições mudaram durante a análise. Actualize a previsão.']);
        }
        $forecast = $analysis['forecast'];
        $findings = ['A projecção assume que as adesões actuais se mantêm. Novas adesões, alterações e desistências podem mudar o resultado.'];
        if ($features['historicalContext']['campaignCount'] < 5) {
            $findings[] = 'Há poucas campanhas anteriores da mesma categoria e unidade. A incerteza é elevada.';
        }
        if ($features['largestParticipationShare'] > 0.5) {
            $findings[] = 'Mais de metade da procura depende de uma única adesão.';
        }
        if ($features['remainingHours'] < 24) {
            $findings[] = 'Falta menos de um dia para o prazo de interesse.';
        }

        return [...$result, 'state' => 'estimated', 'model' => $analysis['model'],
            'method' => 'Estimativa da OpenAI a partir de dados agregados. Esta probabilidade ainda não foi validada com os resultados reais do Junta. O intervalo mostra cenários plausíveis, não uma garantia estatística.',
            'probabilityPercent' => max(1, min(99, (int) round((float) $forecast['probability'] * 100))),
            'expectedQuantity' => $forecast['expectedQuantity'], 'lowerQuantity' => $forecast['lowerQuantity'], 'upperQuantity' => $forecast['upperQuantity'],
            'expiresAt' => now()->addMinutes(5)->min($campaign->deadline)->toIso8601String(), 'findings' => $findings,
        ];
    }

    /** @return array{version:string,features:array<string,mixed>} */
    private function snapshot(Campaign $campaign): array
    {
        $asOf = now();
        $published = AuditEntry::query()->where('subject_type', 'Campaign')->where('subject_id', $campaign->id)->where('action', 'campaign.published')->min('created_at');
        $startedAt = $published ? Carbon::parse($published) : $campaign->created_at->copy();
        $startedAt = $startedAt->min($asOf);
        $elapsedHours = max(0, $startedAt->diffInHours($asOf));
        $remainingHours = max(0, $asOf->diffInHours($campaign->deadline, false));
        $active = $campaign->participations()->whereIn('status', ['pendente', 'confirmada'])->where('created_at', '<=', $asOf);
        $totals = (clone $active)->selectRaw('count(*) as participants, coalesce(sum(quantity),0) as units, coalesce(max(quantity),0) as largest')->first();
        $quantity = (int) $totals->getAttribute('units');
        $recentStart = $asOf->copy()->subDay()->max($startedAt);
        $previousStart = $asOf->copy()->subDays(2)->max($startedAt);
        $recentQuantity = (int) (clone $active)->where('created_at', '>=', $recentStart)->sum('quantity');
        $previousQuantity = (int) (clone $active)->where('created_at', '>=', $previousStart)->where('created_at', '<', $recentStart)->sum('quantity');
        $recentHours = $recentStart->diffInHours($asOf);
        $previousHours = $previousStart->diffInHours($recentStart);
        $averageRate = $elapsedHours > 0 ? $quantity / ($elapsedHours / 24) : 0;
        $seriesStart = $asOf->copy()->subDays(29)->startOfDay();
        $cumulative = (int) (clone $active)->where('created_at', '<', $seriesStart)->sum('quantity');
        $series = [];
        if ($cumulative > 0) {
            $series[] = ['date' => $seriesStart->copy()->subDay()->toDateString(), 'quantity' => $cumulative];
        }
        $days = (clone $active)->where('created_at', '>=', $seriesStart)->selectRaw('date(created_at) as day, sum(quantity) as units')->groupByRaw('date(created_at)')->orderBy('day')->get();
        foreach ($days as $day) {
            $cumulative += (int) $day->getAttribute('units');
            $series[] = ['date' => (string) $day->getAttribute('day'), 'quantity' => $cumulative];
        }
        $history = Campaign::query()->where('id', '!=', $campaign->id)->where('category', $campaign->category)->where('unit', $campaign->unit)
            ->where('status', '!=', 'rascunho')->where('deadline', '<', $asOf)->where('created_at', '<', $asOf)
            ->withSum(['participations as retained_quantity' => fn (Builder $query) => $query->whereIn('status', ['pendente', 'confirmada'])->whereColumn('participations.created_at', '<=', 'campaigns.deadline')], 'quantity')
            ->orderByDesc('deadline')->limit(50)->get();
        $sameLocation = $history->filter(fn (Campaign $past): bool => mb_strtolower(trim($past->location)) === mb_strtolower(trim($campaign->location)));
        $ratio = fn (Campaign $past): float => (int) ($past->getAttribute('retained_quantity') ?? 0) / max(1, $past->target_quantity);
        $label = fn (string $value): string => mb_substr((string) preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}|\+?\d[\d\s()-]{7,}\d/u', '[contacto omitido]', $value), 0, 120);

        return ['version' => $this->version($campaign), 'features' => [
            'asOf' => $asOf->toIso8601String(), 'startedAt' => $startedAt->toIso8601String(), 'deadline' => $campaign->deadline->toIso8601String(),
            'targetQuantity' => $campaign->target_quantity, 'currentQuantity' => $quantity, 'participantCount' => (int) $totals->getAttribute('participants'),
            'category' => $label($campaign->category), 'location' => $label($campaign->location), 'unit' => $label($campaign->unit),
            'elapsedHours' => round($elapsedHours, 2), 'remainingHours' => round($remainingHours, 2),
            'averageUnitsPerDay' => round($averageRate, 2),
            'recentUnitsPerDay' => $recentHours > 0 ? round($recentQuantity / ($recentHours / 24), 2) : 0,
            'previousUnitsPerDay' => $previousHours > 0 ? round($previousQuantity / ($previousHours / 24), 2) : null,
            'largestParticipationShare' => $quantity > 0 ? round((int) $totals->getAttribute('largest') / $quantity, 3) : 0,
            'linearProjection' => min(2147483647, (int) round($quantity + $averageRate * $remainingHours / 24)),
            'retainedInterestSeries' => $series,
            'historicalContext' => ['campaignCount' => $history->count(), 'sameLocationCount' => $sameLocation->count(),
                'averageRetainedTargetRatio' => $history->isNotEmpty() ? round((float) $history->avg($ratio), 3) : null,
                'sameLocationRetainedTargetRatio' => $sameLocation->isNotEmpty() ? round((float) $sameLocation->avg($ratio), 3) : null,
                'basis' => 'Current retained quantities of earlier campaigns, not verified snapshots at their deadlines. Not a training set or measured success rate.'],
            'limitations' => ['Retained quantities grouped by registration date; edits, reactivations and cancellations are not reconstructed as events.',
                'Start uses publication audit where available; otherwise campaign creation is a proxy.', 'No calibrated outcome model is available.'],
        ]];
    }

    public function version(Campaign $campaign): string
    {
        $totals = $campaign->participations()->selectRaw("count(*) as records, coalesce(sum(case when status in ('pendente','confirmada') then quantity else 0 end),0) as units, max(updated_at) as changed")->first();
        $lastChange = AuditEntry::query()->where('subject_type', 'Participation')->whereIn('subject_id', $campaign->participations()->select('id'))->max('id');

        return hash('sha256', json_encode([$campaign->only(['id', 'status', 'purchase_phase', 'purchase_config', 'target_quantity', 'deadline', 'category', 'location', 'unit', 'updated_at']), $totals?->getAttributes(), $lastChange], JSON_THROW_ON_ERROR));
    }
}
