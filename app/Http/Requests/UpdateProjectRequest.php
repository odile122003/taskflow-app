<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            // Rule::unique()->ignore() : le slug doit rester unique, sauf par
            // rapport à lui-même — sinon un projet ne pourrait jamais être
            // ré-enregistré sans changer son propre slug.
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('projects', 'slug')->ignore($this->route('project'))],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_archived' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
        ];
    }
}
