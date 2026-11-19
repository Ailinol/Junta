<?php

namespace App\Providers;

use App\Models\Campaign;
use App\Models\User;
use App\Support\PaymentGateway;
use App\Support\TestPaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, TestPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::define('administer-platform', fn (User $user): bool => $user->is_admin);
        Gate::define('organize-campaigns', fn (User $user): bool => $user->is_admin || $user->is_organizer);
        Gate::define('supply-campaigns', fn (User $user): bool => $user->supplier()->exists());
        Gate::define('manage-campaign', fn (User $user, Campaign $campaign): bool => $user->is_admin || ($user->is_organizer && $campaign->organizer_id === $user->id));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): ?Password {
            if (! app()->isProduction()) {
                return null;
            }

            $password = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            return config('app.demo_mode') === true ? $password : $password->uncompromised();
        });
    }
}
