<?php

namespace App\Http\Requests;

use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    /**
     * Seul un Owner ou un Admin de CETTE équipe (celle de l'URL, pas une
     * équipe quelconque de l'utilisateur) peut y inviter quelqu'un.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && in_array($this->user()->roleIn($team), [TeamRole::Owner, TeamRole::Admin], true);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Pas de `exists:users,email` : une adresse sans compte est un cas
            // valide (le Module 6 lui envoie une invitation par URL signée
            // plutôt que de refuser la requête).
            'email' => ['required', 'email'],
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
}
