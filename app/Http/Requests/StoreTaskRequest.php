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

        return [
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high'])],
            // Règle conditionnelle : une échéance devient obligatoire dès que la
            // priorité est "high" (sinon elle reste facultative).
            'due_date' => ['nullable', 'date', 'required_if:priority,high'],
            'assignee_id' => ['nullable', 'exists:users,id', new NotAssignedToArchivedProject($project)],
            // Validation d'un tableau et de ses éléments (items.*).
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ];
    }
}
