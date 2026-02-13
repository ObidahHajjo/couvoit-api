<?php

namespace App\Providers;

use App\Clients\Implementations\SupabaseAuthClient;
use App\Clients\Interfaces\SupabaseAuthClientInterface;
use App\Resolvers\Implementations\AddressResolver;
use App\Resolvers\Implementations\CarReferenceResolver;
use App\Resolvers\Interfaces\AddressResolverInterface;
use App\Resolvers\Interfaces\CarReferenceResolverInterface;
use App\Services\Implementations\AuthService;
use App\Services\Implementations\BrandService;
use App\Services\Implementations\CarService;
use App\Services\Implementations\PersonService;
use App\Services\Implementations\TripService;
use App\Services\Interfaces\AuthServiceInterface;
use App\Services\Interfaces\BrandServiceInterface;
use App\Services\Interfaces\CarServiceInterface;
use App\Services\Interfaces\PersonServiceInterface;
use App\Services\Interfaces\TripServiceInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthServiceInterface::class, AuthService::class);

        $this->app->bind(BrandServiceInterface::class, BrandService::class);

        $this->app->bind(CarServiceInterface::class, CarService::class);

        $this->app->bind(PersonServiceInterface::class, PersonService::class);

        $this->app->bind(TripServiceInterface::class, TripService::class);

        // Resolvers
        $this->app->bind(CarReferenceResolverInterface::class, CarReferenceResolver::class);

        $this->app->bind(AddressResolverInterface::class, AddressResolver::class);

        $this->app->bind(SupabaseAuthClientInterface::class, SupabaseAuthClient::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
