<?php

namespace App\Providers;

use App\Models\Task;
use App\Observers\TaskObserver;
use App\Services\TaskNumberGenerator;
use App\Support\CurrentTeam;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TaskNumberGenerator::class);
        $this->app->singleton(CurrentTeam::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Task::observe(TaskObserver::class);
    }
}
