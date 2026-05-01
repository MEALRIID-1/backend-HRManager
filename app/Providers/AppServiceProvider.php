<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Conge;
use App\Models\Contrat;
use App\Models\User;
use App\Observers\CongeObserver;
use App\Observers\ContratObserver;
use App\Observers\UserObserver;
use App\Policies\CongePolicy;
use App\Policies\ContratPolicy;
use App\Policies\EmployePolicy;
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
        // Enregistrer les observers pour les modèles
        User::observe(UserObserver::class);
        Conge::observe(CongeObserver::class);
        Contrat::observe(ContratObserver::class);

        // Enregistrer les policies
        Gate::policy(Conge::class, CongePolicy::class);
        Gate::policy(Contrat::class, ContratPolicy::class);
        Gate::policy(User::class, EmployePolicy::class);
    }
}
