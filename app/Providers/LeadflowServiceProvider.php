<?php

declare(strict_types=1);

namespace App\Providers;

use App\Adapters\AdapterRegistry;
use App\Adapters\ConfigFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class LeadflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigFactory::class);

        $this->app->singleton(AdapterRegistry::class, function (Application $app) {
            return new AdapterRegistry(
                $app->make(ConfigFactory::class),
            );
        });
    }
}
