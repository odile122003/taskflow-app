<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * L'autorisation de rôle (qui a le droit de déplacer une tâche) reste
     * dans TaskPolicy::update — déplacer est un cas particulier de modifier,
     * pas une règle métier différente qui justifierait sa propre ability.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ];
    }
}
