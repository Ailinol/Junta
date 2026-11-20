<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\User;
use App\Support\CollectivePurchase;
use App\Support\PlatformPricing;
use App\Support\ProposalSelection;
use App\Support\TestPaymentGateway;
use Database\Seeders\CollectivePurchasePilotSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreparePurchaseDemonstration extends Command
{
    protected $signature = 'junta:demo-purchases {action=status : reset, open ou status}
        {--scenario= : success ou refund}
        {--minutes=15 : Prazo real de pagamento, de 1 a 120 minutos}';

    protected $description = 'Prepara e consulta exclusivamente a base SQLite descartável da demonstração.';

    public function handle(CollectivePurchase $purchases, ProposalSelection $selection, TestPaymentGateway $gateway): int
    {
        $action = $this->argument('action');
        if (! in_array($action, ['reset', 'open', 'status'], true)) {
            $this->error('Acção inválida. Use reset, open ou status.');

            return self::FAILURE;
        }
        if (! app()->environment(['local', 'testing']) || ! $gateway->available()) {
            $this->error('Use local/testing com o prestador de teste activado.');

            return self::FAILURE;
        }
        $directory = storage_path('app');
        $database = $directory.DIRECTORY_SEPARATOR.'purchase-demonstration.sqlite';
        if (! is_dir($directory) || is_link(storage_path()) || is_link($directory) || is_link($database)) {
            $this->error('O destino da demonstração deve ser um ficheiro local em storage/app, sem ligações simbólicas.');

            return self::FAILURE;
        }
        if ($action !== 'reset' && ! is_file($database)) {
            $this->error('Prepare primeiro a demonstração com reset.');

            return self::FAILURE;
        }
        if ($action === 'open' && (! in_array($this->option('scenario'), ['success', 'refund'], true) || filter_var($this->option('minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 120]]) === false)) {
            $this->error('Indique --scenario=success ou refund e --minutes entre 1 e 120.');

            return self::FAILURE;
        }
        if (! is_file($database) && ! touch($database)) {
            $this->error('Não foi possível criar a base da demonstração.');

            return self::FAILURE;
        }

        $previous = DB::getDefaultConnection();
        config(['database.connections.purchase_demonstration' => [
            'driver' => 'sqlite', 'database' => $database, 'url' => null, 'prefix' => '',
            'foreign_key_constraints' => true, 'busy_timeout' => 5000,
        ]]);
        DB::purge('purchase_demonstration');
        DB::setDefaultConnection('purchase_demonstration');
        try {
            if ($action === 'reset') {
                if ($this->call('migrate:fresh', ['--database' => 'purchase_demonstration', '--force' => true, '--no-interaction' => true]) !== self::SUCCESS) {
                    return self::FAILURE;
                }
                if ($this->call('db:seed', ['--database' => 'purchase_demonstration', '--class' => CollectivePurchasePilotSeeder::class, '--force' => true, '--no-interaction' => true]) !== self::SUCCESS) {
                    return self::FAILURE;
                }
                foreach ([1 => 'Arroz', 2 => 'Feijão'] as $id => $product) {
                    Campaign::findOrFail($id)->update([
                        'title' => '[DEMO] '.$product.' em sacos de 5 kg', 'unit' => 'sacos de 5 kg', 'delivery_mode' => 'recolha',
                        'description' => 'Produto fictício: '.$product.' em embalagem selada de 5 kg. Compra para recolha em Maputo. Identificação, preço e condições são exemplos para esta sessão; não existe mercadoria nem dinheiro real.',
                    ]);
                    Proposal::query()->where('campaign_id', $id)->update(['delivery_mode' => 'recolha']);
                }
                $this->info('Demonstração reposta: duas campanhas em interesse, sem pedidos nem pagamentos. Sessões anteriores eliminadas.');
            } elseif ($action === 'open') {
                $this->open($purchases, $selection);
            }
            $this->line('Base exclusiva: '.$database);
            $this->table(['Cenário', 'Campanha', 'Fase', 'Prazo de Maputo', 'Acompanhamento'], Campaign::query()->orderBy('id')->get()->map(fn (Campaign $campaign): array => [
                $campaign->id === 1 ? 'success' : 'refund', $campaign->title, $campaign->purchase_phase,
                $campaign->payment_deadline?->timezone('Africa/Maputo')->format('Y-m-d H:i:s') ?? 'Ainda não aberto',
                route('consumidor.acompanhamento.show', ['id' => $campaign->id], absolute: false),
            ])->all());

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error(implode(' ', $exception->validator->errors()->all()));

            return self::FAILURE;
        } finally {
            DB::purge('purchase_demonstration');
            DB::setDefaultConnection($previous);
        }
    }

    private function open(CollectivePurchase $purchases, ProposalSelection $selection): void
    {
        $id = $this->option('scenario') === 'success' ? 1 : 2;
        DB::transaction(function () use ($id, $purchases, $selection): void {
            $campaign = Campaign::findOrFail($id);
            if ($campaign->status !== 'ativa' || $campaign->purchase_phase !== 'interest' || $campaign->purchase_config !== null) {
                throw ValidationException::withMessages(['scenario' => 'Este cenário já avançou. Consulte status ou reponha ambos com reset.']);
            }
            $organizer = User::query()->where('email', 'organizador@pilot.junta.test')->sole();
            $proposal = Proposal::query()->where('campaign_id', $campaign->id)->sole();
            $proposal->forceFill(['unit_price' => '100.10', 'minimum_quantity' => 3, 'available_quantity' => 10, 'delivery_days' => 3, 'valid_until' => now()->addDays(2), 'reserved_until' => now()->addDay(), 'transport_included' => false, 'transport_unit_cents' => 1000])->save();
            $purchases->closeInterest($organizer, $campaign);
            $selection->select($organizer, $campaign->fresh(), $proposal);
            $admin = User::query()->where('email', 'admin@pilot.junta.test')->sole();
            $quantity = (int) $campaign->participations()->where('status', '!=', 'cancelada')->sum('quantity');
            app(PlatformPricing::class)->quoteDelivery($admin, $campaign->fresh(), 1000 * $quantity, '[DEMO] Cotação de transporte fictícia');
            $purchases->openPayments($organizer, $campaign->fresh(), [
                'paymentDeadline' => now()->addMinutes((int) $this->option('minutes'))->toIso8601String(),
                'pickupPoint' => 'Ponto Junta de demonstração, Maputo (local fictício)',
            ]);
        });
        $this->info('Proposta de demonstração seleccionada com as condições submetidas. O comprador ainda tem de aceitar e pagar; o apuramento aguarda o prazo real.');
    }
}
