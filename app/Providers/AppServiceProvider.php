<?php

namespace App\Providers;

use App\Resolvers\Implementations\AddressResolver;
use App\Resolvers\Implementations\CarReferenceResolver;
use App\Resolvers\Interfaces\AddressResolverInterface;
use App\Resolvers\Interfaces\CarReferenceResolverInterface;
use App\Services\Implementations\AuthService;
use App\Services\Implementations\BrandService;
use App\Services\Implementations\CarService;
use App\Services\Implementations\ChatService;
use App\Services\Implementations\OrsRoutingClient;
use App\Services\Implementations\PersonService;
use App\Services\Implementations\ResendContactEmailService;
use App\Services\Implementations\ResendTripEmailService;
use App\Services\Implementations\TripService;
use App\Services\Implementations\UserPersonalDataPurgeService;
use App\Services\Interfaces\AuthServiceInterface;
use App\Services\Interfaces\BrandServiceInterface;
use App\Services\Interfaces\CarServiceInterface;
use App\Services\Interfaces\ChatServiceInterface;
use App\Services\Interfaces\ContactEmailServiceInterface;
use App\Services\Interfaces\OrsRoutingClientInterface;
use App\Services\Interfaces\PersonServiceInterface;
use App\Services\Interfaces\TripEmailServiceInterface;
use App\Services\Interfaces\TripServiceInterface;
use App\Services\Interfaces\UserPersonalDataPurgeServiceInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

/**
 * Registers application service bindings and bootstrapping hooks.
 */
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

        $this->app->bind(ChatServiceInterface::class, ChatService::class);

        $this->app->bind(ContactEmailServiceInterface::class, ResendContactEmailService::class);

        $this->app->bind(PersonServiceInterface::class, PersonService::class);

        $this->app->bind(TripEmailServiceInterface::class, ResendTripEmailService::class);

        $this->app->bind(TripServiceInterface::class, TripService::class);

        // Resolvers
        $this->app->bind(CarReferenceResolverInterface::class, CarReferenceResolver::class);

        $this->app->bind(AddressResolverInterface::class, AddressResolver::class);

        $this->app->bind(OrsRoutingClientInterface::class, OrsRoutingClient::class);

        $this->app->bind(UserPersonalDataPurgeServiceInterface::class, UserPersonalDataPurgeService::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
