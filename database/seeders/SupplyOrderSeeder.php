<?php

namespace Database\Seeders;

use App\Models\SupplyOrder;
use Illuminate\Database\Seeder;
use RuntimeException;

class SupplyOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os cenários de compra de teste não podem ser criados em produção.');
        }
        SupplyOrder::factory()->create();
    }
}
