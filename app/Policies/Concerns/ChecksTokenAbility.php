<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksTokenAbility
{
    /**
     * `$user->currentAccessToken()` est documenté par Sanctum comme
     * `@var TToken` (sans `|null`), alors qu'il vaut bel et bien `null` hors
     * contexte API — prouvé par un vrai test de session web qui échouait
     * (Module 10). Larastan croit ce docblock sur parole et déclare tout
     * contrôle de nullité direct "toujours vrai" (mort). Passer par un
     * paramètre `mixed` ici n'est pas un contournement : `mixed` est le
     * type honnête de cette valeur, Sanctum se trompe en affirmant le
     * contraire.
     */
    private function hasAccessToken(User $user): bool
    {
        return $this->isNotNull($user->currentAccessToken());
    }

    private function isNotNull(mixed $value): bool
    {
        return $value !== null;
    }
}
