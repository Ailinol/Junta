<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** @param array<string,array<string,float|int>> $evaluations
 * @return array<string,mixed>
 */
function juntaOpenAiResponse(array $evaluations, string $status = 'completed'): array
{
    return ['id' => 'resp_test', 'model' => 'gpt-6.1-terra', 'status' => $status, 'output' => [['type' => 'message', 'role' => 'assistant', 'status' => 'completed', 'content' => [['type' => 'output_text', 'text' => json_encode(['evaluations' => $evaluations], JSON_THROW_ON_ERROR)]]]]];
}

/** @param array<string,array<string,float|int>> $evaluations */
function fakeJuntaOpenAi(array $evaluations = []): void
{
    Http::fake(['https://api.openai.com/v1/responses' => function (Request $request) use ($evaluations) {
        $result = [];
        foreach (array_keys($request['text']['format']['schema']['properties']['evaluations']['properties']) as $item) {
            $result[$item] = [...['clarity' => 2, 'confidence' => 0.95, 'contradiction' => 0.01, 'alignment' => 0.98], ...($evaluations[$item] ?? [])];
        }

        return Http::response(juntaOpenAiResponse($result));
    }]);
}
