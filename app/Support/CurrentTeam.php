<?php

namespace App\Support;

use App\Models\Team;

/**
 * Contexte "équipe courante", lié au conteneur en singleton (voir AppServiceProvider).
 * Vide par défaut (aucun filtrage) — c'est l'authentification (Module 6) qui le
 * peuplera avec l'équipe active de l'utilisateur connecté, sur chaque requête.
 */
class CurrentTeam
{
    private ?Team $team = null;

    public function set(?Team $team): void
    {
        $this->team = $team;
    }

    public function get(): ?Team
    {
        return $this->team;
    }

    public function id(): ?int
    {
        return $this->team?->id;
    }
}
