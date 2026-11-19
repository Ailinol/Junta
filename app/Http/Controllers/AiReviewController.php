<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiReviewRequest;
use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\User;
use App\Support\AiAdvisor;
use App\Support\CampaignForecast;
use App\Support\PlatformAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AiReviewController extends Controller
{
    public function campaign(AiReviewRequest $request, AiAdvisor $advisor): JsonResponse
    {
        $draft = $request->validated();

        return $this->respond($request->user(), $request->user(), 'campaign', fn (): array => $advisor->review($draft, null), $draft);
    }

    public function proposal(AiReviewRequest $request, AiAdvisor $advisor): JsonResponse
    {
        $campaign = Campaign::findOrFail($request->integer('campaignId'));
        abort_unless($request->user()->supplier?->verified, 403);
        abort_unless($campaign->canReceiveProposals(), 403);
        $draft = $request->safe()->except('campaignId');

        return $this->respond($request->user(), $request->user(), 'proposal', fn (): array => $advisor->review($draft, $campaign), $draft);
    }

    public function compare(Request $request, Campaign $campaign, AiAdvisor $advisor): JsonResponse
    {
        Gate::authorize('manage-campaign', $campaign);
        $data = $request->validate([
            'weights' => ['required', 'array:cost,delivery,clarity,history'],
            'weights.cost' => ['required', 'integer', 'between:0,100'],
            'weights.delivery' => ['required', 'integer', 'between:0,100'],
            'weights.clarity' => ['required', 'integer', 'between:0,100'],
            'weights.history' => ['sometimes', 'integer', 'between:0,100'],
        ]);
        $weights = ['cost' => (int) $data['weights']['cost'], 'delivery' => (int) $data['weights']['delivery'], 'clarity' => (int) $data['weights']['clarity'], 'history' => (int) ($data['weights']['history'] ?? 0)];
        if (array_sum($weights) !== 100) {
            throw ValidationException::withMessages(['weights' => 'Os pesos devem somar 100%.']);
        }

        return $this->respond($request->user(), $campaign, 'comparison', fn (): array => $advisor->compare($campaign, $weights), $weights);
    }

    public function forecast(Request $request, Campaign $campaign, CampaignForecast $forecast): JsonResponse
    {
        Gate::authorize('manage-campaign', $campaign);

        return $this->respond($request->user(), $campaign, 'forecast', fn (): array => $forecast->analyse($campaign), []);
    }

    public function decision(Request $request, AuditEntry $analysis): JsonResponse
    {
        abort_unless($analysis->action === 'ai.review.created' && (int) $analysis->getAttribute('user_id') === $request->user()->id, 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['reviewed', 'dismissed'])]]);
        PlatformAudit::record($request->user(), 'ai.review.'.$data['decision'], $analysis, ['analysisId' => $analysis->id]);

        return response()->json(['message' => 'Revisão registada. Nenhuma proposta foi seleccionada.']);
    }

    /**
     * @param  callable():array<string,mixed>  $analyse
     * @param  array<string,mixed>  $input
     */
    private function respond(User $actor, Model $subject, string $kind, callable $analyse, array $input): JsonResponse
    {
        try {
            $result = $analyse();
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
        $result['createdAt'] = now()->toIso8601String();
        $result['expiresAt'] ??= now()->addMinutes(5)->toIso8601String();
        $result['inputHash'] ??= hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        $entry = AuditEntry::create([
            'user_id' => $actor->id, 'actor_name' => $actor->name, 'action' => 'ai.review.created',
            'subject_type' => class_basename($subject), 'subject_id' => $subject->getKey(),
            'changes' => ['kind' => $kind, 'analysis' => $result],
        ]);

        return response()->json([...$result, 'analysisId' => $entry->id]);
    }
}
