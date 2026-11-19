<?php

namespace Database\Seeders;

use App\Support\TestPaymentGateway;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment(['local', 'testing']) && DB::connection()->getDriverName() === 'sqlite' && app(TestPaymentGateway::class)->available()) {
            $this->call(CompleteDemoSeeder::class);

            return;
        }
        $this->command?->info('Nenhum dado de demonstração foi inserido. Use uma base SQLite isolada com pagamentos de teste activados.');
    }
}
