<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Rules\NotAssignedToArchivedProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
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

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            // Rule::enum() : la valeur soumise doit correspondre à un cas de
            // l'enum TaskStatus (Module 4) — même liste de valeurs, une seule
            // source de vérité entre le cast du modèle et la validation.
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high'])],
            'due_date' => ['nullable', 'date', 'required_if:priority,high'],
            // Même correctif que StoreTaskRequest (Module 10) : scopé à
            // l'équipe du projet, pas n'importe quel utilisateur de la base.
            'assignee_id' => [
                'nullable',
                Rule::exists('team_user', 'user_id')->where('team_id', $project->team_id),
                new NotAssignedToArchivedProject($project),
            ],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ];
    }
}
