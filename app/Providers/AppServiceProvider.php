<?php

namespace App\Providers;

use App\Contracts\DeliveryService;
use App\Services\BiteshipService;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            DeliveryService::class,
            BiteshipService::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // FilamentAsset needs Vite manifest (npm run build).
        // In CI/testing the manifest is empty/dummy, so skip gracefully.
        try {
            FilamentAsset::register([
                Js::make('chart-js-plugins', Vite::asset('resources/js/filament-chart-js-plugins.js'))->module(),
            ]);
        } catch (\Exception) {
            // Manifest not built yet — chart plugin won't be registered.
            // The chart widget degrades gracefully without it.
        }
    }
}
