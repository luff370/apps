<?php

namespace App\Providers;

use App\Models\AppAdvertisement;
use App\Models\AppPayment;
use App\Observers\AppAdvertisementObserver;
use App\Observers\AppPaymentObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        AppPayment::observe(AppPaymentObserver::class);
        AppAdvertisement::observe(AppAdvertisementObserver::class);
        // TrafficViolationContent::observe(TrafficViolationContentObserver::class);
        // MemberOrder::observe(MemberOrderObserver::class);
        // SubscriptionOrder::observe(SubscriptionOrderObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
