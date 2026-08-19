<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteMemberRequest;
use App\Models\Team;
use App\Models\User;

class TeamMemberController extends Controller
{
    /**
     * Ajoute un utilisateur existant à l'équipe. L'invitation d'une adresse
     * e-mail qui n'a pas encore de compte (mail envoyé, URL signée à durée
     * limitée) est une fonctionnalité du Module 6 (Auth), pas de celui-ci.
     */
    public function store(InviteMemberRequest $request, Team $team)
    {
        $user = User::where('email', $request->validated('email'))->firstOrFail();

        $team->users()->syncWithoutDetaching([
            $user->id => ['role' => $request->validated('role')],
        ]);

        return response()->json(['message' => 'Membre ajouté.'], 201);
    }
}
