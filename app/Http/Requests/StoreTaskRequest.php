<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Rules\NotAssignedToArchivedProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return self::rulesFor($project);
    }

    /**
     * Extrait de rules() pour être réutilisable hors contexte HTTP (le
     * formulaire de création en Livewire, Module 13, n'a pas de route
     * dont déduire {project} — le composant le connaît déjà directement).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(Project $project): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high'])],
            // Règle conditionnelle : une échéance devient obligatoire dès que la
            // priorité est "high" (sinon elle reste facultative).
            'due_date' => ['nullable', 'date', 'required_if:priority,high'],
            // exists:users,id seul acceptait n'importe quel utilisateur de la
            // base, même hors de l'équipe du projet (trouvé en testant,
            // Module 10) : scopé à team_user pour cette équipe précise.
            'assignee_id' => [
                'nullable',
                Rule::exists('team_user', 'user_id')->where('team_id', $project->team_id),
                new NotAssignedToArchivedProject($project),
            ],
            // Validation d'un tableau et de ses éléments (items.*).
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ];
    }
}
