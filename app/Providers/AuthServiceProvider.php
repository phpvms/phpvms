<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\Filament\ActivityPolicy;
use Filament\FilamentManager;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        Gate::define('access_admin', function (?User $user): Response {
            return $user?->hasAdminAccess()
                ? Response::allow()
                : Response::deny('You do not have permission to access this page.');
        });

        Gate::define('viewLogViewer', function (?User $user): Response {
            return $user?->can('view_logs')
                ? Response::allow()
                : Response::deny('You do not have permission to access this page.');
        });

        Gate::policy(Activity::class, ActivityPolicy::class);

        Gate::guessPolicyNamesUsing(function (string $modelClass) {
            if (filament() instanceof FilamentManager && filament()->isServing()) {
                // try to resolve policies under Filament
                $targetPolicy = str_replace('Models', 'Policies\\Filament', $modelClass).'Policy';

                // Return the policy if there is no, otherwise fallback on the default
                if (class_exists($targetPolicy)) {
                    return $targetPolicy;
                }
            }
            // follow the same namespace as the model
            $targetPolicy = str_replace('Models', 'Policies', $modelClass).'Policy';

            return class_exists($targetPolicy) ? $targetPolicy : null;
        });
    }
}
