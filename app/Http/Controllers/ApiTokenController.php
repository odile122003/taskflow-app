<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    /**
     * @var list<string>
     */
    public const ABILITIES = [
        'projects:read',
        'projects:write',
        'tasks:read',
        'tasks:write',
    ];

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken(
            $request->validated('name'),
            $request->validated('abilities'),
        );

        // Le jeton en clair n'est affiché qu'une seule fois : Sanctum ne
        // stocke que son hash, impossible de le retrouver après ce redirect.
        return back()
            ->with('success', 'Jeton créé — copiez-le maintenant, il ne sera plus affiché.')
            ->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, PersonalAccessToken $token): RedirectResponse
    {
        abort_unless($token->tokenable_id === $request->user()->id, 403);

        $token->delete();

        return back()->with('success', 'Jeton révoqué.');
    }
}
