<?php

namespace App\Console\Commands;

use Database\Seeders\HostedDemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

#[Signature('junta:seed-demo {--credentials= : Ficheiro JSON privado dos perfis} {--force : Autorizar a demonstração em produção}')]
#[Description('Acrescenta perfis e cenários fictícios no modo de demonstração, preservando dados existentes.')]
class SeedHostedDemo extends Command
{
    public function handle(HostedDemoSeeder $seeder): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Use --force para executar explicitamente o povoamento de demonstração em produção.');

            return self::FAILURE;
        }
        $path = $this->option('credentials');
        if (! is_string($path) || ! File::isFile($path)) {
            $this->error('Indique --credentials com um ficheiro JSON privado existente.');

            return self::FAILURE;
        }
        try {
            $document = json_decode(File::get($path), true, 32, JSON_THROW_ON_ERROR);
            $passwords = [];
            foreach (array_keys(HostedDemoSeeder::PROFILES) as $role) {
                $password = $document['accounts'][$role]['password'] ?? null;
                if (! is_string($password)) {
                    throw new RuntimeException('O ficheiro privado deve conter todos os perfis em accounts, com password.');
                }
                $passwords[$role] = $password;
            }
            $seeder->run($passwords);
        } catch (JsonException) {
            $this->error('O ficheiro de credenciais não contém JSON válido.');

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Demonstração preparada: 9 perfis e 24 campanhas. Contas, palavras-passe e progresso existentes foram preservados.');

        return self::SUCCESS;
    }
}
