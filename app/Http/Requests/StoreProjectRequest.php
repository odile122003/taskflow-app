<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Pas de Policy avant le Module 6 (Auth/Gates) : tout le monde est autorisé
     * pour l'instant. `authorize()` existe déjà pour qu'on n'ait qu'à changer
     * cette méthode plus tard, sans toucher aux routes ni au contrôleur.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:projects,slug'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
