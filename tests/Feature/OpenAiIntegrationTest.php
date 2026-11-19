<?php

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\User;
use App\Support\OpenAiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../OpenAiTestHelpers.php';

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
    config(['inertia.ssr.enabled' => false, 'services.openai.enabled' => true, 'services.openai.key' => 'openai-test-secret', 'services.openai.model' => 'gpt-6.1-terra', 'services.openai.reasoning_effort' => 'low']);
});

/** @return array<string,mixed> */
function openAiDraft(): array
{
    return ['title' => 'Arroz em sacos', 'description' => 'Arroz branco em sacos selados de origem.', 'unit' => 'saco', 'targetQuantity' => 10, 'deadline' => now()->addDay()->toIso8601String(), 'deliveryMode' => 'recolha'];
}

test('OpenAI requests use private credentials bounded output and a strict per item schema', function () {
    fakeJuntaOpenAi();
    $organizer = User::factory()->create(['is_organizer' => true]);
    $this->actingAs($organizer)->postJson(route('ai.campaign'), openAiDraft())->assertOk()->assertJsonPath('model', 'gpt-6.1-terra');
    Http::assertSent(function (Request $request): bool {
        $format = $request['text']['format'];

        return $request->url() === 'https://api.openai.com/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer openai-test-secret')
            && $request['store'] === false && $request['max_output_tokens'] === 6000
            && $request['reasoning']['effort'] === 'low'
            && $request['input'][0]['role'] === 'system' && $request['input'][1]['role'] === 'user'
            && $format['type'] === 'json_schema' && $format['strict'] === true
            && $format['schema']['additionalProperties'] === false
            && $format['schema']['properties']['evaluations']['required'] === ['draft']
            && $format['schema']['properties']['evaluations']['properties']['draft']['additionalProperties'] === false;
    });
    Http::assertSentCount(1);
});

test('completed OpenAI output is found after reasoning and can span multiple text blocks', function () {
    $body = juntaOpenAiResponse(['draft' => ['clarity' => 1, 'confidence' => 0.8, 'contradiction' => 0, 'alignment' => 1]]);
    $text = $body['output'][0]['content'][0]['text'];
    $body['output'][0]['content'] = [['type' => 'output_text', 'text' => substr($text, 0, 20)], ['type' => 'output_text', 'text' => substr($text, 20)]];
    array_unshift($body['output'], ['type' => 'reasoning', 'summary' => []]);
    Http::fake(['https://api.openai.com/v1/responses' => Http::response($body)]);
    $this->actingAs(User::factory()->create(['is_organizer' => true]))->postJson(route('ai.campaign'), openAiDraft())->assertOk()->assertJsonPath('clarity', 50)->assertJsonPath('confidence', 80);
    expect(AuditEntry::where('action', 'ai.review.created')->count())->toBe(1);
});

test('incomplete refused malformed or unexpected OpenAI results never become an application analysis', function (string $failure) {
    $body = juntaOpenAiResponse(['draft' => ['clarity' => 2, 'confidence' => 0.9, 'contradiction' => 0, 'alignment' => 1]]);
    if ($failure === 'incomplete') {
        $body['status'] = 'incomplete';
        $body['incomplete_details'] = ['reason' => 'max_output_tokens'];
    } elseif ($failure === 'refusal') {
        array_unshift($body['output'][0]['content'], ['type' => 'refusal', 'refusal' => 'PROVIDER-SECRET']);
    } elseif ($failure === 'invalid-json') {
        $body['output'][0]['content'][0]['text'] = 'PROVIDER-SECRET';
    } elseif ($failure === 'missing-model') {
        unset($body['model']);
    } elseif ($failure === 'unexpected-root') {
        $body['output'][0]['content'][0]['text'] = json_encode(['evaluations' => ['draft' => ['clarity' => 2, 'confidence' => 1, 'contradiction' => 0, 'alignment' => 1]], 'selectedProposalId' => 999], JSON_THROW_ON_ERROR);
    } elseif ($failure === 'wrong-item') {
        $body = juntaOpenAiResponse(['unrequested' => ['clarity' => 2, 'confidence' => 1, 'contradiction' => 0, 'alignment' => 1]]);
    } elseif ($failure === 'invalid-metric') {
        $body = juntaOpenAiResponse(['draft' => ['clarity' => 2, 'confidence' => 2, 'contradiction' => 0, 'alignment' => 1]]);
    }
    Http::fake(['https://api.openai.com/v1/responses' => Http::response($body)]);
    $response = $this->actingAs(User::factory()->create(['is_organizer' => true]))->postJson(route('ai.campaign'), openAiDraft())->assertStatus(503)->assertJsonMissingPath('clarity');
    expect($response->getContent())->not->toContain('PROVIDER-SECRET')->not->toContain('openai-test-secret');
    expect(Campaign::count())->toBe(0);
    expect(AuditEntry::where('action', 'ai.review.created')->count())->toBe(0);
    Http::assertSentCount(1);
})->with(['incomplete', 'refusal', 'invalid-json', 'missing-model', 'unexpected-root', 'wrong-item', 'invalid-metric']);

test('invalid model settings and provider outages remain safe errors without automatic billing retries', function (int $status) {
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'PROVIDER-SECRET']], $status)]);
    $response = $this->actingAs(User::factory()->create(['is_organizer' => true]))->postJson(route('ai.campaign'), openAiDraft())->assertStatus(503);
    expect($response->getContent())->not->toContain('PROVIDER-SECRET')->not->toContain('openai-test-secret');
    Http::assertSentCount(1);
    expect(AuditEntry::where('action', 'ai.review.created')->count())->toBe(0);
})->with([400, 404, 500]);

test('the browser receives only the AI availability flag without credentials or server settings', function () {
    $this->actingAs(User::factory()->create(['is_organizer' => true]))->get(route('organizador.campanhas.criar'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('ai.enabled', true)->missing('ai.key')->missing('ai.apiKey')->missing('ai.model'));
    expect($this->get(route('organizador.campanhas.criar'))->getContent())->not->toContain('openai-test-secret');
    Http::assertNothingSent();
});

test('AI configuration status is private and performs no external request by default', function () {
    $this->artisan('junta:ai-status')->expectsOutputToContain('Fornecedor: OpenAI')->expectsOutputToContain('configurada (oculta)')->expectsOutputToContain('Nenhum pedido foi enviado')->doesntExpectOutputToContain('openai-test-secret')->assertSuccessful();
    Http::assertNothingSent();
});

test('the explicit AI connection check uses only a fictional draft and does not change business records', function () {
    fakeJuntaOpenAi();
    $this->artisan('junta:ai-status', ['--check' => true])->expectsOutputToContain('Ligação confirmada')->doesntExpectOutputToContain('openai-test-secret')->assertSuccessful();
    Http::assertSentCount(1);
    expect(Campaign::count())->toBe(0);
    expect(AuditEntry::count())->toBe(0);
});

test('disabled incomplete AI configuration cannot contact the provider even for an explicit check', function (string $setting, mixed $value) {
    config(['services.openai.'.$setting => $value]);
    expect(app(OpenAiClient::class)->enabled())->toBeFalse();
    $this->artisan('junta:ai-status', ['--check' => true])->expectsOutputToContain('antes do teste')->assertFailed();
    Http::assertNothingSent();
})->with([['enabled', false], ['key', null], ['model', '']]);

test('invalid or excessive item lists are rejected before any OpenAI request', function (array $items, string $message) {
    expect(fn () => app(OpenAiClient::class)->evaluate(['campaign' => [], 'items' => []], $items))->toThrow(RuntimeException::class, $message);
    Http::assertNothingSent();
})->with([
    'empty' => [[], 'A análise aceita entre 1 e 20 itens distintos.'],
    'duplicate' => [['draft', 'draft'], 'A análise aceita entre 1 e 20 itens distintos.'],
    'over limit' => [array_map(fn (int $id): string => 'p'.$id, range(1, 21)), 'A análise aceita entre 1 e 20 itens distintos.'],
    'unsafe key' => [['draft.extra'], 'Os itens da análise não são válidos.'],
]);
