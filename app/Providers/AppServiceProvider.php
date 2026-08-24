<?php

namespace App\Providers;

use App\Contracts\AttachmentStorage;
use App\Models\Task;
use App\Observers\TaskObserver;
use App\Services\Attachments\DiskAttachmentStorage;
use App\Services\TaskNumberGenerator;
use App\Support\CurrentTeam;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Un seul endroit à changer pour faire pointer AttachmentController
        // vers une autre implémentation (ex. InMemoryAttachmentStorage dans
        // les tests, via $this->app->bind() dans le test lui-même).
        $this->app->bind(AttachmentStorage::class, DiskAttachmentStorage::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Task::observe(TaskObserver::class);

        // Par jeton plutôt que par utilisateur : deux jetons du même compte
        // (un script d'import, une appli mobile) ne doivent pas se gêner —
        // chacun a son propre compteur. À défaut de jeton (session web,
        // ou requête non authentifiée), on retombe sur l'utilisateur puis l'IP.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            // Aucun mode SPA à cookie configuré (SANCTUM_STATEFUL_DOMAINS) :
            // un utilisateur authentifié sur /api/* l'est toujours par un
            // vrai jeton Bearer, jamais par TransientToken.
            $key = $user !== null ? $user->currentAccessToken()->id : $request->ip();

            return Limit::perMinute(60)->by((string) $key);
        });
    }
}
