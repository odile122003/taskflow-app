<?php

namespace App\Http\Requests;

use App\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
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
            // Version Module 5 : ajoute un utilisateur *existant* à l'équipe.
            // L'invitation d'un e-mail qui n'a pas encore de compte (URL signée,
            // mail envoyé) est une fonctionnalité du Module 6 (Auth), pas de celui-ci.
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::enum(TeamRole::class)],
        ];
    }

    /**
     * Attributs traduits propres à cette requête (en plus de lang/fr/validation.php).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'adresse e-mail',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'Aucun compte ne correspond à cette :attribute.',
        ];
    }
}
