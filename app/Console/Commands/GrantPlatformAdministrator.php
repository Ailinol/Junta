<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PlatformAudit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('junta:grant-admin {email : Email exacto da conta existente}')]
#[Description('Atribui acesso administrativo explicitamente a uma conta existente.')]
class GrantPlatformAdministrator extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('A conta indicada não existe. Nenhuma conta foi criada ou alterada.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user): void {
            $user->is_admin = true;
            $user->save();
            PlatformAudit::record($user, 'administrator.granted.via-console', $user);
        });
        $this->info('Acesso administrativo atribuído a '.$user->email.'. A palavra-passe não foi alterada.');

        return self::SUCCESS;
    }
}
