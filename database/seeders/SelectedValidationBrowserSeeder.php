<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SelectedValidationBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || basename((string) config('database.connections.sqlite.database')) !== 'junta-platform-qa.sqlite') {
            throw new RuntimeException('Use apenas a base SQLite temporária de testes.');
        }
        $this->call(PlatformBrowserSeeder::class);
        $organizer = User::where('email', 'organizer@junta.example.test')->firstOrFail();
        Campaign::factory()->create(['organizer_id' => $organizer->id, 'organizer_name' => $organizer->name, 'title' => '[TESTE] Validações dos formulários', 'deadline' => now()->addDays(20)]);
    }
}
