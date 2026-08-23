<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksTokenAbility;
use App\Support\CurrentTeam;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    use ChecksTokenAbility;

    /**
     * Vérifié ici, avant tout le reste : un jeton d'API sans l'ability
     * requise doit être refusé même pour le ou la Owner — sinon le bypass
     * juste en dessous rendrait les abilities inutiles pour ce rôle.
     *
     * Piège réel (trouvé en écrivant les tests de policies web, Module 10) :
     * `tokenCan()` ne renvoie PAS toujours `true` pour une session — cette
     * garantie ne vaut que si l'authentification passe par le guard
     * `sanctum` lui-même (mode SPA à cookie, avec un TransientToken). Nos
     * routes web utilisent le guard `web` classique : `currentAccessToken()`
     * y est toujours `null`, donc `tokenCan()` valait toujours `false` —
     * **toute l'interface web était bloquée** depuis son introduction, sans
     * que personne ne s'en aperçoive avant un vrai test de session web.
     * D'où la vérification explicite (voir ChecksTokenAbility) : la
     * restriction ne s'applique que lorsqu'un vrai jeton est en jeu.
     */
    public function before(User $user, string $ability, mixed $arg = null): Response|bool|null
    {
        $required = in_array($ability, ['view', 'viewAny'], true) ? 'projects:read' : 'projects:write';

        if ($this->hasAccessToken($user) && ! $user->tokenCan($required)) {
            return Response::deny("Ce jeton n'a pas la permission « {$required} ».");
        }

        // Le ou la propriétaire d'une équipe peut toujours tout faire sur les
        // projets de cette équipe. Retourner `null` (et non `false`) laisse les
        // autres méthodes décider normalement pour tous les autres cas — y
        // compris quand $arg est la chaîne de classe (viewAny/create) plutôt
        // qu'une instance de Project.
        if ($arg instanceof Project && $user->roleIn($arg->team) === TeamRole::Owner) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        // Toujours vrai : la liste elle-même est déjà filtrée par équipe via
        // le scope global TeamScope, inutile de dupliquer ce filtrage ici.
        return true;
    }

    public function view(User $user, Project $project): Response
    {
        return $user->roleIn($project->team) !== null
            ? Response::allow()
            : Response::deny("Vous n'êtes pas membre de l'équipe propriétaire de ce projet.");
    }

    public function create(User $user): Response
    {
        $team = app(CurrentTeam::class)->get();

        if ($team === null) {
            return Response::deny('Aucune équipe active : impossible de créer un projet.');
        }

        return in_array($user->roleIn($team), [TeamRole::Owner, TeamRole::Admin], true)
            ? Response::allow()
            : Response::deny('Seuls le ou la propriétaire et les administrateurs peuvent créer un projet.');
    }

    public function update(User $user, Project $project): Response
    {
        return $user->roleIn($project->team) === TeamRole::Admin
            ? Response::allow()
            : Response::deny('Seuls le ou la propriétaire et les administrateurs peuvent modifier ce projet.');
    }

    public function delete(User $user, Project $project): Response
    {
        // Sans before(), un Admin ne passerait jamais ici : seul Owner
        // (intercepté par before() ci-dessus) peut supprimer un projet.
        return Response::deny('Seul le ou la propriétaire de l\'équipe peut supprimer ce projet.');
    }
}
