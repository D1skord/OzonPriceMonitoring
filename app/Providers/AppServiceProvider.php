<?php

namespace App\Providers;

use App\Services\Parsing\MarketplaceWishlistParserRegistry;
use App\Services\Parsing\OzonWishlistParserStrategy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MarketplaceWishlistParserRegistry::class, function ($app): MarketplaceWishlistParserRegistry {
            return new MarketplaceWishlistParserRegistry([
                $app->make(OzonWishlistParserStrategy::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
