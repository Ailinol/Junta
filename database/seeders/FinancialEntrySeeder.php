<?php

namespace Database\Seeders;

use App\Models\FinancialEntry;
use Illuminate\Database\Seeder;
use RuntimeException;

class FinancialEntrySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os cenários de compra de teste não podem ser criados em produção.');
        }
        FinancialEntry::factory()->create();
    }
}
