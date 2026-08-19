<?php

namespace App\Support;

use App\Models\Team;

/**
 * Contexte "équipe courante", lié au conteneur en singleton (voir AppServiceProvider).
 * Peuplé à chaque requête authentifiée par le middleware SetCurrentTeam avec
 * l'équipe de l'utilisateur connecté. Vide (donc sans filtrage) pour un invité
 * ou une commande Artisan exécutée hors contexte HTTP.
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
