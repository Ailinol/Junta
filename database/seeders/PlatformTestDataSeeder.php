<?php

namespace Database\Seeders;

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\OrganizerApplication;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PlatformTestDataSeeder extends Seeder
{
    public const PASSWORD = 'Junta-Teste!2026';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os dados de teste só podem ser criados nos ambientes local e testing.');
        }

        DB::transaction(function (): void {
            $consumer = $this->account('consumidor', 'Consumidor');
            $organizer = $this->account('organizador', 'Organizador', true);
            $supplierUser = $this->account('fornecedor', 'Fornecedor');
            $secondSupplierUser = $this->account('fornecedor2', 'Segundo fornecedor');
            $pendingSupplierUser = $this->account('fornecedor.pendente', 'Fornecedor por verificar');
            $candidate = $this->account('candidato', 'Candidato a organizador');
            $rejected = $this->account('candidato.rejeitado', 'Candidato rejeitado');
            $participant = $this->account('consumidor2', 'Segundo consumidor');

            $supplier = $this->supplier($supplierUser, '999000101', '[TESTE] Fornecimentos Maputo', true);
            $secondSupplier = $this->supplier($secondSupplierUser, '999000102', '[TESTE] Distribuição Matola', true);
            $this->supplier($pendingSupplierUser, '999000103', '[TESTE] Fornecedor por verificar', false);

            $reviewer = User::query()->where('email', 'lilianolicumba42@gmail.com')->where('is_admin', true)->first();
            foreach ([[$candidate, 'pending'], [$rejected, 'rejected'], [$organizer, 'approved']] as [$account, $status]) {
                OrganizerApplication::firstOrCreate(['user_id' => $account->id], [
                    'full_name' => $account->name, 'phone' => '+258 84 000 0000', 'location' => 'Maputo',
                    'motivation' => '[TESTE] Pretendo organizar compras colectivas para a comunidade. Cenário de avaliação administrativa.',
                    'status' => $status, 'decision_reason' => $status === 'rejected' ? '[TESTE] Especifique melhor o tipo de campanhas e a comunidade que pretende organizar.' : ($status === 'approved' ? '[TESTE] Acesso inicial preparado para testar a gestão de campanhas.' : null),
                    'reviewed_by' => $status === 'pending' ? null : $reviewer?->id,
                    'reviewed_at' => $status === 'pending' ? null : now()->subDay(),
                ]);
            }

            $scenarios = [
                ['Cimento para a comunidade', 'construcao', 'ativa', 'sacos', 200, 850, 'construction', 21, 0],
                ['Óleo de coco em grupo', 'alimentacao', 'ativa', 'litros', 300, 450, 'food', 7, 0],
                ['Equipamento solar em preparação', 'eletronicos', 'rascunho', 'kits', 50, 19000, 'solar', 30, 0],
                ['Sementes para a próxima época', 'agricola', 'em_avaliacao', 'sacos', 500, 320, 'agriculture', 14, 1],
                ['Kits de escritório', 'escritorio', 'em_avaliacao', 'kits', 100, 2500, 'office', 10, 1],
                ['Kits de primeiros socorros', 'saude', 'proposta_selecionada', 'kits', 100, 750, 'health', 5, 2],
                ['Material escolar confirmado', 'escritorio', 'confirmada', 'kits', 100, 1200, 'office', 5, 2],
                ['Painéis solares em fornecimento', 'eletronicos', 'em_fornecimento', 'painéis', 100, 9500, 'solar', -2, 3],
                ['Material de escritório concluído', 'escritorio', 'concluida', 'caixas', 100, 600, 'office', -20, 4],
                ['Compra de fertilizante cancelada', 'agricola', 'cancelada', 'sacos', 100, 1500, 'agriculture', -5, 5],
            ];
            foreach ($scenarios as [$name, $category, $status, $unit, $target, $price, $image, $days, $months]) {
                $title = '[TESTE] '.$name;
                $campaign = Campaign::firstOrCreate(['title' => $title], [
                    'organizer_id' => $organizer->id, 'organizer_name' => $organizer->name,
                    'description' => '[DADOS DE TESTE] Campanha preparada para experimentar o fluxo do Junta. Quantidades, preços e condições são fictícios. Não representa uma compra real.',
                    'category' => $category, 'status' => $status, 'target_quantity' => $target, 'unit' => $unit,
                    'estimated_price' => $price, 'deadline' => now()->addDays($days), 'location' => $category === 'agricola' ? 'Nampula' : 'Maputo',
                    'delivery_mode' => 'ambos', 'image_url' => url('/images/campaigns/'.$image.'-illustration.jpg'),
                    'tags' => ['Dados de teste', 'Imagem ilustrativa'],
                    'created_at' => now()->subMonthsNoOverflow($months)->subDays(3),
                ]);

                if (! $campaign->wasRecentlyCreated) {
                    continue;
                }

                AuditEntry::create(['actor_name' => 'Preparação de dados de teste', 'action' => 'campaign.created', 'subject_type' => 'Campaign', 'subject_id' => $campaign->id, 'changes' => ['source' => self::class, 'testData' => true, 'title' => $title]]);
                if ($status === 'rascunho' || $name === 'Óleo de coco em grupo') {
                    continue;
                }

                foreach ([[$consumer, 20], [$participant, 30], [$candidate, 25]] as [$user, $quantity]) {
                    Participation::firstOrCreate(['campaign_id' => $campaign->id, 'user_id' => $user->id], [
                        'quantity' => $quantity, 'status' => in_array($status, ['confirmada', 'em_fornecimento', 'concluida'], true) ? 'confirmada' : ($status === 'cancelada' ? 'cancelada' : 'pendente'),
                        'notes' => '[TESTE] Participação fictícia para validar a procura agregada e o acompanhamento.',
                        'created_at' => $campaign->created_at->copy()->addDay(),
                    ]);
                }
                if (in_array($status, ['ativa', 'cancelada'], true)) {
                    continue;
                }

                $selected = in_array($status, ['proposta_selecionada', 'confirmada', 'em_fornecimento', 'concluida'], true);
                $this->proposal($campaign, $supplier, round($price * .9, 2), 30, 12, $selected ? 'recusada' : 'submetida', '[TESTE] Menor preço unitário, mas quantidade insuficiente para a procura. Transporte não incluído; pagamento antecipado.');
                $this->proposal($campaign, $secondSupplier, round($price * .95, 2), 100, 3, $selected ? 'aceite' : 'em_avaliacao', '[TESTE] Quantidade suficiente e prazo mais curto. Transporte incluído na localização indicada; pagamento a combinar.');
            }

            $consumer->notifications()->firstOrCreate(['id' => 'a1e298cb-f474-4b52-bfd4-000000000001'], [
                'type' => 'junta.test-data', 'data' => ['title' => '[TESTE] Participações disponíveis', 'message' => 'Foram criadas participações de teste. Consulte a sua área para acompanhar as campanhas.', 'type' => 'info', 'link' => route('consumidor.campanhas.index', absolute: false)],
            ]);
            $candidate->notifications()->firstOrCreate(['id' => 'a1e298cb-f474-4b52-bfd4-000000000002'], [
                'type' => 'junta.test-data', 'data' => ['title' => '[TESTE] Candidatura pendente', 'message' => 'A candidatura de teste aguarda decisão administrativa. Ainda não tem privilégios de organizador.', 'type' => 'warning', 'link' => route('consumidor.candidatura', absolute: false)],
            ]);
            $rejected->notifications()->firstOrCreate(['id' => 'a1e298cb-f474-4b52-bfd4-000000000003'], [
                'type' => 'junta.test-data', 'data' => ['title' => '[TESTE] Rever candidatura', 'message' => 'A candidatura de teste foi rejeitada. Consulte o motivo e experimente reenviar o pedido.', 'type' => 'warning', 'link' => route('consumidor.candidatura', absolute: false)],
            ]);
        });
        $this->command?->info('Dados [TESTE] preparados. Registos e palavras-passe existentes foram preservados.');
    }

    private function account(string $alias, string $name, bool $organizer = false): User
    {
        $email = $alias.'@junta.example.test';
        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            if (! str_starts_with($existing->name, '[TESTE] ')) {
                throw new RuntimeException('Já existe uma conta não identificada como teste com o email '.$email.'. Nenhum dado foi alterado.');
            }

            return $existing;
        }

        return User::factory()->create([
            'name' => '[TESTE] '.$name, 'email' => $email, 'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(), 'is_organizer' => $organizer, 'is_admin' => false,
            'contact_preferences' => ['phone' => '+258 84 000 0000', 'city' => 'Maputo', 'address' => '[TESTE] Bairro de demonstração', 'notifProposal' => true, 'notifReminder' => true, 'notifPickup' => true],
        ]);
    }

    private function supplier(User $user, string $nuit, string $name, bool $verified): Supplier
    {
        $existing = Supplier::query()->where('nuit', $nuit)->first();
        if ($existing && $existing->user_id !== $user->id) {
            throw new RuntimeException('O NUIT de teste '.$nuit.' já pertence a outro fornecedor. Nenhum dado foi alterado.');
        }

        return Supplier::firstOrCreate(['user_id' => $user->id], ['company_name' => $name, 'nuit' => $nuit, 'contact_name' => $user->name, 'email' => $user->email, 'phone' => '+258 84 000 0000', 'location' => 'Maputo', 'categories' => ['construcao', 'alimentacao', 'eletronicos', 'escritorio', 'saude', 'agricola'], 'description' => '[DADOS DE TESTE] Perfil comercial fictício para experimentar propostas e verificação administrativa.', 'verified' => $verified]);
    }

    private function proposal(Campaign $campaign, Supplier $supplier, float $price, int $quantity, int $days, string $status, string $notes): void
    {
        Proposal::create(['campaign_id' => $campaign->id, 'supplier_id' => $supplier->id, 'unit_price' => $price, 'available_quantity' => $quantity, 'minimum_quantity' => 1, 'reserved_until' => $campaign->deadline->copy()->addDays(15), 'transport_included' => true, 'transport_unit_cents' => 0, 'total_estimated' => round($price * $quantity, 2), 'delivery_days' => $days, 'delivery_mode' => 'entrega', 'valid_until' => $campaign->deadline->copy()->addDays(15), 'status' => $status, 'notes' => $notes, 'submitted_at' => $campaign->created_at->copy()->addDays(2)]);
    }
}
