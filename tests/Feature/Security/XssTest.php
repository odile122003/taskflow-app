<?php

use App\Models\Project;
use Illuminate\Support\Facades\Blade;

it('escapes a malicious project name instead of executing it', function () {
    $project = Project::factory()->create(['name' => '<script>window.xssFired = true</script>Titre piégé']);
    $user = memberOf($project->team);

    $response = $this->actingAs($user)->get('/projects');

    // {{ }} (Blade) échappe : le tag apparaît en texte littéral, jamais exécuté.
    $response->assertDontSee('<script>window.xssFired = true</script>', false)
        ->assertSee('&lt;script&gt;window.xssFired = true&lt;/script&gt;', false)
        ->assertSee('Titre piégé');
});

it('would execute the same payload if a view used {!! !!} on untrusted input', function () {
    // Démonstration volontaire de ce qui casse la protection : jamais fait
    // en dehors de ce test, jamais sur du contenu utilisateur en pratique
    // (voir l'interdit correspondant dans CLAUDE.md).
    $payload = '<script>window.xssFired = true</script>';

    $unsafeBlade = Blade::render('{!! $value !!}', ['value' => $payload]);

    expect($unsafeBlade)->toBe($payload);
});
