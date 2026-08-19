<?php

namespace App\Rules;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Complète (ne remplace pas) le middleware EnsureProjectIsNotArchived : le
 * middleware bloque toute écriture sur les tâches d'un projet archivé (403,
 * avant même la validation) ; cette règle protège spécifiquement le champ
 * assignee_id, avec un message rattaché au formulaire — utile si ce champ est
 * un jour validé dans un contexte où le middleware de route n'est pas présent
 * (ex. API, import en masse).
 */
class NotAssignedToArchivedProject implements ValidationRule
{
    public function __construct(private readonly Project $project) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->project->is_archived) {
            $fail('Impossible d\'assigner une tâche sur un projet archivé.');
        }
    }
}
