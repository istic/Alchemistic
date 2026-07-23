<?php

namespace App\Providers;

use App\Listeners\AttachOidcIdToken;
use App\Models\Passport\Client;
use App\Models\SftpUser;
use App\Observers\SftpUserObserver;
use App\Services\Oidc\PendingIdToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::ignoreRoutes();

        $this->app->singleton(PendingIdToken::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        URL::forceScheme('https');
        SftpUser::observe(SftpUserObserver::class);

        Passport::useClientModel(Client::class);

        Passport::tokensCan([
            'openid' => 'Verify your identity',
            'profile' => 'View your name',
            'email' => 'View your email address',
        ]);

        Passport::defaultScopes(['openid']);

        Event::listen(AccessTokenCreated::class, AttachOidcIdToken::class);
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

        Password::defaults(
            fn (): ?Password => app()->isProduction()
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
