<?php

namespace Database\Seeders;

use App\Models\AuditEntry;
use App\Models\Campaign;
use App\Models\OrganizerApplication;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use App\Support\PlatformAudit;
use App\Support\TestPaymentGateway;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SensitiveParameter;

class HostedDemoSeeder extends CompleteDemoSeeder
{
    public const PROFILES = [
        'admin' => 'Administração de demonstração',
        'organizador' => 'Carlos Nhantumbo',
        'fornecedor' => 'Paulo Cossa',
        'consumidor' => 'Ana Mucavele',
        'candidato' => 'Júlia Matavele',
        'candidato_rejeitado' => 'Ernesto Mabote',
        'fornecedor_pendente' => 'Rogério Macamo',
        'fornecedor2' => 'Marta Machava',
        'consumidor2' => 'Helena Chongo',
    ];

    private const DATASET = 'hosted-demo-v1';

    /**
     * @param  array<string, string>  $passwords
     */
    public function run(#[SensitiveParameter] array $passwords = []): void
    {
        if (config('app.demo_mode') !== true || ! app(TestPaymentGateway::class)->available()) {
            throw new RuntimeException('O povoamento exige APP_DEMO_MODE=true e pagamentos exclusivamente de teste.');
        }
        if (app()->isProduction() && DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('A demonstração publicada exige a ligação MySQL existente.');
        }
        foreach (array_keys(self::PROFILES) as $role) {
            if (! isset($passwords[$role]) || ! is_string($passwords[$role]) || strlen($passwords[$role]) < 16) {
                throw new RuntimeException('Forneça credenciais privadas de pelo menos 16 caracteres para todos os perfis.');
            }
        }

        DB::transaction(function () use ($passwords): void {
            $accounts = [];
            foreach (self::PROFILES as $role => $name) {
                $accounts[$role] = $this->account($role, $name, $passwords[$role]);
            }
            $suppliers = [
                $this->supplier($accounts['fornecedor'], 'Comercial Baía de Maputo [DEMO]', '999771001', true),
                $this->supplier($accounts['fornecedor2'], 'Distribuição Matola [DEMO]', '999771002', true),
            ];
            $this->supplier($accounts['fornecedor_pendente'], 'Comercial Nova Machava [DEMO]', '999771003', false);
            foreach (['organizador' => 'approved', 'candidato' => 'pending', 'candidato_rejeitado' => 'rejected'] as $role => $status) {
                $this->application($accounts[$role], $accounts['admin'], $status);
            }

            foreach ($this->scenarios() as $scenario) {
                $alreadyExists = Campaign::query()->where('title', '[DEMO] '.$scenario[0])->exists();
                $this->seedScenario($accounts['organizador'], $accounts['admin'], $accounts['fornecedor'], $suppliers[0], [$accounts['consumidor'], $accounts['consumidor2']], $scenario);
                if (! $alreadyExists) {
                    $campaign = Campaign::query()->where('title', '[DEMO] '.$scenario[0])->firstOrFail();
                    if ($campaign->canReceiveProposals()) {
                        $price = Money::cents($scenario[3]) + 150;
                        $proposal = Proposal::create([
                            'campaign_id' => $campaign->id, 'supplier_id' => $suppliers[1]->id,
                            'unit_price' => Money::decimal($price), 'available_quantity' => 25, 'minimum_quantity' => 5,
                            'total_estimated' => Money::decimal(Money::multiply($price, 25)), 'delivery_days' => 4,
                            'delivery_mode' => 'ambos', 'valid_until' => now()->addDays(10), 'reserved_until' => now()->addDays(8),
                            'transport_included' => true, 'transport_unit_cents' => 0, 'status' => 'submetida', 'submitted_at' => now(),
                            'notes' => '[DEMO] Proposta alternativa fictícia, com transporte incluído para comparar condições.',
                        ]);
                        PlatformAudit::record($accounts['fornecedor2'], 'proposal.created', $proposal, ['source' => self::class, 'synthetic' => true]);
                    }
                }
            }
        }, 5);
    }

    private function account(string $role, string $name, #[SensitiveParameter] string $password): User
    {
        $marker = AuditEntry::query()->where('action', 'demo.account.created')
            ->where('subject_type', 'User')->where('changes->dataset', self::DATASET)->where('changes->profile', $role)->first();
        if ($marker) {
            return User::query()->findOrFail($marker->subject_id);
        }
        $email = $role.'@demo.junta.test';
        if (User::query()->where('email', $email)->exists()) {
            throw new RuntimeException('O email reservado '.$email.' já pertence a uma conta não criada por este povoamento. Nenhum acesso foi alterado.');
        }
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill([
            'is_admin' => $role === 'admin', 'is_organizer' => $role === 'organizador', 'email_verified_at' => now(),
            'contact_preferences' => ['city' => 'Maputo', 'notifProposal' => true, 'notifReminder' => true, 'notifPickup' => true],
        ])->save();
        PlatformAudit::record($user, 'demo.account.created', $user, ['dataset' => self::DATASET, 'profile' => $role, 'synthetic' => true]);

        return $user;
    }

    private function supplier(User $user, string $company, string $nuit, bool $verified): Supplier
    {
        $existing = $user->supplier()->first();
        if ($existing) {
            return $existing;
        }
        if (Supplier::query()->where('nuit', $nuit)->exists()) {
            throw new RuntimeException('Um NUIT reservado à demonstração já está em uso. Nenhum registo foi substituído.');
        }

        return Supplier::create([
            'user_id' => $user->id, 'company_name' => $company, 'nuit' => $nuit, 'contact_name' => $user->name,
            'email' => $user->email, 'phone' => '+258 84 000 1101', 'location' => 'Maputo',
            'description' => 'Fornecedor fictício para experimentar propostas, fornecimento e aprovação no Junta.',
            'categories' => ['alimentacao', 'construcao', 'eletronicos', 'escritorio', 'saude', 'agricola', 'outro'], 'verified' => $verified,
        ]);
    }

    private function application(User $user, User $admin, string $status): void
    {
        if ($user->organizerApplication()->exists()) {
            return;
        }
        $application = new OrganizerApplication([
            'full_name' => $user->name, 'phone' => '+258 84 000 1102', 'location' => 'Maputo',
            'motivation' => '[DEMO] Quero organizar compras da comunidade e coordenar a recolha num ponto combinado.',
        ]);
        $application->forceFill([
            'user_id' => $user->id, 'status' => $status,
            'reviewed_by' => $status === 'pending' ? null : $admin->id,
            'reviewed_at' => $status === 'pending' ? null : now(),
            'decision_reason' => match ($status) {
                'approved' => '[DEMO] Plano de organização e contactos conferidos.',
                'rejected' => '[DEMO] Complete as informações do ponto de recolha.',
                default => null,
            },
        ])->save();
        PlatformAudit::record($user, 'organizer.application.created', $application, ['source' => self::class, 'synthetic' => true]);
    }

    /** @return list<array{0:string,1:string,2:string,3:numeric-string,4:string}> */
    private function scenarios(): array
    {
        return [
            ['Farinha de milho em sacos de 25 kg', 'alimentacao', 'saco', '950.00', 'draft'],
            ['Arroz branco em sacos de 25 kg', 'alimentacao', 'saco', '1250.00', 'interest'],
            ['Óleo alimentar em caixas de 12 litros', 'alimentacao', 'caixa', '1800.00', 'empty'],
            ['Cimento 32.5 em sacos de 50 kg', 'construcao', 'saco', '550.00', 'evaluation'],
            ['Sementes de milho certificadas', 'agricola', 'saco', '2100.00', 'selected'],
            ['Material escolar em kits', 'escritorio', 'kit', '450.00', 'payment'],
            ['Painéis solares de 100 W', 'eletronicos', 'painel', '4500.00', 'review'],
            ['Lâmpadas LED em caixas de 10', 'eletronicos', 'caixa', '650.00', 'supply'],
            ['Papel A4 em caixas de 5 resmas', 'escritorio', 'caixa', '1600.00', 'collection'],
            ['Kits de primeiros socorros', 'saude', 'kit', '850.00', 'completed'],
            ['Fertilizante NPK em sacos de 50 kg', 'agricola', 'saco', '2300.00', 'refunded'],
            ['Arroz branco em sacos de 5 kg', 'alimentacao', 'saco', '340.00', 'interest'],
            ['Feijão manteiga em sacos de 5 kg', 'alimentacao', 'saco', '480.00', 'interest'],
            ['Açúcar branco em pacotes de 1 kg', 'alimentacao', 'pacote', '95.00', 'interest'],
            ['Batata de consumo em sacos de 10 kg', 'alimentacao', 'saco', '650.00', 'interest'],
            ['Cebola seca em sacos de 5 kg', 'alimentacao', 'saco', '300.00', 'interest'],
            ['Água engarrafada em packs de 6', 'alimentacao', 'pack', '240.00', 'evaluation'],
            ['Sabão de lavar em caixas de 24', 'outro', 'caixa', '720.00', 'evaluation'],
            ['Detergente da loiça em embalagens de 5 L', 'outro', 'embalagem', '680.00', 'selected'],
            ['Cadernos de 96 folhas em packs de 10', 'escritorio', 'pack', '450.00', 'payment'],
            ['Enxadas para agricultura familiar', 'agricola', 'unidade', '380.00', 'payment'],
            ['Tubos de PVC para canalização', 'construcao', 'unidade', '280.00', 'supply'],
            ['Fraldas em pacotes de 20 unidades', 'saude', 'pacote', '450.00', 'collection'],
            ['Luvas de trabalho em caixas de 12 pares', 'outro', 'caixa', '600.00', 'completed'],
        ];
    }
}
