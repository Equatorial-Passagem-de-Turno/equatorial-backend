<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Shift\Repositories\ShiftRepositoryInterface;
use App\Infrastructure\Repositories\EloquentShiftRepository;
use App\Domain\Occurrence\Repositories\OccurrenceRepositoryInterface;
use App\Infrastructure\Repositories\EloquentOccurrenceRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Domain\Shift\Repositories\ShiftRepositoryInterface::class,
            \App\Infrastructure\Repositories\EloquentShiftRepository::class
        );

        $this->app->bind(
            \App\Domain\Occurrence\Repositories\OccurrenceRepositoryInterface::class,
            \App\Infrastructure\Repositories\EloquentOccurrenceRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
