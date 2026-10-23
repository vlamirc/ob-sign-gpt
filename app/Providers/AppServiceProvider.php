<?php

namespace App\Providers;

use App\Models\Signer;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        foreach (['signing-code' => [5, 600], 'signing-verify' => [10, 60], 'signing-accept' => [10, 60]] as $name => [$attempts, $seconds]) {
            RateLimiter::for($name, function (Request $request) use ($name, $attempts, $seconds) {
                $signer = $request->route('signer');
                $id = $signer instanceof Signer ? $signer->id : (string) $signer;

                return Limit::perSecond($attempts, $seconds)->by($name.'|'.$id.'|'.$request->ip());
            });
        }
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
