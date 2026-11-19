<?php

namespace Database\Seeders;

use App\Models\PurchaseRefund;
use Illuminate\Database\Seeder;
use RuntimeException;

class PurchaseRefundSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os cenários de compra de teste não podem ser criados em produção.');
        }
        PurchaseRefund::factory()->create();
    }
}
