<?php

declare(strict_types=1);

namespace App\Providers;

use App\Adapters\AdapterRegistry;
use App\Adapters\ConfigFactory;
use App\Http\BankHttpClient;
use App\Http\Events\BankRequestFailed;
use App\Http\Listeners\LogBankRequestFailures;
use App\Http\Sleeper;
use App\Http\SystemSleeper;
use App\Scoring\ScoringConfigFactory;
use App\Services\KeyDetector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LeadflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigFactory::class);
        $this->app->singleton(KeyDetector::class);
        $this->app->singleton(ScoringConfigFactory::class);

        $this->app->singleton(Sleeper::class, SystemSleeper::class);

        $this->app->singleton(BankHttpClient::class, function (Application $app) {
            return new BankHttpClient(
                events: $app->make(\Illuminate\Contracts\Events\Dispatcher::class),
                sleeper: $app->make(Sleeper::class),
            );
        });

        $this->app->singleton(AdapterRegistry::class, function (Application $app) {
            return new AdapterRegistry(
                $app->make(ConfigFactory::class),
            );
        });
    }

    public function boot(): void
    {
        Event::listen(BankRequestFailed::class, LogBankRequestFailures::class);
    }
}
