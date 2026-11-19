<?php

namespace App\Console\Commands;

use App\Support\OpenAiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('junta:ai-status {--check : Faz uma análise fictícia curta na OpenAI; pode consumir tokens}')]
#[Description('Mostra a configuração da IA sem revelar a chave; só contacta a OpenAI com --check.')]
class CheckAiConfiguration extends Command
{
    public function handle(OpenAiClient $client): int
    {
        $this->info('Fornecedor: OpenAI');
        $this->line('Modelo: '.(string) config('services.openai.model'));
        $this->line('OPENAI_API_KEY: '.(filled(config('services.openai.key')) ? 'configurada (oculta)' : 'não configurada'));
        $this->line('Estado: '.($client->enabled() ? 'activa' : 'desactivada'));
        if (! $this->option('check')) {
            $this->line('Nenhum pedido foi enviado. Para testar a ligação depois de activar: php artisan junta:ai-status --check');

            return self::SUCCESS;
        }
        if (! $client->enabled()) {
            $this->error('Configure OPENAI_API_KEY e OPENAI_ENABLED=true no ambiente privado antes do teste.');

            return self::FAILURE;
        }
        $draft = ['title' => 'Arroz em sacos de 25 kg', 'description' => 'Arroz branco em sacos selados de origem, para recolha no ponto acordado.', 'unit' => 'saco', 'targetQuantity' => 10, 'deliveryMode' => 'recolha'];
        try {
            $result = $client->evaluate(['campaign' => $draft, 'items' => ['draft' => $draft]], ['draft']);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Ligação confirmada e resposta estruturada validada. Modelo devolvido: '.$result['model']);
        $this->line('O teste usou dados fictícios. Nenhuma campanha, proposta ou operação financeira foi alterada.');

        return self::SUCCESS;
    }
}
