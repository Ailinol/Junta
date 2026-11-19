<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\User;
use App\Support\PlatformAudit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MainApplicationAccessSeeder extends Seeder
{
    public const PASSWORD = 'Junta-Piloto!2026';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os acessos de apresentação só podem ser preparados em local/testing.');
        }

        DB::transaction(function (): void {
            $accounts = [
                'consumidor' => 'Isabel Nhantumbo',
                'organizador' => 'Luís Mabote',
                'fornecedor' => 'Daniel Matavele',
                'admin' => 'Administração Junta',
            ];

            foreach ($accounts as $role => $name) {
                $email = $role.'@pilot.junta.test';
                if (User::query()->where('email', $email)->exists()) {
                    continue;
                }

                $account = new User(['name' => $name, 'email' => $email, 'password' => self::PASSWORD]);
                $account->forceFill([
                    'is_admin' => $role === 'admin',
                    'is_organizer' => $role === 'organizador',
                    'email_verified_at' => now(),
                    'contact_preferences' => ['city' => 'Maputo', 'notifProposal' => true, 'notifReminder' => true, 'notifPickup' => true],
                ])->save();

                PlatformAudit::record($account, 'local.access.created', $account, ['source' => self::class, 'synthetic' => true, 'profile' => $role]);

                if ($role === 'fornecedor') {
                    if (Supplier::query()->where('nuit', '991305105')->exists()) {
                        throw new RuntimeException('O NUIT reservado ao perfil de apresentação já pertence a outro fornecedor. Nenhuma conta foi criada.');
                    }
                    $supplier = Supplier::create([
                        'user_id' => $account->id,
                        'company_name' => 'Comercial Horizonte de Maputo',
                        'nuit' => '991305105',
                        'contact_name' => $name,
                        'email' => 'comercial.horizonte@junta.example',
                        'phone' => '+258 84 206 3001',
                        'location' => 'Maputo',
                        'description' => 'Fornecimento por volume de produtos alimentares, consumíveis e materiais para compras colectivas.',
                        'categories' => ['alimentacao', 'construcao', 'eletronicos', 'escritorio', 'saude', 'agricola'],
                        'verified' => true,
                    ]);
                    PlatformAudit::record($account, 'supplier.created', $supplier, ['source' => self::class, 'synthetic' => true]);
                }
            }
        }, 5);

        $this->command?->info('Acessos locais preparados na ligação actual. Contas, palavras-passe e permissões existentes foram preservadas.');
    }
}
