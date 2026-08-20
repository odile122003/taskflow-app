<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">Jetons d'API</h2>
        <p class="mt-1 text-sm text-gray-600">
            Permettent à un client externe (script, application mobile, Postman…) de piloter
            TaskFlow sans mot de passe. Chaque jeton n'a que les permissions cochées ci-dessous.
        </p>
    </header>

    @if (session('plainTextToken'))
        <div class="mt-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-medium">Copiez ce jeton maintenant — il ne sera plus jamais affiché :</p>
            <code class="mt-1 block break-all rounded bg-white px-2 py-1 text-xs">{{ session('plainTextToken') }}</code>
        </div>
    @endif

    <form method="POST" action="{{ route('tokens.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <x-input-label for="token_name" value="Nom du jeton" />
            <x-text-input id="token_name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="Ex : script d'import" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700">Permissions</span>
            <div class="mt-2 space-y-1">
                @foreach (\App\Http\Controllers\ApiTokenController::ABILITIES as $ability)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(collect(old('abilities'))->contains($ability))>
                        {{ $ability }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('abilities')" class="mt-2" />
        </div>

        <x-primary-button>Créer le jeton</x-primary-button>
    </form>

    <div class="mt-6 space-y-2">
        @forelse ($tokens as $token)
            <div class="flex items-center justify-between rounded-md border border-gray-200 px-3 py-2 text-sm">
                <div>
                    <span class="font-medium">{{ $token->name }}</span>
                    <span class="text-gray-500">— {{ implode(', ', $token->abilities) }}</span>
                </div>
                <form method="POST" action="{{ route('tokens.destroy', $token) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">Révoquer</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aucun jeton pour l'instant.</p>
        @endforelse
    </div>
</section>
