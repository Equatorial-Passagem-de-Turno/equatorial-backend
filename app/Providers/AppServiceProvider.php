<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Turno\Repositories\TurnoRepositoryInterface;
use App\Infrastructure\Repositories\EloquentTurnoRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TurnoRepositoryInterface::class,
            EloquentTurnoRepository::class
        );

        $this->app->bind(
    \App\Domain\Ocorrencia\Repositories\OcorrenciaRepositoryInterface::class,
    \App\Infrastructure\Repositories\EloquentOcorrenciaRepository::class
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
