<?php

namespace Database\Seeders;

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\FinancialEntry;
use App\Models\OrganizerApplication;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\PurchaseIssue;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CollectivePurchase;
use App\Support\Money;
use App\Support\PlatformAudit;
use App\Support\ProposalSelection;
use App\Support\TestPaymentGateway;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class RealisticPlatformSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! app(TestPaymentGateway::class)->available()) {
            throw new RuntimeException('Os dados de apresentação exigem ambiente local/testing e pagamentos simulados activados.');
        }
        if (! Schema::hasTable('purchase_orders')) {
            throw new RuntimeException('Execute primeiro as migrações do fluxo de compra.');
        }
        $admin = User::query()->where('is_admin', true)->first();
        if (! $admin) {
            throw new RuntimeException('É necessária uma conta administrativa existente.');
        }

        DB::transaction(function () use ($admin): void {
            if ($this->shouldMaintainLegacyPresentation()) {
                $this->cleanLegacyLabels();
            }
            $admin->refresh();
            $owner = User::query()->where('is_organizer', true)->orderByRaw('email = ? desc', ['organizador@junta.example.test'])->first() ?? $admin;
            $consumers = User::query()->orderBy('id')->get()->all();
            $consumers = array_values(collect([...$consumers, ...$this->regionalConsumers('Maputo')])->unique('id')->all());
            $suppliers = $this->suppliers();
            if ($this->shouldMaintainLegacyPresentation()) {
                $this->applications($owner, $admin);
            }
            foreach ($this->catalogue() as $index => $scenario) {
                $members = in_array($scenario['city'], ['Maputo', 'Matola'], true) ? $consumers : $this->regionalConsumers($scenario['city']);
                $this->scenario($scenario, $owner, $admin, $suppliers, $members, $index);
            }
        }, 5);
        $this->command?->info('Dados fictícios de apresentação preparados. Acessos, permissões e progresso existentes foram preservados.');
    }

    protected function shouldMaintainLegacyPresentation(): bool
    {
        return true;
    }

    private function marked(?string $value): bool
    {
        return preg_match('/\[(?:TESTE|DEMO|PILOTO TESTE|DADOS DE TESTE)\]/iu', $value ?? '') === 1;
    }

    private function withoutMarkers(string $value): string
    {
        return trim((string) preg_replace('/\[(?:TESTE|DEMO|PILOTO TESTE|DADOS DE TESTE)\]\s*/iu', '', $value));
    }

    private function cleanLegacyLabels(): void
    {
        $names = [
            'consumidor@junta.example.test' => 'Ana Mucavele', 'organizador@junta.example.test' => 'Carlos Nhantumbo',
            'fornecedor@junta.example.test' => 'Paulo Cossa', 'fornecedor2@junta.example.test' => 'Marta Machava',
            'fornecedor.pendente@junta.example.test' => 'Rogério Macamo', 'candidato@junta.example.test' => 'Júlia Matavele',
            'candidato.rejeitado@junta.example.test' => 'Ernesto Mabote', 'consumidor2@junta.example.test' => 'Helena Chongo',
        ];
        $replacements = [];
        foreach (User::query()->get() as $user) {
            if (! $this->marked($user->name)) {
                continue;
            }
            $old = $user->name;
            $user->name = $names[$user->email] ?? $this->withoutMarkers($old);
            $preferences = $user->contact_preferences ?? [];
            foreach (['phone' => sprintf('+258 84 201 %04d', $user->id), 'city' => 'Maputo', 'address' => 'Bairro do Alto Maé, Maputo'] as $key => $value) {
                if (empty($preferences[$key]) || $this->marked((string) $preferences[$key]) || ($key === 'phone' && $preferences[$key] === '+258 84 000 0000')) {
                    $preferences[$key] = $value;
                }
            }
            $user->contact_preferences = $preferences;
            $user->save();
            $replacements[$old] = $user->name;
        }
        $companies = ['[TESTE] Fornecimentos Maputo' => 'Abastecimentos Baía de Maputo', '[TESTE] Distribuição Matola' => 'Matola Distribuição', '[TESTE] Fornecedor por verificar' => 'Comercial Nova Machava'];
        foreach (Supplier::query()->with('user')->get() as $supplier) {
            if (! $this->marked($supplier->company_name)) {
                continue;
            }
            $old = $supplier->company_name;
            $supplier->company_name = $companies[$old] ?? $this->withoutMarkers($old);
            $supplier->contact_name = $supplier->user->name ?? $this->withoutMarkers($supplier->contact_name);
            $supplier->email = 'comercial.'.Str::slug($supplier->company_name).'@junta.example';
            if ($supplier->phone === '+258 84 000 0000') {
                $supplier->phone = sprintf('+258 84 203 %04d', $supplier->id);
            }
            $supplier->description = 'Distribuição de produtos alimentares, materiais e consumíveis. Fornecimento por volume, com confirmação de disponibilidade e transporte por campanha.';
            $supplier->save();
            $replacements[$old] = $supplier->company_name;
        }
        foreach (Campaign::query()->get() as $campaign) {
            if (! $this->marked($campaign->title)) {
                continue;
            }
            $old = $campaign->title;
            $campaign->title = $this->withoutMarkers($old);
            $campaign->organizer_name = $campaign->organizer->name ?? $this->withoutMarkers($campaign->organizer_name);
            if ($this->marked($campaign->description)) {
                $campaign->description = $this->description($campaign->title, $campaign->unit, $campaign->location);
            }
            $campaign->tags = array_values(array_filter($campaign->tags ?? [], fn (string $tag): bool => ! in_array($tag, ['Dados de teste', 'Demonstração', 'Sem dinheiro real'], true)));
            $campaign->save();
            $replacements[$old] = $campaign->title;
            foreach ($campaign->participations as $participation) {
                if ($this->marked($participation->notes)) {
                    $participation->notes = 'Recolha no ponto combinado, preferencialmente ao fim da tarde.';
                    $participation->save();
                }
            }
            foreach ($campaign->proposals as $proposal) {
                if ($this->marked($proposal->notes)) {
                    $proposal->notes = $this->withoutMarkers($proposal->notes);
                    $proposal->save();
                }
            }
        }
        foreach (OrganizerApplication::query()->with('user')->get() as $application) {
            if ($this->marked($application->full_name) || $this->marked($application->motivation)) {
                $application->full_name = $application->user->name;
                $application->motivation = 'Pretendo reunir encomendas da comunidade e coordenar um ponto de recolha, com conferência das quantidades e comunicação regular aos participantes.';
                if ($this->marked($application->decision_reason)) {
                    $application->decision_reason = $application->status === 'rejected' ? 'Especifique o ponto de recolha e a equipa responsável pela distribuição.' : 'Contactos e plano de organização conferidos.';
                }
                $application->save();
            }
        }
        foreach (AuditEntry::query()->get() as $entry) {
            $actor = strtr($entry->actor_name, $replacements);
            $actor = str_replace('Preparação de dados de teste', 'Preparação de dados', $actor);
            if ($actor !== $entry->actor_name) {
                $entry->actor_name = $actor;
                $entry->save();
            }
        }
        foreach (DatabaseNotification::query()->get() as $notification) {
            $data = $notification->data;
            if ($notification->type === 'junta.test-data' && isset($data['message'])) {
                $data['message'] = strtr($data['message'], [
                    'Foram criadas participações de teste.' => 'As suas participações estão disponíveis.',
                    'A candidatura de teste' => 'A sua candidatura',
                    'experimente reenviar o pedido' => 'volte a enviar o pedido com as informações pedidas',
                ]);
            }
            array_walk_recursive($data, function (mixed &$value) use ($replacements): void {
                if (is_string($value)) {
                    $value = $this->withoutMarkers(strtr($value, $replacements));
                }
            });
            if ($data !== $notification->data) {
                $notification->data = $data;
                $notification->save();
            }
        }
    }

    private function account(string $email, string $name, string $city): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user) {
            return $user;
        }
        $user = new User(['name' => $name, 'email' => $email, 'password' => Str::random(40)]);
        $user->forceFill(['email_verified_at' => now()->subDays(30), 'created_at' => now()->subDays(45), 'contact_preferences' => ['phone' => '+258 84 204 3001', 'city' => $city, 'address' => 'Zona central, '.$city, 'notifProposal' => true, 'notifReminder' => true, 'notifPickup' => true]])->save();

        return $user;
    }

    /** @return list<Supplier> */
    private function suppliers(): array
    {
        $definitions = [
            ['fornecedor@junta.example.test', 'paulo.cossa@junta.example', 'Paulo Cossa', 'Abastecimentos Baía de Maputo', '991305101', 'Maputo'],
            ['fornecedor2@junta.example.test', 'marta.machava@junta.example', 'Marta Machava', 'Matola Distribuição', '991305102', 'Matola'],
            ['fatima.litoral@junta.example', 'fatima.litoral@junta.example', 'Fátima Chilala', 'Litoral Comercial', '991305103', 'Beira'],
            ['tomas.norte@junta.example', 'tomas.norte@junta.example', 'Tomás Nhampossa', 'Norte Equipamentos', '991305104', 'Nampula'],
        ];
        $result = [];
        foreach ($definitions as [$legacyEmail, $email, $name, $company, $nuit, $city]) {
            $user = User::query()->where('email', $legacyEmail)->first() ?? $this->account($email, $name, $city);
            $supplier = Supplier::query()->where('user_id', $user->id)->first();
            if (! $supplier) {
                $supplier = Supplier::create(['user_id' => $user->id, 'company_name' => $company, 'nuit' => $nuit, 'contact_name' => $user->name, 'email' => 'comercial.'.Str::slug($company).'@junta.example', 'phone' => '+258 84 204 3002', 'location' => $city, 'description' => 'Fornecimento por volume para comerciantes, famílias e organizações. Condições de preço e transporte discriminadas em cada proposta.', 'categories' => ['alimentacao', 'construcao', 'eletronicos', 'escritorio', 'saude', 'agricola'], 'verified' => true]);
            }
            if ($supplier->verified) {
                $result[] = $supplier;
            }
        }
        if (count($result) < 2) {
            throw new RuntimeException('São necessários pelo menos dois fornecedores verificados para preparar as comparações.');
        }

        return $result;
    }

    private function applications(User $owner, User $admin): void
    {
        $pending = User::query()->where('email', 'candidato@junta.example.test')->first() ?? $this->account('julia.matavele@junta.example', 'Júlia Matavele', 'Matola');
        if ($pending->organizerApplication()->where('status', '!=', 'pending')->exists()) {
            $pending = $this->account('isabel.sambo@junta.example', 'Isabel Sambo', 'Matola');
        }
        $rejected = User::query()->where('email', 'candidato.rejeitado@junta.example.test')->first() ?? $this->account('ernesto.mabote@junta.example', 'Ernesto Mabote', 'Maputo');
        $cases = [[$pending, 'pending'], [$rejected, 'rejected']];
        if ($owner->is_organizer) {
            $cases[] = [$owner, 'approved'];
        }
        foreach ($cases as [$user, $status]) {
            if ($user->organizerApplication()->exists()) {
                continue;
            }
            $application = new OrganizerApplication(['full_name' => $user->name, 'phone' => '+258 84 205 4001', 'location' => $user->contact_preferences['city'] ?? 'Maputo', 'motivation' => 'Quero coordenar compras de famílias e pequenos comerciantes, com um ponto de recolha e uma equipa para conferir e distribuir as encomendas.']);
            $application->forceFill(['user_id' => $user->id, 'status' => $status, 'decision_reason' => $status === 'rejected' ? 'Complete o plano de recolha e identifique a equipa responsável.' : ($status === 'approved' ? 'Plano de organização e contactos conferidos.' : null), 'reviewed_by' => $status === 'pending' ? null : $admin->id, 'reviewed_at' => $status === 'pending' ? null : now()->subDays(2)])->save();
        }
    }

    /** @return list<User> */
    private function regionalConsumers(string $city): array
    {
        $result = [];
        $names = match ($city) {
            'Beira' => ['Fátima Chissano', 'Samuel Mussa', 'Teresa Cossa'],
            'Chimoio' => ['Edson Nhachungue', 'Celina Nhantumbo', 'Jorge Mabote'],
            'Tete' => ['Maria Uamusse', 'José Amosse', 'Belmira Chongo'],
            'Quelimane' => ['Ivone Macuácua', 'Nélio Muando', 'Sara Matola'],
            default => ['Lídia Macuácua', 'Joaquim Mabote', 'Celina Macamo'],
        };
        foreach ($names as $index => $name) {
            $result[] = $this->account('cliente.'.Str::slug($city).'.'.($index + 1).'@junta.example', $name, $city);
        }

        return $result;
    }

    protected function description(string $title, string $unit, string $city): string
    {
        return $title.'. Fornecimento em embalagens de origem, com identificação do produto e conferência na recepção. A proposta deve discriminar o preço por '.$unit.', a quantidade disponível, o prazo e o transporte até '.$city.'. A recolha será organizada por pedido.';
    }

    /** @return list<array{key:string,title:string,category:string,unit:string,price:numeric-string,city:string,stage:string,ageHours?:int,pace?:string}> */
    protected function catalogue(): array
    {
        $rows = [
            ['detergente', 'Detergente líquido em embalagens de 5 L', 'outro', 'embalagem', '680.00', 'Maputo', 'draft'],
            ['arroz', 'Arroz agulha em sacos de 25 kg — Matola', 'alimentacao', 'saco', '1550.00', 'Matola', 'interest'],
            ['oleo', 'Óleo de soja em caixas de 12 garrafas — Maputo', 'alimentacao', 'caixa', '1920.00', 'Maputo', 'interest'],
            ['cimento', 'Cimento 32.5 em sacos de 50 kg — Beira', 'construcao', 'saco', '520.00', 'Beira', 'evaluation'],
            ['sementes', 'Sementes de milho em sacos de 10 kg — Chimoio', 'agricola', 'saco', '2450.00', 'Chimoio', 'selected'],
            ['cadernos', 'Cadernos escolares em caixas de 50 — Maputo', 'escritorio', 'caixa', '1850.00', 'Maputo', 'payment'],
            ['paineis', 'Painéis solares de 100 W — Tete', 'eletronicos', 'painel', '4800.00', 'Tete', 'review'],
            ['lampadas', 'Lâmpadas LED de 12 W em caixas de 10 — Matola', 'eletronicos', 'caixa', '720.00', 'Matola', 'supply'],
            ['papel', 'Papel A4 em caixas de cinco resmas — Maputo', 'escritorio', 'caixa', '1750.00', 'Maputo', 'collection'],
            ['mascaras', 'Máscaras descartáveis em caixas de 50 — Quelimane', 'saude', 'caixa', '450.00', 'Quelimane', 'partial'],
            ['socorros', 'Kits de primeiros socorros para equipas de trabalho — Maputo', 'saude', 'kit', '890.00', 'Maputo', 'completed'],
            ['fertilizante', 'Fertilizante NPK em sacos de 50 kg — Matola', 'agricola', 'saco', '2350.00', 'Matola', 'refunded'],
        ];

        return array_map(static fn (array $row): array => array_combine(['key', 'title', 'category', 'unit', 'price', 'city', 'stage'], $row), $rows);
    }

    /**
     * @param  array{key:string,title:string,category:string,unit:string,price:numeric-string,city:string,stage:string,ageHours?:int,pace?:string}  $scenario
     * @param  list<Supplier>  $suppliers
     * @param  list<User>  $consumers
     */
    private function scenario(array $scenario, User $owner, User $admin, array $suppliers, array $consumers, int $index): void
    {
        $seedKey = 'realistic-platform:'.$scenario['key'];
        if (AuditEntry::query()->where('action', 'campaign.created')->where('changes->seed_key', $seedKey)->exists()) {
            return;
        }
        $historical = in_array($scenario['stage'], ['review', 'supply', 'collection', 'partial', 'completed', 'refunded'], true);
        $observedAt = now();
        $started = $observedAt->copy()->subHours($scenario['ageHours'] ?? ($historical ? 2 : 0));
        Carbon::withTestNow($started, function () use ($scenario, $owner, $admin, $suppliers, $consumers, $index, $seedKey, $started, $observedAt): void {
            $supplier = $suppliers[$index % count($suppliers)];
            $members = array_values(array_filter($consumers, fn (User $user): bool => $user->id !== $supplier->user_id));
            if (isset($scenario['pace'])) {
                $members = array_slice($members, 0, $scenario['pace'] === 'sparse' ? 2 : 12);
            }
            $campaign = Campaign::create(['organizer_id' => $owner->id, 'organizer_name' => $owner->name, 'title' => $scenario['title'], 'description' => $this->description($scenario['title'], $scenario['unit'], $scenario['city']), 'category' => $scenario['category'], 'status' => $scenario['stage'] === 'draft' ? 'rascunho' : 'ativa', 'unit' => $scenario['unit'], 'target_quantity' => max(30, count($members) * 6), 'estimated_price' => $scenario['price'], 'deadline' => now()->addDays(7), 'location' => $scenario['city'], 'delivery_mode' => 'ambos']);
            PlatformAudit::record($owner, 'campaign.created', $campaign, ['source' => static::class, 'seed_key' => $seedKey, 'synthetic' => true]);
            if ($scenario['stage'] === 'draft') {
                return;
            }
            foreach ($members as $position => $consumer) {
                $quantity = match ($scenario['pace'] ?? null) {
                    'slow', 'sparse' => 1,
                    'near_goal' => 5 + ($position % 2),
                    'achieved' => 7,
                    default => 2 + (($position + 1) % 3),
                };
                $joinedAt = $started->copy();
                if ($scenario['stage'] === 'interest' && isset($scenario['pace'])) {
                    $fraction = match ($scenario['pace']) {
                        'rising' => $position < count($members) / 3 ? 0.1 + 0.3 * $position / count($members) : 0.8 + 0.18 * $position / count($members),
                        'slow' => 0.05 + 0.25 * $position / count($members),
                        default => ($position + 1) / (count($members) + 1),
                    };
                    $joinedAt = $joinedAt->addSeconds((int) round($started->diffInSeconds($observedAt) * $fraction));
                }
                Carbon::withTestNow($joinedAt, function () use ($campaign, $quantity, $position, $consumer): void {
                    $participation = new Participation(['campaign_id' => $campaign->id, 'quantity' => $quantity, 'status' => 'pendente', 'notes' => $position % 2 ? 'Avisar quando estiver disponível para recolha.' : 'Recolha no ponto combinado ao fim da tarde.']);
                    $participation->user_id = $consumer->id;
                    $participation->save();
                    PlatformAudit::record($consumer, 'participation.created', $participation);
                });
            }
            $demand = (int) $campaign->participations()->sum('quantity');
            $minimum = max(2, (int) ceil($demand * .6));
            $capacity = max(120, $demand * 3);
            $offer = null;
            foreach (range(0, min(2, count($suppliers) - 1)) as $offset) {
                $seller = $suppliers[($index + $offset) % count($suppliers)];
                $price = (int) round(Money::cents($scenario['price']) * (1 + $offset * .035));
                $proposal = Proposal::create(['campaign_id' => $campaign->id, 'supplier_id' => $seller->id, 'unit_price' => Money::decimal($price), 'available_quantity' => $capacity, 'minimum_quantity' => $minimum, 'total_estimated' => Money::decimal(Money::multiply($price, $capacity)), 'delivery_days' => 2 + $offset, 'delivery_mode' => 'ambos', 'valid_until' => now()->addDays(14), 'reserved_until' => now()->addDays(10), 'transport_included' => $offset !== 1, 'transport_unit_cents' => $offset === 1 ? 1500 : 0, 'status' => 'submetida', 'submitted_at' => now(), 'notes' => 'Embalagens de origem e quantidades conferidas na expedição. Total declarado para '.$capacity.' '.$scenario['unit'].'. Transporte '.($offset === 1 ? 'acrescido de 15,00 MZN por unidade.' : 'até ao ponto incluído no preço.')]);
                PlatformAudit::record($seller->user()->firstOrFail(), 'proposal.created', $proposal);
                $offer ??= $proposal;
            }
            if ($scenario['stage'] === 'interest') {
                return;
            }
            $purchases = app(CollectivePurchase::class);
            $purchases->closeInterest($owner, $campaign);
            if ($scenario['stage'] === 'evaluation') {
                return;
            }
            app(ProposalSelection::class)->select($owner, $campaign->fresh(), $offer);
            if ($scenario['stage'] === 'selected') {
                return;
            }
            $purchases->openPayments($owner, $campaign->fresh(), ['paymentDeadline' => now()->addMinutes($scenario['stage'] === 'payment' ? 2880 : 20)->toIso8601String(), 'pickupPoint' => 'Armazém de recolha, zona central de '.$scenario['city']]);
            $orders = [];
            foreach ($members as $position => $consumer) {
                $order = $purchases->issueOrder($consumer, $campaign->fresh(), ['deliveryMethod' => 'recolha', 'termsVersion' => config('purchases.terms_version'), 'acceptTerms' => true]);
                $orders[] = $order;
                if (($scenario['stage'] === 'payment' && $position > 1) || ($scenario['stage'] === 'refunded' && $position > 0)) {
                    continue;
                }
                $attempt = $purchases->pay($consumer, $order);
                if (($scenario['stage'] === 'review' && $position === 0) || ($scenario['stage'] === 'payment' && $position === 1)) {
                    continue;
                }
                app(TestPaymentGateway::class)->simulate($attempt->reference, 'paid');
                $purchases->reconcile($attempt);
            }
            if ($scenario['stage'] === 'payment') {
                return;
            }
            Carbon::withTestNow($started->copy()->addMinutes(30), function () use ($scenario, $purchases, $campaign, $owner, $admin, $orders, $members, $demand): void {
                $purchases->closeCampaign($campaign->fresh(), $owner);
                if (in_array($scenario['stage'], ['review', 'supply', 'refunded'], true)) {
                    return;
                }
                $received = $scenario['stage'] === 'partial' ? $orders[0]->quantity : $demand;
                $purchases->receiveSupply($owner, $campaign->fresh(), ['receivedQuantity' => $received, 'reference' => 'GR-'.now()->format('Ym').'-'.$campaign->id, 'reason' => 'Mercadoria conferida na recepção.']);
                foreach ($orders as $position => $order) {
                    if ($scenario['stage'] === 'partial' && $position > 0) {
                        continue;
                    }
                    $purchases->prepareOrder($owner, $order->fresh());
                    if ($scenario['stage'] === 'completed') {
                        $purchases->handoff($owner, $order->fresh(), (string) $order->fresh()->collection_code);
                    }
                }
                if ($scenario['stage'] === 'partial') {
                    $purchases->reportIssue($members[0], $orders[0]->fresh(), ['type' => 'damaged', 'description' => 'Uma das caixas apresenta a embalagem exterior danificada. Solicito conferência e substituição.']);
                }
                if ($scenario['stage'] === 'completed') {
                    $purchases->reportIssue($members[0], $orders[0]->fresh(), ['type' => 'missing', 'description' => 'Faltava uma unidade na embalagem entregue. Solicito a reposição.']);
                    $issue = PurchaseIssue::query()->where('purchase_order_id', $orders[0]->id)->firstOrFail();
                    $purchases->resolveIssue($admin, $issue, ['resolutionType' => 'resolved', 'reason' => 'Unidade reposta e conferida com o comprador.', 'reference' => 'OC-'.$campaign->id]);
                    $purchases->finance($admin, $campaign->fresh(), ['kind' => 'carrier_due', 'amount' => '250.00', 'reference' => 'TR-'.$campaign->id, 'reason' => 'Distribuição urbana dos pedidos entregues.']);
                    foreach (['supplier_due' => 'supplier_settled', 'carrier_due' => 'carrier_settled'] as $due => $settled) {
                        $amount = (int) FinancialEntry::query()->where('campaign_id', $campaign->id)->where('kind', $due)->sum('amount_cents');
                        $purchases->finance($admin, $campaign->fresh(), ['kind' => $settled, 'amount' => Money::decimal($amount), 'reference' => 'LC-'.$campaign->id.'-'.$settled, 'reason' => 'Liquidação conferida e registada no fecho da compra.']);
                    }
                    $purchases->complete($admin, $campaign->fresh());
                }
            });
        });
    }
}
