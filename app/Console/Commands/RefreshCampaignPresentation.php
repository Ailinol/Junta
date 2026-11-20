<?php

namespace App\Console\Commands;

use App\Support\ProductIllustrations;
use App\Support\TestPaymentGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('junta:refresh-presentation {--force : Autorizar explicitamente a actualização em produção}')]
#[Description('Remove marcadores visuais e acrescenta ilustrações locais, sem alterar valores ou acessos.')]
class RefreshCampaignPresentation extends Command
{
    public function handle(TestPaymentGateway $gateway): int
    {
        if (config('app.demo_mode') !== true || ! $gateway->available()) {
            $this->error('A actualização exige o modo de demonstração e pagamentos exclusivamente de teste.');

            return self::FAILURE;
        }
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Use --force para actualizar explicitamente a apresentação publicada.');

            return self::FAILURE;
        }
        $changed = DB::transaction(function (): int {
            $changed = 0;
            $tables = [
                'users' => ['name'],
                'campaigns' => ['title', 'description', 'organizer_name', 'location'],
                'suppliers' => ['company_name', 'contact_name', 'description', 'location'],
                'participations' => ['notes'], 'proposals' => ['notes'],
                'organizer_applications' => ['full_name', 'motivation', 'decision_reason'],
            ];
            foreach ($tables as $table => $fields) {
                foreach (DB::table($table)->select(['id', ...$fields])->lockForUpdate()->lazyById(100) as $row) {
                    $changes = [];
                    foreach ($fields as $field) {
                        if (is_string($row->{$field})) {
                            $clean = $this->withoutMarker($row->{$field});
                            if ($clean !== $row->{$field}) {
                                $changes[$field] = $clean;
                            }
                        }
                    }
                    if ($changes !== []) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                        $changed++;
                    }
                }
            }
            foreach (DB::table('campaigns')->lockForUpdate()->lazyById(100) as $campaign) {
                $image = ProductIllustrations::forTitle($campaign->title);
                if (empty($campaign->image_url) && $image !== null) {
                    DB::table('campaigns')->where('id', $campaign->id)->update(['image_url' => $image]);
                    $changed++;
                }
            }
            foreach (['campaigns' => 'purchase_config', 'purchase_orders' => 'conditions', 'supply_orders' => 'conditions', 'notifications' => 'data'] as $table => $column) {
                foreach (DB::table($table)->whereNotNull($column)->lockForUpdate()->lazyById(100) as $row) {
                    $data = json_decode($row->{$column}, true, 512, JSON_THROW_ON_ERROR);
                    $original = $data;
                    if (! is_array($data)) {
                        continue;
                    }
                    array_walk_recursive($data, function (mixed &$value): void {
                        if (is_string($value)) {
                            $value = $this->withoutMarker($value);
                        }
                    });
                    if ($data !== $original) {
                        DB::table($table)->where('id', $row->id)->update([$column => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
                        $changed++;
                    }
                }
            }

            return $changed;
        }, 5);
        $this->info('Apresentação actualizada. Alterações de apresentação: '.$changed.'. Valores, acessos e auditoria preservados.');

        return self::SUCCESS;
    }

    private function withoutMarker(string $value): string
    {
        if (preg_match('/\[demo\]/iu', $value) !== 1) {
            return $value;
        }

        return trim((string) preg_replace('/\[demo\][ \t]*/iu', '', $value));
    }
}
