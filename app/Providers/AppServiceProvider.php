<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Area-level access. Item-level access is ProjectPolicy.
        Gate::define('staff', fn (User $user) => $user->isStaff());
        Gate::define('manage-clients', fn (User $user) => $user->canManageClients());
        Gate::define('manage-team', fn (User $user) => $user->isOwner());
    }
}
