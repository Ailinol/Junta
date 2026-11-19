<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('two processes racing for reserved stock initiate only one charge', function () {
    $this->freezeTime();
    $database = tempnam(sys_get_temp_dir(), 'junta-race-');
    $original = config('database.connections.sqlite');
    try {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 15000]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true, '--no-interaction' => true]);
        $campaign = Campaign::factory()->create();
        $config = PurchaseOrder::factory()->make()->conditions;
        $config['available_quantity'] = 3;
        $campaign->forceFill(['purchase_phase' => 'payment', 'payment_deadline' => now()->addDay(), 'purchase_config' => $config])->save();
        $ids = [];
        foreach ([1, 2] as $index) {
            $user = User::factory()->create();
            $participation = Participation::factory()->create(['user_id' => $user->id, 'campaign_id' => $campaign->id, 'quantity' => 2]);
            $order = PurchaseOrder::factory()->create(['participation_id' => $participation->id, 'campaign_id' => $campaign->id, 'user_id' => $user->id, 'quantity' => 2, 'merchandise_cents' => 20000, 'total_cents' => 20000, 'conditions' => $config]);
            $ids[] = [$user->id, $order->id];
        }
        $workers = [];
        foreach ($ids as [$userId, $orderId]) {
            $worker = <<<'PHP'
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(["database.default"=>"sqlite", "database.connections.sqlite.database"=>$argv[1], "database.connections.sqlite.url"=>null, "database.connections.sqlite.busy_timeout"=>15000, "purchases.provider"=>"test", "purchases.test_enabled"=>true]);
Illuminate\Support\Facades\DB::purge("sqlite");
try {
    $app->make(App\Support\CollectivePurchase::class)->pay(App\Models\User::findOrFail((int)$argv[2]), App\Models\PurchaseOrder::findOrFail((int)$argv[3]));
    echo "charged";
} catch (Illuminate\Validation\ValidationException $exception) {
    echo "unavailable";
}
PHP;
            $process = proc_open([PHP_BINARY, '-r', $worker, $database, (string) $userId, (string) $orderId], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['APP_ENV' => 'testing']));
            expect(is_resource($process))->toBeTrue();
            $workers[] = [$process, $pipes];
        }
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $results[] = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            expect(proc_close($process))->toBe(0, $error);
        }
        sort($results);
        expect($results)->toBe(['charged', 'unavailable']);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('test_gateway_transactions', 1);
        expect($campaign->fresh()->held_quantity)->toBe(2);
    } finally {
        DB::purge('sqlite');
        config(['database.connections.sqlite' => $original]);
        if (is_string($database) && file_exists($database)) {
            unlink($database);
        }
    }
});
