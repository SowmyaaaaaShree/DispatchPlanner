<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\GeocodingService::class);
        $this->app->singleton(\App\Services\HolidayService::class);
        $this->app->singleton(\App\Services\WeatherService::class);
        $this->app->singleton(\App\Services\CurrencyService::class);
        $this->app->singleton(\App\Services\CsvIngestionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
