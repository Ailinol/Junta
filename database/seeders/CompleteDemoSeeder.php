<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\Proposal;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CollectivePurchase;
use App\Support\Money;
use App\Support\PlatformAudit;
use App\Support\ProposalSelection;
use App\Support\TestPaymentGateway;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CompleteDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || DB::connection()->getDriverName() !== 'sqlite' || ! app(TestPaymentGateway::class)->available()) {
            throw new RuntimeException('Use a base SQLite isolada, em ambiente local/testing e com pagamentos de teste activados.');
        }
        $this->call(CollectivePurchasePilotSeeder::class);
        DB::transaction(function (): void {
            $owner = User::where('email', 'organizador@pilot.junta.test')->firstOrFail();
            $admin = User::where('email', 'admin@pilot.junta.test')->firstOrFail();
            $supplierUser = User::where('email', 'fornecedor@pilot.junta.test')->firstOrFail();
            $supplier = $supplierUser->supplier()->firstOrFail();
            $consumers = [User::where('email', 'consumidor@pilot.junta.test')->firstOrFail(), User::where('email', 'consumidor2@pilot.junta.test')->firstOrFail()];
            $scenarios = [
                ['Farinha de milho em sacos de 25 kg', 'alimentacao', 'saco de 25 kg', '950.00', 'draft'],
                ['Arroz branco em sacos de 25 kg', 'alimentacao', 'saco de 25 kg', '1250.00', 'interest'],
                ['Óleo alimentar em caixas de 12 litros', 'alimentacao', 'caixa', '1800.00', 'empty'],
                ['Cimento 32.5 em sacos de 50 kg', 'construcao', 'saco de 50 kg', '550.00', 'evaluation'],
                ['Sementes de milho certificadas', 'agricola', 'saco de 10 kg', '2100.00', 'selected'],
                ['Material escolar em kits', 'escritorio', 'kit', '450.00', 'payment'],
                ['Painéis solares de 100 W', 'eletronicos', 'painel', '4500.00', 'review'],
                ['Lâmpadas LED em caixas de 10', 'eletronicos', 'caixa', '650.00', 'supply'],
                ['Papel A4 em caixas de 5 resmas', 'escritorio', 'caixa', '1600.00', 'collection'],
                ['Kits de primeiros socorros', 'saude', 'kit', '850.00', 'completed'],
                ['Fertilizante NPK em sacos de 50 kg', 'agricola', 'saco de 50 kg', '2300.00', 'refunded'],
            ];
            foreach ($scenarios as $scenario) {
                $this->seedScenario($owner, $admin, $supplierUser, $supplier, $consumers, $scenario);
            }
        }, 5);
        $this->command?->info('Cenários [DEMO] preparados por fluxos reais. Contas existentes, palavras-passe e progresso foram preservados.');
    }

    /**
     * @param  list<User>  $consumers
     * @param  array{0:string,1:string,2:string,3:numeric-string,4:string}  $scenario
     */
    protected function seedScenario(User $owner, User $admin, User $supplierUser, Supplier $supplier, array $consumers, array $scenario): void
    {
        [$title, $category, $unit, $price, $stage] = $scenario;
        $existing = Campaign::where('title', '[DEMO] '.$title)->first();
        if ($existing) {
            if ($existing->organizer_id !== $owner->id) {
                throw new RuntimeException('Uma campanha com o mesmo nome de demonstração pertence a outra conta.');
            }

            return;
        }
        $campaign = Campaign::create([
            'organizer_id' => $owner->id, 'organizer_name' => $owner->name,
            'title' => '[DEMO] '.$title,
            'description' => 'Exemplo fictício para experimentar o Junta. Produto: '.$title.'. Embalagem selada, identificação de origem e validade aplicável. Conferência na recepção. Preço, quantidades e condições são dados de demonstração; não existe uma compra real.',
            'category' => $category, 'status' => $stage === 'draft' ? 'rascunho' : 'ativa',
            'target_quantity' => 10, 'unit' => $unit, 'estimated_price' => $price,
            'deadline' => now()->addDays(7), 'location' => $category === 'agricola' ? 'Matola' : 'Maputo',
            'delivery_mode' => 'ambos', 'tags' => ['Demonstração', 'Sem dinheiro real'],
        ]);
        PlatformAudit::record($owner, 'campaign.created', $campaign, ['source' => self::class, 'demo' => true]);
        if (in_array($stage, ['draft', 'empty'], true)) {
            return;
        }
        foreach ($consumers as $index => $consumer) {
            $participation = $campaign->participations()->make(['quantity' => $index === 0 ? 3 : 2, 'notes' => '[DEMO] Interesse fictício.']);
            $participation->user_id = $consumer->id;
            $participation->status = 'pendente';
            $participation->save();
            PlatformAudit::record($consumer, 'participation.created', $participation);
        }
        $unitCents = Money::cents($price);
        $proposal = Proposal::create([
            'campaign_id' => $campaign->id, 'supplier_id' => $supplier->id,
            'unit_price' => $price, 'available_quantity' => 20, 'minimum_quantity' => $stage === 'refunded' ? 8 : 5,
            'total_estimated' => Money::decimal(Money::multiply($unitCents, 20)), 'delivery_days' => 3,
            'delivery_mode' => 'ambos', 'valid_until' => now()->addDays(10), 'reserved_until' => now()->addDays(8),
            'transport_included' => true, 'transport_unit_cents' => 0, 'status' => 'submetida', 'submitted_at' => now(),
            'notes' => '[DEMO] Produto conforme especificação, transporte ao ponto incluído e reserva confirmável. Sem encargos adicionais declarados.',
        ]);
        PlatformAudit::record($supplierUser, 'proposal.created', $proposal);
        if ($stage === 'interest') {
            return;
        }
        $purchases = app(CollectivePurchase::class);
        $purchases->closeInterest($owner, $campaign);
        if ($stage === 'evaluation') {
            return;
        }
        app(ProposalSelection::class)->select($owner, $campaign->fresh(), $proposal);
        if ($stage === 'selected') {
            return;
        }
        $purchases->openPayments($owner, $campaign->fresh(), [
            'paymentDeadline' => now()->addDay()->toIso8601String(),
            'pickupPoint' => 'Ponto Junta de demonstração, Maputo',
        ]);
        $orders = [];
        foreach ($consumers as $index => $consumer) {
            $order = $purchases->issueOrder($consumer, $campaign->fresh(), ['deliveryMethod' => 'recolha', 'termsVersion' => config('purchases.terms_version'), 'acceptTerms' => true]);
            $orders[] = $order;
            if ($index === 0 || ! in_array($stage, ['payment', 'refunded'], true)) {
                $payment = $purchases->pay($consumer, $order);
                if ($stage !== 'review' || $index === 0) {
                    app(TestPaymentGateway::class)->simulate($payment->reference, 'paid');
                    $purchases->reconcile($payment);
                }
            }
        }
        if ($stage === 'payment') {
            return;
        }
        $campaign->refresh();
        $campaign->payment_deadline = now()->subMinute();
        $campaign->save();
        PurchaseOrder::where('campaign_id', $campaign->id)->update(['payment_deadline' => $campaign->payment_deadline]);
        $purchases->closeCampaign($campaign->fresh(), $owner);
        if (in_array($stage, ['review', 'refunded', 'supply'], true)) {
            return;
        }
        $purchases->receiveSupply($owner, $campaign->fresh(), ['receivedQuantity' => 5, 'reference' => '[DEMO] Guia de recepção '.$campaign->id, 'reason' => 'Mercadoria fictícia conferida.']);
        foreach ($orders as $order) {
            $purchases->prepareOrder($owner, $order->fresh());
            if ($stage === 'completed') {
                $purchases->handoff($owner, $order->fresh(), (string) $order->fresh()->collection_code);
            }
        }
        if ($stage === 'completed') {
            $due = (int) FinancialEntry::where('campaign_id', $campaign->id)->where('kind', 'supplier_due')->sum('amount_cents');
            $purchases->finance($admin, $campaign->fresh(), ['kind' => 'supplier_settled', 'amount' => Money::decimal($due), 'reference' => '[DEMO] Liquidação '.$campaign->id, 'reason' => 'Liquidação fictícia para testar o encerramento.']);
            $purchases->complete($admin, $campaign->fresh());
        }
    }
}
