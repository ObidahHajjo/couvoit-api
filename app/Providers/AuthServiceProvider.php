<?php

namespace App\Providers;

use App\Models\Car;
use App\Models\Person;
use App\Models\Trip;
use App\Policies\CarPolicy;
use App\Policies\PersonPolicy;
use App\Policies\TripPolicy;
use App\Security\JwtIssuer;
use App\Security\JwtIssuerInterface;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{

    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Trip::class => TripPolicy::class,
        Car::class => CarPolicy::class,
        Person::class => PersonPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            JwtIssuerInterface::class,
            JwtIssuer::class
        );
    }
}
