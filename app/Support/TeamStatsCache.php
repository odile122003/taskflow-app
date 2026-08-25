<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;

/**
 * Statistiques d'équipe (nombre de projets, de tâches par statut, tâches
 * terminées ce mois) : des agrégats qui changent rarement comparé au nombre
 * de fois où le tableau de bord les affiche. Cache::tags() plutôt qu'une
 * simple clé — nécessite un store qui les supporte (Redis, Memcached ;
 * jamais `database` ou `file`, qui lèvent une BadMethodCallException).
 * Invalidation par événement : TaskObserver et ProjectObserver appellent
 * forget() dès qu'une écriture peut changer un de ces nombres, plutôt que
 * de compter sur le TTL seul.
 *
 * `config('cache.stats_store')` plutôt que le store par défaut global :
 * cette classe est la seule chose de l'application qui a vraiment besoin de
 * Redis (pour les tags). Faire de Redis le store par défaut de *toute*
 * l'app (sessions, rate limiter de connexion...) rendait la connexion
 * elle-même indisponible dès que Redis (lancé dans WSL sur cette machine)
 * tombait — trouvé en pratique quand une simple connexion (`POST /login`)
 * a jeté une `StreamInitException`. `CACHE_STATS_STORE=array` en test
 * (phpunit.xml) : les tests n'ont jamais besoin d'un vrai Redis.
 */
final class TeamStatsCache
{
    private const TTL_MINUTES = 15;

    /**
     * @return array{projects_count: int, tasks_count: int, tasks_by_status: array<string, int>, completed_this_month: int}
     */
    public static function remember(Team $team): array
    {
        return Cache::store(config('cache.stats_store'))->tags([self::tag($team->id)])->remember(
            'stats',
            now()->addMinutes(self::TTL_MINUTES),
            fn () => self::compute($team),
        );
    }

    public static function forget(int $teamId): void
    {
        Cache::store(config('cache.stats_store'))->tags([self::tag($teamId)])->flush();
    }

    private static function tag(int $teamId): string
    {
        return "team:{$teamId}:stats";
    }

    /**
     * @return array{projects_count: int, tasks_count: int, tasks_by_status: array<string, int>, completed_this_month: int}
     */
    private static function compute(Team $team): array
    {
        // Group by en base, jamais un ->get() de toutes les tâches suivi d'un
        // groupBy() PHP : seuls les comptes voyagent, pas les lignes.
        $tasksByStatus = $team->tasks()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'projects_count' => $team->projects()->count(),
            'tasks_count' => (int) $tasksByStatus->sum(),
            'tasks_by_status' => collect(TaskStatus::cases())
                ->mapWithKeys(fn (TaskStatus $status) => [
                    $status->value => (int) ($tasksByStatus[$status->value] ?? 0),
                ])
                ->all(),
            'completed_this_month' => $team->tasks()
                ->where('status', TaskStatus::Done)
                ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];
    }
}
