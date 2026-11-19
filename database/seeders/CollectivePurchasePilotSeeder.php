<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CollectivePurchasePilotSeeder extends Seeder
{
    public const PASSWORD = 'Junta-Piloto!2026';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('O piloto de teste está bloqueado em produção.');
        }
        DB::transaction(function (): void {
            $accounts = [];
            foreach (['consumidor', 'consumidor2', 'organizador', 'fornecedor', 'admin'] as $role) {
                $email = $role.'@pilot.junta.test';
                $name = '[PILOTO TESTE] '.$role;
                $account = User::query()->where('email', $email)->first();
                if ($account && ($account->name !== $name || ($role === 'admin' && ! $account->is_admin) || ($role === 'organizador' && ! $account->is_organizer))) {
                    throw new RuntimeException('Conta em conflito com o cenário do piloto. Nenhum registo foi alterado.');
                }
                if (! $account) {
                    $account = new User(['name' => $name, 'email' => $email, 'password' => self::PASSWORD]);
                    $account->forceFill(['is_admin' => $role === 'admin', 'is_organizer' => $role === 'organizador', 'email_verified_at' => now()])->save();
                }
                $accounts[$role] = $account;
            }
            $supplier = Supplier::firstOrCreate(['user_id' => $accounts['fornecedor']->id], [
                'company_name' => '[PILOTO TESTE] Fornecedor', 'nuit' => '999888771',
                'contact_name' => 'Fornecedor do piloto', 'email' => $accounts['fornecedor']->email,
                'phone' => '+258 840000001', 'location' => 'Maputo', 'categories' => ['alimentacao'], 'verified' => true,
            ]);
            foreach (['Mínimo atingido' => 3, 'Mínimo insuficiente' => 2] as $scenario => $quantity) {
                $campaign = Campaign::firstOrCreate(['title' => '[PILOTO TESTE] '.$scenario], [
                    'organizer_id' => $accounts['organizador']->id, 'organizer_name' => $accounts['organizador']->name,
                    'description' => 'Cenário fictício para testar o fluxo. Confirme com mínimo 3, preço 100.10 MZN e reserva futura. Não movimenta dinheiro.',
                    'category' => 'alimentacao', 'status' => 'ativa', 'target_quantity' => 10, 'unit' => 'unidades',
                    'estimated_price' => '100.10', 'deadline' => now()->addDays(5), 'location' => 'Maputo', 'delivery_mode' => 'ambos',
                ]);
                if (! $campaign->wasRecentlyCreated) {
                    continue;
                }
                $participation = $campaign->participations()->make(['quantity' => $quantity, 'notes' => '[PILOTO TESTE]']);
                $participation->user_id = $accounts['consumidor']->id;
                $participation->status = 'pendente';
                $participation->save();
                Proposal::create([
                    'campaign_id' => $campaign->id, 'supplier_id' => $supplier->id, 'unit_price' => '100.10', 'available_quantity' => 10,
                    'total_estimated' => '1001.00', 'delivery_days' => 3, 'delivery_mode' => 'ambos', 'valid_until' => now()->addDays(7),
                    'status' => 'submetida', 'submitted_at' => now(), 'notes' => '[PILOTO TESTE] A confirmar pelo fornecedor.',
                ]);
            }
        });
    }
}
