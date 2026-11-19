<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\OrganizerApplication;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || basename((string) config('database.connections.sqlite.database')) !== 'junta-platform-qa.sqlite') {
            throw new \RuntimeException('Este seeder só pode ser usado na base SQLite temporária de teste.');
        }
        $password = Hash::make('Junta-QA-Only!2026');
        $admin = User::factory()->create(['name' => 'Administrador de teste', 'email' => 'admin@junta.example.test', 'password' => $password, 'is_admin' => true]);
        $consumer = User::factory()->create(['name' => 'Consumidor de teste', 'email' => 'consumer@junta.example.test', 'password' => $password, 'contact_preferences' => ['phone' => '+258 84 123 4567', 'city' => 'Maputo']]);
        $owner = User::factory()->create(['name' => 'Organizador de teste', 'email' => 'organizer@junta.example.test', 'password' => $password, 'is_organizer' => true]);
        $supplierUser = User::factory()->create(['name' => 'Fornecedor de teste', 'email' => 'supplier@junta.example.test', 'password' => $password]);
        $supplier = Supplier::factory()->create(['user_id' => $supplierUser->id, 'company_name' => 'Fornecedor de teste verificado', 'verified' => true]);
        Supplier::factory()->create(['company_name' => 'Fornecedor pendente de teste', 'verified' => false]);
        OrganizerApplication::factory()->create(['full_name' => 'Candidato pendente de teste']);
        $states = ['concluida', 'cancelada', 'confirmada', 'proposta_selecionada', 'em_avaliacao', 'ativa'];
        foreach ($states as $index => $status) {
            $date = now()->subMonths(5 - $index)->startOfMonth()->addDays(8);
            $campaign = Campaign::factory()->create(['organizer_id' => $owner->id, 'organizer_name' => $owner->name, 'title' => 'Campanha de teste '.($index + 1), 'status' => $status, 'created_at' => $date, 'updated_at' => now(), 'deadline' => now()->addDays(30)]);
            if ($status !== 'cancelada') {
                Proposal::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => $supplier->id, 'status' => in_array($status, ['concluida', 'confirmada', 'proposta_selecionada'], true) ? 'aceite' : 'submetida', 'submitted_at' => $date->addDays(1)]);
            }
            if ($status === 'em_avaliacao') {
                Proposal::factory()->create(['campaign_id' => $campaign->id, 'supplier_id' => $supplier->id, 'unit_price' => 900, 'submitted_at' => $date->addDays(2)]);
            }
            if ($status === 'ativa') {
                Participation::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $consumer->id, 'quantity' => 3]);
            }
        }
    }
}
