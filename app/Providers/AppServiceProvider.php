<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('manage-accounts', fn (User $user) => $user->is_active && $user->isKetuaLab());
        Gate::define('view-audit-logs', fn (User $user) => $user->is_active && $user->isKetuaLab());
        Gate::define('manage-master-data', fn (User $user) => $user->is_active && $user->isKetuaLab());
        Gate::define('manage-lab-facilities', fn (User $user, int $labId) => $user->is_active && ($user->isKetuaLab() || ($user->isAsistenLab() && (int) $user->lab_id === $labId)));
        Gate::define('import-students', fn (User $user) => $user->is_active && ($user->isDosen() || $user->isAsistenLab() || $user->isKetuaLab()));
        Gate::define('review-reservations', fn (User $user) => $user->is_active && ($user->isKetuaLab() || $user->isAsistenLab()));
        Gate::define('manage-classes', fn (User $user) => $user->is_active && ($user->isKetuaLab() || $user->isAsistenLab()));
        
        Event::listen(Login::class, function (Login $e) {
            if ($e->user instanceof User) {
                Audit::record('auth.login', $e->user, actor: $e->user);
            }
        });

        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user instanceof User) {
                Audit::record('auth.logout', $e->user, actor: $e->user);
            }
        });

        Event::listen(Failed::class, fn (Failed $e) => Audit::record(
            'auth.login_failed',
            null,
            ['email' => strtolower((string) ($e->credentials['email'] ?? ''))],
            'failed',
        ));

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
