<?php

namespace App\Providers;

use App\Models\Contrat;
use App\Models\FichePaie;
use App\Models\User;
use App\Observers\ContratObserver;
use App\Observers\FichePaieObserver;
use App\Observers\UserObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \Illuminate\Auth\Events\Login::class => [
            \App\Listeners\LogSuccessfulLogin::class,
        ],
        \Illuminate\Auth\Events\Logout::class => [
            \App\Listeners\LogSuccessfulLogout::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Enregistrer les observers pour l'audit trail
        User::observe(UserObserver::class);
        Contrat::observe(ContratObserver::class);
        FichePaie::observe(FichePaieObserver::class);
        \App\Models\Conge::observe(\App\Observers\CongeObserver::class);
    }
}
