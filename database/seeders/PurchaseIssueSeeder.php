<?php

namespace Database\Seeders;

use App\Models\PurchaseIssue;
use Illuminate\Database\Seeder;
use RuntimeException;

class PurchaseIssueSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Os cenários de compra de teste não podem ser criados em produção.');
        }
        PurchaseIssue::factory()->create();
    }
}
