<?php

namespace App\Providers;

use App\Services\Match\MotorDeMatch;
use App\Services\Pncp\PncpClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PncpClient::class, fn (): PncpClient => new PncpClient(
            baseUrl: (string) config('pncp.base_url'),
            timeout: (int) config('pncp.timeout'),
            retryTimes: (int) config('pncp.retry.times'),
            retrySleep: (int) config('pncp.retry.sleep'),
        ));

        $this->app->bind(MotorDeMatch::class, fn (): MotorDeMatch => MotorDeMatch::aPartirDaConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
