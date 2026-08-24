# CONCEPTS.md — Référence des concepts Laravel expliqués sur TaskFlow

> Document de lecture, pas un journal : chaque section explique un concept rencontré
> pendant le développement, regroupé par module. Généré et tenu à jour par l'assistant IA
> au fil des sessions.

---

## Module 0 — Comment Laravel fonctionne réellement

### Le cycle de vie d'une requête HTTP

Toute requête HTTP passe par la même séquence de fichiers, dans cet ordre :

1. **`public/index.php`** — point d'entrée unique de l'application (tout le trafic web
   passe par ce seul fichier, grâce à la réécriture d'URL du serveur).
   - Ligne 9-11 : vérifie d'abord si l'application est en mode maintenance, **avant**
     même de charger l'autoloader Composer (ligne 14). C'est volontaire : si tout le
     framework devait être chargé rien que pour afficher la page « site en maintenance »,
     ce serait plus lent et plus fragile (un bug dans une dépendance pourrait empêcher
     d'afficher la page de maintenance elle-même).
   - Ligne 18 : charge et exécute `bootstrap/app.php`, qui **retourne** l'objet
     `$app` (une instance de `Illuminate\Foundation\Application`).
   - Ligne 20 : `$app->handleRequest(Request::capture())` — c'est ici que tout démarre
     réellement. `Request::capture()` transforme les superglobales PHP (`$_GET`, `$_POST`,
     `$_SERVER`...) en un objet `Illuminate\Http\Request` orienté objet.

2. **`bootstrap/app.php`** — construit et configure l'application via une syntaxe fluide
   (*method chaining*) :
   ```php
   Application::configure(basePath: dirname(__DIR__))
       ->withRouting(web: ..., commands: ..., health: '/up')
       ->withMiddleware(function (Middleware $middleware) { ... })
       ->withExceptions(function (Exceptions $exceptions) { ... })
       ->create();
   ```
   Chaque `->withXxx()` accumule de la configuration sur un *builder*, et `->create()`
   construit réellement l'objet `Application` et le retourne. `Application` **hérite** de
   `Illuminate\Container\Container` — c'est donc, en un sens, déjà le conteneur de
   services (voir plus bas).

3. **Résolution de la route** — à l'intérieur de `handleRequest()`, Laravel utilise le
   `Router` pour comparer l'URL et la méthode HTTP de la requête aux routes définies dans
   `routes/web.php` (ou `routes/api.php`). Une fois la route trouvée, la requête traverse
   la pile de **middlewares** (globaux puis ceux de la route), puis Laravel appelle la
   méthode du contrôleur (ou la closure) associée à la route, en lui injectant ses
   dépendances via le conteneur de services.

4. **La réponse** — ce que retourne le contrôleur (une vue, un tableau JSON, une
   redirection...) est transformé en objet `Illuminate\Http\Response`, qui repasse par la
   pile de middlewares (dans l'autre sens, pour ceux qui agissent après la réponse), puis
   est envoyé au navigateur.

### Piège : il n'y a plus de `app/Http/Kernel.php`

Beaucoup de documentation/tutoriels antérieurs à Laravel 11 mentionnent
`app/Http/Kernel.php` pour déclarer les middlewares globaux et les groupes `web`/`api`.
**Ce fichier n'existe plus** dans la structure par défaut depuis Laravel 11 (confirmé :
absent de `taskflow/app/Http/`). Cette responsabilité a été déplacée dans
`bootstrap/app.php`, via la closure `->withMiddleware(function (Middleware $middleware) {
...})`. Si un tutoriel plus ancien parle du Kernel HTTP, c'est un signe qu'il faut
transposer vers cette nouvelle syntaxe.

### Le conteneur de services (Service Container)

`Application` (vu plus haut) hérite de `Illuminate\Container\Container`. C'est un
**annuaire d'objets** : au lieu de faire `new MaClasse()` partout dans le code, on demande
au conteneur de fournir l'instance, et le conteneur sait comment la construire (y compris
ses propres dépendances, récursivement). Deux méthodes principales pour enregistrer une
classe dans le conteneur :

- `$this->app->bind(Interface::class, Implementation::class)` — **nouvelle instance à
  chaque résolution**.
- `$this->app->singleton(Interface::class, Implementation::class)` — **une seule instance,
  réutilisée** pour toute la durée de la requête (ou du process CLI).

Exemple concret sur TaskFlow (`app/Providers/AppServiceProvider.php`) :
```php
public function register(): void
{
    $this->app->singleton(TaskNumberGenerator::class);
}
```
Ici on ne précise pas de deuxième argument : quand la clé et l'implémentation sont la même
classe concrète (pas une interface), Laravel sait l'instancier tout seul par
**réflexion** — il lit le constructeur et résout chaque paramètre typé automatiquement.

### L'injection de dépendances (DI)

Une fois la classe liée dans le conteneur, il suffit de la **typer** en paramètre d'un
constructeur, d'une méthode de contrôleur, etc. — Laravel la résout automatiquement.
Exemple (`app/Http/Controllers/DemoController.php`) :
```php
public function taskNumber(TaskNumberGenerator $generator)
{
    // $generator est déjà résolu par le conteneur, prêt à l'emploi
}
```
Aucun `new TaskNumberGenerator()` n'apparaît nulle part dans le code applicatif — c'est
tout l'intérêt : le contrôleur ne sait pas *comment* construire ses dépendances, juste
*qu'il en a besoin*.

### Les service providers : `register()` vs `boot()`

Un provider a deux méthodes de cycle de vie :
- `register()` — **uniquement** lier des choses dans le conteneur (`bind`/`singleton`).
  Ne jamais y résoudre une dépendance ou appeler un service : l'ordre d'exécution des
  providers n'est pas garanti, un autre provider n'a peut-être pas encore été chargé.
- `boot()` — exécuté **après que tous les `register()` de tous les providers ont tourné**.
  C'est là qu'on peut utiliser en toute sécurité un service enregistré par un autre
  provider (routes, vues, event listeners, etc.).

### Les façades : ce qu'elles font *vraiment*

`TaskNumber::next()` (`app/Facades/TaskNumber.php`) **n'est pas** un appel de méthode
statique classique. `Facade` intercepte l'appel via `__callStatic()`, va chercher dans le
conteneur l'instance désignée par `getFacadeAccessor()` (ici `TaskNumberGenerator::class`),
puis appelle `next()` sur **cette instance résolue par le conteneur** — la même que celle
injectée ailleurs si elle est enregistrée en `singleton`.

**Preuve concrète faite sur TaskFlow** (route `/demo/task-number`, retirée après
validation du module) : le contrôleur reçoit `$generator` par injection **et** appelle
`TaskNumber::next()` via la façade. Résultat observé :
```json
{"via_injection_1":"TASK-0001","via_injection_2":"TASK-0002","via_facade_1":"TASK-0003","via_facade_2":"TASK-0004"}
```
Le compteur interne du service continue sa numérotation entre les appels par injection et
les appels par façade → **c'est rigoureusement la même instance** dans les deux cas. Si le
binding avait été un `bind()` simple (au lieu de `singleton()`), chaque résolution aurait
créé une nouvelle instance et le compteur serait reparti de zéro à chaque appel.

### `config/`, `.env` et `config:cache`

- `config/*.php` — chaque fichier retourne un tableau PHP de configuration (`config/app.php`,
  `config/database.php`, etc.). C'est **la seule couche officielle** de configuration :
  le reste de l'application doit lire `config('app.name')`, jamais directement l'environnement.
- `.env` — variables spécifiques à la machine/l'environnement (secrets, URL de base de
  données, clés API). Chargé au tout début du cycle de vie, **avant** que les fichiers de
  `config/` soient évalués. Les fichiers de `config/` font le pont via `env('APP_NAME', 'Laravel')`.
- `php artisan config:cache` — fusionne tous les fichiers de `config/` (avec les valeurs
  `.env` déjà résolues) en un seul fichier compilé dans `bootstrap/cache/config.php`, pour
  éviter de reparser tous les fichiers PHP à chaque requête en production.

**Piège directement lié** (déjà documenté dans `CLAUDE.md` §4) : une fois `config:cache`
exécuté, tout appel à `env()` **en dehors** d'un fichier `config/*.php` renvoie `null` —
parce que ce cache ne contient plus le fichier `.env` du tout, seulement les valeurs déjà
résolues au moment du `config:cache`. D'où la règle : toujours `config('services.stripe.key')`,
jamais `env('STRIPE_KEY')` dans le code applicatif.

*(Structure des dossiers et conventions de nommage : voir `CLAUDE.md` §6-7, déjà couvert
en détail là-bas, pas dupliqué ici.)*

---

## Module 1 — Routes, contrôleurs, requêtes, réponses

> Note de contexte : ce module a nécessité une anticipation minimale du Module 3
> (migrations) et du Module 4 (Eloquent) — le binding par slug sur `{project}` exige une
> vraie table et un vrai modèle. On s'est limité au strict nécessaire : migrations sans
> fioritures, modèles sans casts avancés/enums/scopes. L'Eloquent complet (accessors,
> observers, collections...) reste entièrement à voir au Module 4.

### Contrôleurs ressources (`--resource`) et convention REST

`php artisan make:controller ProjectController --resource --model=Project` génère les 7
méthodes conventionnelles (`index, create, store, show, edit, update, destroy`).
`Route::resource('projects', ProjectController::class)` les relie toutes en une ligne, avec
des noms de route cohérents (`projects.index`, `projects.show`, ...) — vérifiable avec
`php artisan route:list`. Pour une ressource imbriquée (`projects.tasks`), Laravel préfixe
automatiquement l'URI (`projects/{project}/tasks/{task}`) et les noms de route
(`projects.tasks.store`...).

Comme les vues Blade n'existent pas encore (Module 2), `create()` et `edit()` (qui servent
uniquement à afficher un formulaire HTML) renvoient volontairement `abort(501, ...)` pour
l'instant — la convention REST est respectée dans les routes, l'implémentation suivra.

### Route model binding : implicite, via modèle, avec scope

Trois façons de lier un segment d'URL à un modèle, du plus simple au plus explicite :

1. **Implicite par défaut** — `{project}` dans une route + `Project $project` dans la
   méthode → Laravel résout par la clé primaire (`id`), sans rien configurer.
2. **Implicite via le modèle** — en surchargeant `getRouteKeyName()` sur `Project`
   (fait ici : retourne `'slug'`), **toutes** les routes utilisant `{project}` résolvent
   par la colonne `slug` au lieu de `id`, partout, sans le répéter dans chaque route.
   C'est ce qui a été choisi pour TaskFlow (`app/Models/Project.php`).
   *(Alternative existante mais non utilisée ici : la syntaxe `{project:slug}` directement
   dans une route définie à la main, pour surcharger la clé au cas par cas plutôt que
   globalement — utile seulement si certaines routes doivent binder par `id` et d'autres
   par `slug` pour le même modèle.)*
3. **Avec scope (`scopeBindings` / `->scoped()`)** — pour une ressource imbriquée
   `projects.tasks`, `Route::resource('projects.tasks', TaskController::class)->scoped()`
   contraint automatiquement la résolution de `{task}` à la relation `tasks()` du
   `{project}` déjà résolu. Concrètement : `/projects/projet-a/tasks/{id}` où `{id}`
   appartient au projet B renvoie **404**, pas 200 avec la mauvaise tâche.

**Preuve concrète faite sur TaskFlow** : la vérification d'appartenance a d'abord été
écrite à la main dans `TaskController` (`if ($task->project_id !== $project->id) abort(404)`),
testée (404 confirmé sur un accès croisé), **puis retirée** au profit de `->scoped()` sur
la route — testé à nouveau, comportement strictement identique. Ça illustre bien la
différence entre « comprendre ce que fait le framework » et « laisser le framework le
faire » : on n'adopte le raccourci qu'après avoir vérifié qu'il fait exactement ce qu'on
avait écrit à la main.

### L'objet `Request` : `validate()`, pas `all()`

Chaque `store()`/`update()` de TaskFlow utilise `$request->validate([...])`, qui retourne
**uniquement** les champs validés (jamais `$request->all()` — voir l'interdit `CLAUDE.md`
§14). Cette validation inline dans le contrôleur est une étape transitoire : le Module 5
la fera migrer vers des classes `FormRequest` dédiées (`StoreTaskRequest`, etc.), plus
adaptées quand les règles se complexifient (règles conditionnelles, autorisation...).

### Middlewares : création, alias, application ciblée

Un middleware généré (`php artisan make:middleware Xxx`) doit être **enregistré** pour être
utilisable par son nom. Depuis Laravel 11+, ça se passe dans `bootstrap/app.php` :
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['project.active' => EnsureProjectIsNotArchived::class]);
    $middleware->append(LogRequestDuration::class); // appliqué à CHAQUE requête
})
```
- `alias()` déclare un nom court utilisable ensuite sur une route ou un contrôleur — le
  middleware n'agit sur rien tant qu'il n'est pas explicitement attaché quelque part.
- `append()` l'ajoute directement à la pile globale : il s'exécute sur toutes les requêtes,
  sans avoir besoin d'un alias (cas de `LogRequestDuration`, exercice 2 — chaque requête,
  sans exception, doit être journalisée).

**Application ciblée sur certaines actions seulement** — le contrôleur `TaskController`
implémente `HasMiddleware` (la convention Laravel 11+, remplace l'ancien
`$this->middleware()` dans le constructeur, qui n'existe plus par défaut — le
`Controller` de base est vide, voir `app/Http/Controllers/Controller.php`) :
```php
public static function middleware(): array
{
    return [
        new Middleware('project.active', only: ['store', 'update', 'destroy']),
    ];
}
```
`EnsureProjectIsNotArchived` ne bloque donc que la création/modification/suppression de
tâches sur un projet archivé — la **lecture** (`index`, `show`) reste toujours possible.
Vérifié concrètement : `POST .../tasks` sur un projet archivé → 403 ; `GET .../tasks` sur
le même projet → 200.

**Piège vérifié en pratique** : à l'intérieur du middleware, `$request->route('project')`
renvoie bien l'**instance `Project` déjà résolue** (pas la chaîne `"projet-b"` brute) —
preuve que la résolution des bindings de route (`SubstituteBindings`) s'exécute **avant**
le middleware attaché au contrôleur. Si ce n'était pas le cas, `$project instanceof Project`
serait toujours `false` et le blocage 403 ne se déclencherait jamais silencieusement.

### `web` vs `api` : session et CSRF, vu en pratique (pas en théorie)

Tester les routes avec un `POST` brut (sans passer par un vrai formulaire Blade) a
immédiatement produit une **erreur 419** — le symptôme exact documenté dans `CLAUDE.md`
§13 (« 419 Page Expired → `@csrf` manquant »). Cause : les routes de TaskFlow vivent dans
`routes/web.php`, donc dans le groupe de middleware `web`, qui active la protection CSRF
et les sessions par défaut (contrairement à un groupe `api`, qui n'a ni session ni CSRF,
mais qui n'existe pas encore dans ce projet — il arrivera au Module 9 avec Sanctum).
Pour vérifier les routes correctement, il a donc fallu reproduire le vrai cycle CSRF :
1. `GET /` pour obtenir le cookie de session + le cookie `XSRF-TOKEN` (posé automatiquement
   par le middleware CSRF sur toute réponse du groupe `web`).
2. Renvoyer sa valeur (décodée) dans l'en-tête `X-XSRF-TOKEN` sur les requêtes `POST/PUT/DELETE`
   suivantes, avec la même session.

C'est exactement ce qu'un navigateur fait tout seul avec un vrai formulaire contenant
`@csrf` — la manip manuelle ici sert uniquement à vérifier les routes sans interface.

---

## Module 2 — Blade et les vues

### Layout en composant (`<x-layout>`) plutôt que `@extends`/`@section`

Blade propose deux façons de factoriser un squelette de page commun :

- **Classique** — une vue `layouts/app.blade.php` avec `@yield('content')`, et chaque page
  fait `@extends('layouts.app')` puis `@section('content') ... @endsection`.
- **Composants** (choisi pour TaskFlow) — `resources/views/components/layout.blade.php`
  est un composant Blade normal ; chaque page l'utilise comme une balise HTML :
  `<x-layout title="...">...contenu...</x-layout>`, et le contenu passé entre les balises
  devient `{{ $slot }}` à l'intérieur du composant.

Les deux font la même chose ; la syntaxe composant a été retenue ici parce que c'est la
même mécanique que les autres composants du projet (`<x-card>`, `<x-badge>`...) — un seul
concept à retenir (les composants) plutôt que deux.

### `@props` et l'`$attributes` bag

Chaque composant anonyme (`resources/views/components/*.blade.php`) déclare en première
ligne les variables qu'il attend, avec des valeurs par défaut :
```php
@props(['status' => 'default'])
```
Toute autre valeur passée à la balise (`class="mb-4"`, `id="..."`) qui n'est **pas**
listée dans `@props` atterrit automatiquement dans `$attributes` — un objet qu'on fusionne
sur l'élément racine avec `$attributes->merge([...])`. C'est ce qui permet d'écrire
`<x-card class="mb-4">` depuis l'extérieur et de voir cette classe s'ajouter (pas
remplacer) aux classes internes du composant (`rounded-lg border ...`).

### Slots nommés

`<x-modal>` a deux zones de contenu distinctes : le déclencheur (bouton qui ouvre la
modale) et le corps de la modale. Un slot nommé permet de les distinguer :
```blade
{{-- Dans le composant modal.blade.php --}}
@isset($trigger)
    <span @click="open = true">{{ $trigger }}</span>
@endisset
...
{{ $slot }}  {{-- le contenu "par défaut", non nommé --}}
```
```blade
{{-- À l'usage --}}
<x-modal>
    <x-slot:trigger>
        <x-button variant="secondary">Aperçu</x-button>
    </x-slot:trigger>

    <h3>...</h3>  {{-- ceci va dans $slot --}}
</x-modal>
```

### `@forelse` et `$loop`

`projects/index.blade.php` utilise `@forelse ($projects as $project) ... @empty ... @endforelse`
— une seule directive qui gère à la fois la boucle **et** l'état vide, sans `if (count(...) === 0)`
séparé. À l'intérieur de la boucle, Blade injecte automatiquement une variable `$loop`
(`$loop->iteration`, `$loop->count`, `$loop->first`, `$loop->last`...) — utilisée ici pour
afficher « Projet 2 / 3 » sans avoir à calculer d'index manuellement.

### Un modal interactif = du JS, donc Alpine.js

Un `<x-modal>` qui s'ouvre/se ferme est de l'**état côté client** (ouvert ou fermé) — Blade
seul ne peut pas faire ça, il ne s'exécute que côté serveur. D'où l'ajout d'**Alpine.js**
(pas prévu avant le Module 13 dans la table des dépendances, mais listé comme outil de
base dans `CLAUDE.md` §3.5 sans module associé — décision explicite pour ce composant) :
```blade
<div x-data="{ open: false }" @keydown.escape.window="open = false">
    <span @click="open = true">{{ $trigger }}</span>
    <div x-show="open" x-cloak>{{ $slot }}</div>
</div>
```
`x-data` déclare un petit état réactif local (`open`) ; `x-show` bascule `display:none` ;
`@click`/`@keydown` réagissent aux événements ; `@click.outside` (utilisé sur le panneau
interne) ferme la modale si on clique en dehors.

### Piège rencontré en vrai : `x-cloak` mal placé casse toute la page

Première tentative : `x-cloak` posé sur `<body>` dans le layout, dans l'idée d'éviter un
flash de contenu avant qu'Alpine soit prêt. Résultat en testant dans un vrai navigateur
(Playwright, capture d'écran) : **la page entière restait invisible** (`display: none`)
— `x-cloak` n'a été retiré par Alpine que sur les éléments réellement gérés par `x-show`/
`x-data`, pas de façon garantie sur un `<body>` sans rapport direct avec un composant
Alpine. Correction : `x-cloak` retiré de `<body>`, conservé uniquement sur la `<div x-show="open">`
du modal, où il a un rôle réel (éviter un flash de la modale ouverte avant qu'Alpine
l'ait basculée en fermée). **Ce bug n'était pas visible en lisant le code — seul le test
dans un navigateur (capture d'écran) l'a révélé.** Exactement la raison pour laquelle
`CLAUDE.md` interdit de considérer un changement front comme terminé sans l'avoir vu
tourner réellement.

### Vérification effectuée

Serveur de dev lancé, 3 projets de test créés (actif, archivé, sans tâche), page
`/projects` capturée : cartes, badges colorés (vert = actif, gris = archivé), compteur
`$loop`. Modale ouverte par clic sur « Aperçu », capturée, refermée par `Échap`, re-capturée
pour prouver qu'elle disparaît bien. Page `/projects/{slug}` capturée avec tâches
(badges de statut todo/in_progress/done) et sans tâche (état vide « Aucune tâche pour ce
projet. »). Aucune erreur console JS.

---

## Module 3 — Base de données, migrations, seeders

### Passage de SQLite à MySQL pour le développement

Jusqu'ici le projet tournait sur SQLite par défaut (squelette Laravel 12). `CLAUDE.md`
§3.1 prévoit MySQL/PostgreSQL comme base **de développement**, SQLite réservé aux tests.
Un serveur MySQL 8 était déjà disponible sur la machine → bascule effective : `.env`
pointe maintenant sur `mysql`/`taskflow`, pendant que `phpunit.xml` force `DB_CONNECTION=sqlite`
+ `DB_DATABASE=:memory:` **indépendamment du `.env`** — donc les tests restent rapides et
isolés sans configuration supplémentaire, exactement la séparation prévue.

### Schéma complet de TaskFlow

Onze tables au total : `users`, `teams`, `team_user` (pivot), `projects`, `tasks`,
`comments`, `tags`, `taggables` (pivot polymorphe), `attachments`, `activities`,
`notifications`. Deux familles de tables :

- **Relations classiques** : `team_user` (`team_id`, `user_id`, `role`, `unique(team_id, user_id)`)
  — pivot simple avec colonne supplémentaire, exactement le cas d'usage qui justifie une
  vraie table pivot plutôt qu'un simple `belongsToMany` sans données propres.
- **Relations polymorphes (schéma seulement)** : `comments`, `taggables`, `attachments`,
  `activities` utilisent `$table->morphs('commentable')` etc., qui crée en une ligne les
  deux colonnes (`commentable_type`, `commentable_id`) **et** leur index composite. Le
  code Eloquent qui exploite ce schéma (`morphTo`, `morphMany`) est volontairement
  **repoussé au Module 4** — ici, on pose juste des colonnes structurées pour ne pas avoir
  à retoucher le schéma plus tard. `Comment::factory()->on($task)` peuple ces colonnes
  directement (`commentable_type`/`commentable_id`) sans passer par une relation Eloquent,
  qui n'existe pas encore.

Table `notifications` créée via la commande standard `php artisan notifications:table`
(uuid, `notifiable_type`/`id`, `data` JSON, `read_at`) — c'est exactement le schéma que le
canal `database` des notifications (Module 7) consommera telle quelle, sans y toucher.

### Piège rencontré en vrai : `HasFactory` oublié

Premier `migrate:fresh --seed` : `BadMethodCallException: Call to undefined method
App\Models\Team::factory()`. Cause : aucun de nos modèles (`Project`, `Task` compris,
depuis le Module 1) n'avait le trait `use HasFactory`. Le stub généré par
`make:model` ne l'inclut pas automatiquement dans ce projet — il faut l'ajouter à la
main dès qu'on veut une factory. Corrigé sur les 7 modèles concernés. **Ce bug dormait
depuis le Module 1** sans se manifester, simplement parce qu'on n'avait jamais encore
appelé `::factory()` dessus.

### Factories : états et séquences

```php
// États nommés (ProjectFactory) — s'empilent à l'usage : Project::factory()->archived()->create()
public function archived(): static
{
    return $this->state(fn (array $attributes) => ['is_archived' => true]);
}
```
```php
// Séquence (DemoSeeder) — fait tourner un tableau d'états sur les modèles créés
Task::factory()->count(8)->for($project)->sequence(
    ['status' => 'todo'],
    ['status' => 'in_progress'],
    ['status' => 'done'],
)->create();
// tâche 1 → todo, tâche 2 → in_progress, tâche 3 → done, tâche 4 → todo, ...
```
`->for($project)` fonctionne parce que `Task::project(): BelongsTo` existe déjà (posé au
Module 1) — Laravel s'en sert pour déduire automatiquement `project_id`. À l'inverse,
`Project` n'a pas encore de relation `team()` (Module 4), donc `team_id` est passé
explicitement en état plutôt que via `->for()`.

### Transaction : `DB::transaction()`

Dans `DemoSeeder`, la création de l'équipe et l'insertion des lignes `team_user` sont
enveloppées dans `DB::transaction(function () { ... return $team; })`. Si l'insertion
des membres échouait à mi-chemin (contrainte violée, erreur réseau...), **toute
l'opération serait annulée** — jamais d'équipe orpheline sans membres en base. C'est le
critère pour décider d'une transaction : plusieurs écritures qui doivent réussir
**ensemble ou pas du tout**.

*(`lockForUpdate()` — verrou pessimiste posé sur une ligne pendant une transaction pour
empêcher deux processus de la modifier en même temps, ex. `Task::where(...)->lockForUpdate()->first()`
dans une transaction — n'a pas encore de cas d'usage réel dans TaskFlow (pas d'écriture
concurrente à protéger pour l'instant) : concept noté ici, sera exercé quand un vrai
scénario de concurrence apparaîtra, ex. compteur partagé ou changement de statut kanban.)*

### Query Builder : `groupBy`/`having`, `join`, sous-requête — testés en vrai

```php
DB::table('tasks')
    ->select('project_id', DB::raw('COUNT(*) as todo_count'))
    ->where('status', 'todo')
    ->groupBy('project_id')
    ->having('todo_count', '>', 5)
    ->get();
// → [{project_id: 1, todo_count: 16638}] (sur le jeu de test de l'exercice 2)

DB::table('tasks')
    ->join('projects', 'projects.id', '=', 'tasks.project_id')
    ->where('tasks.status', 'done')
    ->select('tasks.title', 'projects.name as project_name')
    ->get();
```
Ces deux requêtes ont été exécutées pour de vrai contre la base (pas juste écrites) —
`groupBy`+`having` filtre sur un agrégat calculé, `join` combine deux tables en une seule
requête au lieu de deux requêtes séparées + boucle PHP (ce qui serait un N+1, sujet
central du Module 4).

### Exercice 1 — Index composite `(project_id, status)` et règle du préfixe gauche

Migration dédiée `add_project_id_status_index_to_tasks_table` :
```php
$table->index(['project_id', 'status']);
```

**Piège rencontré en essayant de le retirer pour comparer avant/après** :
```
SQLSTATE[HY000]: General error: 1553 Cannot drop index 'tasks_project_id_status_index':
needed in a foreign key constraint
```
MySQL utilise cet index composite pour supporter la contrainte de clé étrangère sur
`project_id` (colonne en tête de l'index) — impossible de le supprimer seul sans casser
la FK. Plutôt que de contourner (dropper puis recréer la FK), ça a mené à une démonstration
plus juste : la **règle du préfixe gauche** d'un index composite, vérifiée avec `EXPLAIN`
sur un jeu de 50 000 tâches (exercice 2) :

| Requête | `type` | `key` utilisée | `rows` examinées |
|---|---|---|---|
| `WHERE project_id = ? AND status = ?` | `ref` | `tasks_project_id_status_index` | 24 995 |
| `WHERE project_id = ?` seul | `ref` | `tasks_project_id_status_index` | 24 995 |
| `WHERE status = ?` seul | **`ALL`** (scan complet) | *(aucune)* | 49 990 |

La colonne `status` n'étant **pas en tête** de l'index `(project_id, status)`, MySQL ne
peut pas s'en servir quand elle est filtrée seule — comme chercher un nom dans un
annuaire trié par prénom : l'index ne sert à rien si on ne connaît pas le prénom.
**Conclusion pratique** : l'ordre des colonnes dans un index composite doit suivre l'ordre
des filtres les plus fréquents dans le code, pas un ordre arbitraire.

### Exercice 2 — 50 000 tâches et chronométrage

`database/seeders/LargeTaskSeeder.php` insère 50 000 lignes par lots de 1 000
(`DB::table('tasks')->insert($rows)`) plutôt que 50 000 appels à `Task::create()` un par
un (qui aurait été dramatiquement plus lent — chaque `create()` Eloquent fait une requête
individuelle, un insert en masse en fait une par lot). Ne fait **pas** partie du seed de
démo courant : s'exécute à la demande via `php artisan db:seed --class=LargeTaskSeeder`.

Chronométrage réel sur ce jeu de données :
```
project_id + status (index utilisé) : 33.86 ms
status seul (scan complet)          : 31.22 ms
```
Différence quasi nulle en temps réel, alors que `EXPLAIN` montre un écart net de plan
d'exécution (24 995 lignes examinées contre 49 990). **Leçon honnête** : à 50 000 lignes,
MySQL garde toute la table en cache mémoire (InnoDB buffer pool), donc le scan complet
reste rapide malgré tout — l'intérêt de l'index se lira dans le **plan** (`EXPLAIN`) avant
de se voir clairement en millisecondes. L'écart deviendrait flagrant en temps réel à partir
de plusieurs millions de lignes, ou sur un serveur avec moins de mémoire disponible que la
taille de la table. Ne pas se fier uniquement au chronomètre pour juger un index — lire le
plan d'exécution est plus fiable.

---

## Module 4 — Eloquent, le cœur du framework

### Toutes les relations, en un coup d'œil

| Modèle | Relation | Type |
|---|---|---|
| `User` | `teams()` | `belongsToMany` (pivot `team_user`, `withPivot('role')`) |
| `User` | `assignedTasks()`, `comments()`, `attachments()`, `activities()` | `hasMany` |
| `Team` | `users()` | `belongsToMany` (inverse du pivot) |
| `Team` | `projects()` | `hasMany` |
| `Team` | `tasks()` | **`hasManyThrough`** (Team → Project → Task, sans relation intermédiaire chargée) |
| `Project` | `team()` | `belongsTo` |
| `Project` | `tasks()` | `hasMany` |
| `Task` | `project()`, `assignee()`, `parent()` | `belongsTo` |
| `Task` | `subtasks()` | `hasMany` (auto-référence sur `parent_id`) |
| `Task` | `comments()`, `attachments()`, `activities()` | **`morphMany`** |
| `Task` | `tags()` | **`morphToMany`** |
| `Tag` | `tasks()` | **`morphedByMany`** (l'autre sens du `morphToMany`) |
| `Comment`/`Attachment`/`Activity` | `commentable()`/`attachable()`/`subject()` | **`morphTo`** |

Toutes vérifiées avec un script chargeant chaque relation et affichant un résultat réel
(pas juste "ça compile") avant d'être considérées acquises.

### `hasManyThrough` : `Team::tasks()`

```php
public function tasks(): HasManyThrough
{
    return $this->hasManyThrough(Task::class, Project::class);
}
```
Traverse `Team → Project → Task` **en une seule requête SQL** (un `JOIN`), sans avoir à
charger tous les projets d'abord puis boucler dessus pour récupérer leurs tâches. Vérifié :
`$team->tasks()->count()` renvoie directement le total, un seul aller-retour base.

### Enum PHP casté : `TaskStatus`

```php
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string { /* ... */ }
    public function color(): string { /* ... */ }
}

// Task.php
protected $casts = ['status' => TaskStatus::class, ...];
```
`$task->status` n'est plus une chaîne mais une vraie instance `TaskStatus` — impossible
d'assigner une valeur hors de l'enum par erreur (`$task->status = 'archivé'` échouerait),
et `$task->status->label()`/`->color()` centralisent l'affichage au lieu de le disperser
dans chaque vue Blade avec des `match()` dupliqués.

### Scopes : local vs global — la nuance vue en pratique

- **Local** (`scopeForTeam`) : opt-in, explicite, utilisable n'importe quand —
  `Project::forTeam($team)->get()`.
- **Global** (`TeamScope`, classe implémentant `Scope`, enregistrée dans
  `Project::booted()`) : s'applique **automatiquement** à toute requête sur `Project`,
  sans rien écrire au point d'appel.

Problème concret : un scope global « équipe courante » a besoin de savoir *quelle* équipe
est courante — information qui, normalement, vient de l'utilisateur connecté (Module 6,
pas encore fait). Solution : un petit service `CurrentTeam` (singleton dans le conteneur,
vide par défaut) que le scope consulte ; tant que rien ne l'a rempli, le scope est un
no-op et ne casse aucune requête existante. **Vérifié concrètement** avec deux équipes
distinctes : `CurrentTeam` vide → tous les projets (5) ; rempli avec l'équipe A → 4
projets (tous sauf celui de l'équipe B) ; rempli avec l'équipe B → 1 projet. Le Module 6
n'aura qu'à appeler `app(CurrentTeam::class)->set($user->currentTeam)` sur chaque requête
authentifiée — rien d'autre à changer ici.

### Accessor moderne : `Task::isOverdue`

```php
protected function isOverdue(): Attribute
{
    return Attribute::make(
        get: fn () => $this->due_date !== null
            && $this->due_date->isPast()
            && $this->status !== TaskStatus::Done,
    );
}
```
Méthode `isOverdue()` (camelCase) → accessible via `$task->is_overdue` (snake_case,
conversion automatique de Laravel). Rien n'est stocké en base : recalculé à chaque accès
à partir de `due_date` et `status`. Évite qu'un même calcul de « en retard » soit
réécrit différemment dans plusieurs vues.

### Cast personnalisé : `HexColor`

```php
class HexColor implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string { return $value; }
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : '#'.strtolower(ltrim(trim($value), '#'));
    }
}
```
Appliqué à `Project::color` et `Tag::color`. Vérifié : écrire `'FFAA00'` (sans `#`, en
majuscules) stocke et relit `'#ffaa00'` — la normalisation se fait **à un seul endroit**
plutôt que dans chaque formulaire qui écrit une couleur.

### Soft deletes sur `Task`

```php
use SoftDeletes; // + migration $table->softDeletes()
```
`$task->delete()` ne supprime plus la ligne : il pose `deleted_at`. Conséquences
vérifiées :
- `Task::find($id)` → `null` après suppression (les soft-deleted sont exclus par défaut).
- `Task::withTrashed()->find($id)` → la retrouve.
- `->restore()` → réapparaît dans les requêtes normales.

**Aucun changement nécessaire dans `TaskController::destroy()`** (toujours
`$task->delete()`) — c'est tout l'intérêt du trait : le code appelant ne sait pas que le
comportement a changé sous lui.

### Observer : `TaskObserver`

```php
public function updating(Task $task): void
{
    if (! $task->isDirty('status')) return;
    $task->completed_at = $task->status === TaskStatus::Done ? now() : null;
}

public function updated(Task $task): void
{
    if (! $task->wasChanged('status')) return;
    Activity::create([...]); // journalise l'ancien et le nouveau statut
}
```
`updating()` (avant l'écriture) modifie `completed_at` **dans la même requête UPDATE**
que le changement de statut — pas de requête séparée. `updated()` (après l'écriture)
journalise dans `activities`, avec l'ancien statut lu via `getOriginal('status')`.

**Piège rencontré en vrai** : le `DemoSeeder` créait les tâches "terminées" directement
via `Task::factory()->create(['status' => 'done'])` — un `INSERT`, pas un `UPDATE`, donc
les événements `updating`/`updated` **ne se déclenchent jamais** (ce sont `creating`/
`created` qui s'exécutent à la création, pas ceux-là). Corrigé : les tâches "terminées"
du seed sont maintenant créées `todo`/`in_progress` puis **réellement transitionnées** via
`$task->update(['status' => TaskStatus::Done])`, ce qui active l'observer pour de vrai —
plus fidèle à ce qui se passera dans l'application réelle (un utilisateur qui déplace une
carte kanban fait un `update()`, jamais un `create()` direct en "done").

**Deuxième piège, plus sournois** : même après cette correction, `completed_at` restait
`null`. Cause : `DatabaseSeeder` avait `use WithoutModelEvents;` — un trait généré par
défaut par Laravel qui désactive **tous** les événements de modèle pendant tout le
seeding (pensé pour accélérer la création en masse via les factories). Sauf qu'il
désactivait aussi l'observer qu'on voulait voir se déclencher. Retiré, avec un
commentaire expliquant pourquoi, pour que personne ne le remette « par habitude ».

### Exercice 1 — Dashboard et chasse aux N+1

Page `/dashboard` : liste des projets avec leurs tâches et l'assigné de chaque tâche,
plus un flux d'activité récente. Mesuré en appelant directement le contrôleur et en
comptant les requêtes via `DB::listen()`/`getQueryLog()` (isole le coût des données,
sans le bruit des requêtes de session/cache d'une vraie requête HTTP — Debugbar, lui,
recompte tout, voir plus bas) :

| Version | Requêtes SQL | Temps mesuré |
|---|---|---|
| Naïve (`Project::all()`, aucun eager loading) | 42 | 238,48 ms |
| Optimisée (`Project::with('tasks.assignee')`) | 6 | 43,75 ms |

Soit **7× moins de requêtes** et **~5,5× plus rapide** en temps réel mesuré sur ce jeu de
données (le programme du module visait « 10× » à titre indicatif — l'écart réel dépend
du volume de données ; avec plus de projets/tâches, le fossé entre N+1 et eager loading
se creuse encore, puisque la version naïve grandit linéairement avec N alors que la
version optimisée reste à un nombre de requêtes constant).

**Nuance Debugbar vs mesure isolée** : en visitant `/dashboard` dans un vrai navigateur,
Debugbar affiche 12 requêtes (pas 6) — parce qu'une vraie requête HTTP traverse aussi la
session (`SESSION_DRIVER=database`), le cache, Telescope... Ce sont de vraies requêtes,
mais constantes, indépendantes du nombre de projets/tâches — donc hors sujet pour évaluer
un N+1. D'où la mesure isolée ci-dessus pour juger spécifiquement l'effet de
l'eager loading.

### Exercice 2 — Top 5 utilisateurs, tâches terminées ce mois, en une requête

```php
User::query()
    ->withCount(['assignedTasks as completed_this_month' => function ($query) {
        $query->where('status', TaskStatus::Done)
            ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()]);
    }])
    ->orderByDesc('completed_this_month')
    ->limit(5)
    ->get();
```
`withCount` avec une closure de contrainte génère un **sous-select corrélé** dans la
requête principale (`(select count(*) from tasks where users.id = tasks.assignee_id and
...) as completed_this_month`) — vérifié : **1 seule requête** dans le log SQL, pas une
requête de comptage par utilisateur.

### Exercice 3 — `foreach` + accumulateur → pipeline de Collections

```php
// Avant
$counts = [];
foreach ($projects as $project) {
    foreach ($project->tasks as $task) {
        $status = $task->status->value;
        $counts[$status] ??= 0;
        $counts[$status]++;
    }
}

// Après
$counts = $projects
    ->flatMap(fn (Project $project) => $project->tasks)
    ->groupBy(fn (Task $task) => $task->status->value)
    ->map(fn ($tasks) => $tasks->count());
```
Vérifié : les deux versions produisent exactement le même résultat sur le même jeu de
données (`{"done":12,"in_progress":10,"todo":10}`). Aucune requête SQL supplémentaire —
les collections sont déjà en mémoire (chargées par le dashboard), tout se passe en PHP.
La version pipeline gagne surtout en lisibilité : chaque étape (aplatir, grouper, compter)
est nommée, alors que la version `foreach` mélange itération et accumulation dans la même
boucle.

**Piège Larastan rencontré** : `flatMap` puis `groupBy` avec des closures typées
(`fn (Task $task) => ...`) faisait échouer l'analyse statique — Larastan perd le type
précis des éléments à travers `flatMap` (il le voit comme `Model` générique, pas `Task`).
Corrigé avec une annotation `@var Collection<int, Task>` sur la variable intermédiaire :
pas pour cacher une erreur réelle, juste pour donner à l'analyseur une information qu'il
ne peut pas déduire seul à travers ce enchaînement précis.

### Page kanban — validation module 4 (≤ 5 requêtes SQL)

`GET /projects/{project}/board` (bouton « Vue kanban » sur la page projet, pas seulement
une route tapée à la main) : colonnes À faire / En cours / Terminée, tâches groupées en
mémoire via `->groupBy()` (même technique que l'exercice 3). Mesuré directement :

```
1. select * from projects limit 1                                    (résolution {project})
2. select * from tasks where project_id = ? and deleted_at is null   (tâches du projet)
3. select * from users where id in (...)                             (assignés, eager load)
```
**3 requêtes**, et ce nombre ne dépend structurellement pas du nombre de tâches : aucune
des trois requêtes n'est exécutée dans une boucle, seule la liste de valeurs du `in (...)`
grandit avec le nombre d'assignés distincts — jamais le nombre de requêtes lui-même.

---

## Module 5 — Validation et objets de requête

### Form Request plutôt que `$request->validate()`

Depuis le Module 1, `ProjectController`/`TaskController` validaient en ligne
(`$request->validate([...])`). Remplacé par des classes dédiées
(`StoreProjectRequest`, `UpdateProjectRequest`, `StoreTaskRequest`, `UpdateTaskRequest`,
`InviteMemberRequest`) : le contrôleur reçoit directement des données déjà validées
(type-hint sur la classe de la requête), sans un seul appel à `validate()` dans son corps.
Chaque Form Request a sa propre méthode `authorize()` — actuellement `return true` partout
(pas de Policy avant le Module 6), mais l'emplacement existe déjà : le Module 6 n'aura
qu'à changer cette méthode, jamais les routes ni les contrôleurs.

### Règles rencontrées, chacune avec un usage réel dans TaskFlow

| Règle | Où | Rôle |
|---|---|---|
| `sometimes` | `UpdateProjectRequest`, `UpdateTaskRequest` | Ne valide un champ que s'il est présent dans la requête (mise à jour partielle) |
| `required_if:priority,high` | `StoreTaskRequest.due_date` | Rend l'échéance obligatoire **seulement** si la priorité est `high` |
| `Rule::unique(...)->ignore($project)` | `UpdateProjectRequest.slug` | Unique, **sauf** par rapport à sa propre ligne (sinon impossible de ré-enregistrer un projet sans changer son slug) |
| `Rule::in(['low','normal','high'])` | `StoreTaskRequest.priority` | Liste de valeurs autorisées, définie à la main |
| `Rule::enum(TaskStatus::class)` | `UpdateTaskRequest.status` | Même chose, mais dérivée d'un enum PHP (Module 4) — une seule source de vérité entre le cast du modèle et la validation |
| `'tags' => 'array'`, `'tags.*' => 'exists:tags,id'` | `StoreTaskRequest` | Valide un tableau **et** chacun de ses éléments |
| Classe `NotAssignedToArchivedProject` | `StoreTaskRequest`/`UpdateTaskRequest.assignee_id` | Règle métier personnalisée, voir plus bas |

### Règle personnalisée : complète le middleware, ne le remplace pas

`NotAssignedToArchivedProject` (implémente `ValidationRule`) empêche d'assigner quelqu'un
à une tâche d'un projet archivé. **Constat en la testant** : sur les routes actuelles,
cette règle ne se déclenche jamais en pratique — le middleware `EnsureProjectIsNotArchived`
(Module 1) bloque déjà `store`/`update` sur un projet archivé avec un 403, **avant** que
la validation ne s'exécute. La règle a donc été vérifiée **isolément** (instanciée
directement, `->validate()` appelé à la main avec un projet archivé puis actif) plutôt que
via une requête HTTP réelle — sinon impossible de l'atteindre. Gardée quand même : défense
en profondeur (si le middleware est un jour retiré ou que le champ est validé ailleurs,
par exemple une future API), et message d'erreur rattaché au bon champ de formulaire
plutôt qu'un simple 403 générique.

### DTO : `CreateTaskData`

```php
final readonly class CreateTaskData
{
    public function __construct(
        public string $title,
        public ?string $priority,
        public ?string $dueDate,
        public ?int $assigneeId,
        public array $tagIds,
    ) {}

    public static function fromArray(array $validated): self { /* ... */ }
}
```
Entre `$request->validated()` (un tableau, aucune garantie de forme) et la création de la
tâche, `TaskController::store()` construit ce DTO. Bénéfice concret : les noms de
propriétés sont vérifiés par PHP (`$data->title`, pas `$data['titel']` qui planterait
silencieusement avec un tableau). L'extraction vers une vraie classe Action/Service qui
consommerait ce DTO (au lieu du contrôleur lui-même) est le sujet du Module 11 — ici, le
DTO existe déjà, seul son consommateur final changera.

### Traduction complète en français

`php artisan lang:publish` a fait apparaître `lang/en/*.php` (fichiers absents du
squelette Laravel par défaut). Traduits intégralement vers `lang/fr/` (`validation.php`,
`auth.php`, `passwords.php`, `pagination.php`), `APP_LOCALE=fr` dans `.env`. Trois niveaux
de personnalisation, du plus général au plus spécifique :
1. `lang/fr/validation.php` → messages par règle (« Le champ :attribute est obligatoire »).
2. `lang/fr/validation.php['attributes']` → noms de champs en français, appliqués partout
   (`slug` → « identifiant », etc.).
3. `messages()`/`attributes()` dans un Form Request précis (`InviteMemberRequest`) →
   surcharge locale, prioritaire sur les deux niveaux précédents.

**Vérifié avec de vraies requêtes HTTP**, pas en lisant le code :
```
titre manquant          -> "Le champ titre est obligatoire."
priority=high sans date -> "La date d'échéance est obligatoire pour une tâche de priorité haute."
tag inexistant           -> "La valeur sélectionnée pour tags.0 est invalide."
email inexistant         -> "Aucun compte ne correspond à cette adresse e-mail." (message personnalisé)
rôle invalide             -> "La valeur sélectionnée pour rôle est invalide." (Rule::enum + attribut traduit)
```

### `old()` / `@error` — vues réelles, pas juste une API JSON

Les actions `create()`/`edit()` de `ProjectController` renvoyaient `abort(501, ...)`
depuis le Module 1 (Blade pas encore couvert). Corrigées maintenant : vrais formulaires
(`projects/create.blade.php`, `projects/edit.blade.php`) avec `@csrf`, `@method('PUT')`,
`old('name', $project->name)` (pré-remplit avec la valeur soumise en cas d'erreur, sinon
retombe sur la valeur actuelle du modèle en édition) et `@error('champ')` sous chaque
input. Testé dans un vrai navigateur : formulaire vide soumis → erreurs françaises
affichées sous les bons champs ; formulaire rempli → redirection + bannière verte
« Projet créé. » (`session('success')`, affichée une fois dans le layout) ; slug déjà pris
en édition → erreur d'unicité sous le champ concerné, la valeur fautive reste dans le champ.

### Piège réel trouvé en testant le formulaire dans un navigateur

Premier test du formulaire de création : soumission réussie en apparence, mais l'URL
finale était `/projects` au lieu de `/projects/{slug}`, et la page affichée n'avait plus
la mise en page habituelle. Cause : `Project::create($request->validated())` n'incluait
jamais `team_id` — colonne `NOT NULL` avec clé étrangère (Module 3) — donc l'insertion SQL
échouait, et comme la requête `POST` et la route `GET /projects` (index) partagent la
même URI `/projects`, la page d'erreur Laravel (mode debug) s'affichait à cette URI,
donnant l'illusion d'un retour normal à la liste. **Cause racine** : il n'y a pas encore
d'utilisateur connecté (Module 6) pour déduire à quelle équipe rattacher le nouveau
projet. Corrigé provisoirement : le contrôleur rattache le projet à l'équipe du contexte
`CurrentTeam` (Module 4) si présente, sinon à la première équipe existante — avec un
commentaire explicite disant que le Module 6 remplacera cette logique par l'équipe réelle
de l'utilisateur connecté. Sans le test dans un vrai navigateur, ce bug serait resté
invisible : `php artisan test` ne le couvrait pas, et lire le code ne le révèle pas
(le `$fillable` de `Project` autorise `team_id`, rien ne signale qu'il manque).

---

## Module 7 — Fichiers, e-mails, notifications

### `Storage` : un disque abstrait, jamais le disque en dur

`Storage::disk()` (sans argument = le disque par défaut, `FILESYSTEM_DISK`) ou
`$file->store('attachments/'.$task->id)` : aucune méthode de `AttachmentController`
ne nomme `local` ou `s3`. C'est ce qui permet de changer `.env` sans changer le code.
Doc : https://laravel.com/docs/12.x/filesystem#configuration

**Vérifié pour de vrai, pas seulement lu** : upload + téléchargement d'une pièce jointe
avec `FILESYSTEM_DISK=local`, puis `FILESYSTEM_DISK=public` (pas de vraies clés AWS en
local — même principe, seul le driver change) : même code, même comportement, le fichier
atterrit simplement à un autre endroit sur disque (`storage/app/private/...` puis
`storage/app/public/...`).

**Décision consciente** : les pièces jointes ne sont **pas** servies par une URL publique
directe (ce que permettrait le disque `public` + son lien symbolique). Elles passent par
`AttachmentController::download()` → `Storage::download()`, protégé par
`$this->authorize('view', $task)`. Sinon, n'importe qui avec le lien pourrait télécharger
le fichier d'une équipe à laquelle il n'appartient pas — tout le travail des policies du
Module 6 serait contourné par une simple URL statique.

### Piège réel : une policy qui plante au lieu de refuser

Premier test avec un compte hors équipe qui tente de télécharger une pièce jointe d'une
tâche d'une autre équipe : **500**, pas 403. Cause : `TaskPolicy` faisait
`$task->project->team` — mais `$task->project` est un accès relationnel **paresseux**,
donc une vraie requête Eloquent, donc **filtrée par le scope global `TeamScope`** de
`Project` (Module 4). Le middleware `SetCurrentTeam` (Module 6) a déjà positionné
l'équipe *courante* de la personne qui demande l'accès à ce moment de l'exécution : pour
une tâche d'une équipe différente, `$task->project` renvoie donc `null` — pas le vrai
projet, un projet absent — et `null->team` explose.

Piège subtil car **le scope censé protéger empêche justement de constater qu'il faut
refuser l'accès**. Corrigé avec une relation dédiée, réservée aux policies :
```php
public function projectForAuthorization(): BelongsTo
{
    return $this->belongsTo(Project::class, 'project_id')->withoutGlobalScope(TeamScope::class);
}
```
Leçon générale : une policy doit toujours voir la vraie donnée, jamais une donnée
pré-filtrée par le contexte de la personne qui demande l'accès — sinon la vérification
elle-même devient invisible à ses propres yeux.

### Mailable Markdown + `ShouldQueue`

`TeamInvitationMail` (minimal depuis le Module 6, comme annoncé) devient un vrai Mailable
Markdown avec `envelope()`/`content()` plutôt que l'ancien `build()`, et implémente
`ShouldQueue` :
```php
public function content(): Content
{
    return new Content(markdown: 'emails.team-invitation');
}
```
La vue `emails/team-invitation.blade.php` utilise les composants `<x-mail::message>` /
`<x-mail::button>` : un seul fichier Blade génère à la fois la version HTML stylée et la
version texte brut envoyées ensemble dans le même e-mail (vérifié dans le log : les deux
parties MIME sont bien présentes).
Doc : https://laravel.com/docs/12.x/mail#markdown-mailables

**`ShouldQueue` vérifié pour de vrai**, pas supposé : après avoir déclenché une invitation,
`DB::table('jobs')->count()` passe à 1 **avant** que quoi que ce soit apparaisse dans le
log — l'e-mail n'est pas encore parti, juste mis en file. Après
`php artisan queue:work --once`, le job disparaît de `jobs` et l'e-mail apparaît dans le
log. Sans `queue:work` qui tourne, l'invitation ne partirait jamais : point d'attention
pour la mise en production (approfondi au Module 8, superviseur de queue).

### Notifications : un seul message, plusieurs canaux

`TaskAssignedNotification` (`via()` retourne `['mail', 'database']`) illustre la
différence avec un Mailable : la même classe produit `toMail()` (un e-mail) **et**
`toArray()` (une ligne dans la table `notifications`, déjà migrée depuis le Module 3).
Doc : https://laravel.com/docs/12.x/notifications#creating-notifications

**Choix assumé et documenté dans le code** : contrairement à `TeamInvitationMail`, cette
notification n'implémente **pas** `ShouldQueue`. Le canal `database` alimente le compteur
de non-lus affiché dans la navigation à chaque page — la mettre en file d'attente
retarderait ce compteur tant qu'aucun worker ne tourne, ce qui casserait la démonstration
du centre de notifications. `TeamInvitationMail` reste la démonstration de référence de
l'envoi en file d'attente ; les deux approches (synchrone / en file) existent pour de
bonnes raisons différentes, ni l'une ni l'autre n'est « la bonne façon » dans l'absolu.

Déclenchée depuis `TaskObserver` (déjà là depuis le Module 4), sur deux événements
distincts : `created()` (une tâche qui naît déjà assignée) et `updated()` avec
`wasChanged('assignee_id')` (réassignation). Testé avec un changement d'assigné réel :
ligne créée dans `notifications` (`read_at` à `null`), e-mail présent dans le log
immédiatement (pas de délai, cohérent avec l'absence de `ShouldQueue`).

### Centre de notifications

`auth()->user()->unreadNotifications` (méthode fournie par le trait `Notifiable`, déjà
sur `User` depuis Breeze) compte les notifications non lues — affiché en badge dans la
navigation sur chaque page. Page `/notifications` : liste paginée, bouton individuel
« Marquer comme lu » (`DatabaseNotification::markAsRead()`) et bouton global « Tout
marquer comme lu ». Vérifié dans un vrai navigateur : badge « 1 » après assignation,
disparaît après clic sur « Marquer comme lu ».

### Validation du module

`FILESYSTEM_DISK=local` → `public` sans toucher un seul fichier applicatif (vérifié
ci-dessus). `php artisan test` reste vert (25 tests), `pint`/`phpstan` verts.

---

## Module 8 — Files d'attente, jobs, planification, événements

### Redis / Horizon : sciemment mis de côté

Ni l'extension PHP `redis` ni un serveur Redis ne sont installés sur cette machine.
Horizon (supervision des queues) est **spécifique à Redis** — impossible à faire tourner
sans lui. Décision : rester sur `QUEUE_CONNECTION=database` (déjà en place depuis le
départ) pour tout le module — jobs, chaînes, batches, échecs/rejeu fonctionnent
identiquement quel que soit le driver, seule la supervision avancée (tableau de bord
Horizon) change. Documenté plutôt qu'installé à moitié.

### `GenerateThumbnail` : un job réel, idempotent

```php
class GenerateThumbnail implements ShouldQueue
{
    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public int $attachmentId) {}

    public function handle(): void
    {
        $attachment = Attachment::find($this->attachmentId);
        if ($attachment === null || $attachment->thumbnail_path !== null) {
            return; // déjà fait, ou plus rien à faire
        }
        // ... génère la miniature (intervention/image, driver GD)
    }
}
```
`$this->attachmentId` (un entier), jamais `$this->attachment` (le modèle) : un job est
sérialisé et stocké tel quel dans la table `jobs`, potentiellement pour un moment avant
qu'un worker le reprenne — un modèle complet gonflerait la charge utile inutilement, et
ses données pourraient être périmées d'ici l'exécution.

**Piège d'environnement réel** : ni GD ni Imagick n'étaient activés (`php -m` muet sur
les deux). `extension=gd` décommenté dans `php.ini` (même geste que `zip` au Module 0),
vérifié avec une vraie image PNG générée par GD, uploadée, transformée en miniature de
300px de large — fichier physiquement plus petit sur disque, chemin enregistré en base.

**Idempotence vérifiée, pas seulement affirmée** : rejouer le job sur la même pièce
jointe (déjà traitée) retourne en ~100 ms au lieu de ~250 ms — preuve que le
retraitement de l'image est bien sauté, pas recalculé silencieusement en double.

### Échec réel, `failed_jobs`, puis `queue:retry`

Fichier stocké volontairement corrompu (octets non-image), job redispatché. Le paramètre
`--tries` de `queue:work` **ne l'emporte pas** sur la propriété `$tries` du job lui-même
— avec `$tries = 3` sur `GenerateThumbnail`, il a fallu 3 tentatives réelles (forcées en
remettant `available_at` à `now()` entre chacune, plutôt que d'attendre le délai de
nouvelle tentative) avant que Laravel ne le bascule dans `failed_jobs`. Fichier réparé,
`php artisan queue:retry <uuid>` (repris de `queue:failed`), rejoué avec succès :
`thumbnail_path` correctement renseigné à la fin. Le cycle complet — échec, stockage de
l'échec, correction, rejeu, succès — a été vérifié de bout en bout, pas juste la
commande `queue:retry` isolément.

### Batch : import CSV de tâches avec suivi de progression

```php
$jobs = collect($rows)->map(fn (array $row) => new ImportTaskRow($project->id, $row));
$batch = Bus::batch($jobs)->name("Import CSV — {$project->name}")->dispatch();
// -> $batch->id renvoyé au client, qui interroge GET /imports/{batch} pour suivre
```
Chaque ligne du CSV = un job indépendant (`ImportTaskRow`) : une ligne invalide (testé
avec un titre vide) échoue **seule**, sans faire échouer les autres. Vérifié avec un CSV
de 5 lignes (4 valides, 1 invalide) traité une ligne à la fois : progression 20 % → 40 %
→ 60 % → 80 %, puis `failed=1` sur la dernière — `$batch->finished()` devient `true`
malgré l'échec partiel, et exactement 4 tâches ont été créées (pas 5, pas 0).
`ImportTaskRow` utilise aussi `firstOrCreate` par titre : idempotent, un rejeu du batch
ne duplique pas les lignes déjà importées avec succès.

### `TaskMoved` : événement + listener en file d'attente

Jusqu'ici, `TaskObserver::updated()` écrivait directement dans `activities`. Refactorisé
en événement de domaine explicite :
```php
// TaskObserver::updated()
event(new TaskMoved($task, $from, $task->status));
```
`LogTaskMovedActivity implements ShouldQueue` écoute cet événement et journalise
— **découvert automatiquement** par convention (voir le piège du Module 6 sur le double
enregistrement : celui-ci n'est PAS ré-enregistré à la main). Différence avec l'Observer
(Module 4) : l'Observer reste pour ce qui est intrinsèquement lié à la persistance
(`completed_at`, mis à jour *avant* l'écriture, dans la même requête SQL) ; l'événement
sert pour les réactions métier découplées, qui peuvent être multiples et indépendantes
(journalisation **et** diffusion temps réel, voir plus bas — deux jobs distincts déposés
par un seul `event()`).

**Vérifié pour de vrai** : un changement de statut dépose 2 jobs (`jobs` passe de 0 à 2 :
le listener + la diffusion automatique de `ShouldBroadcast`), la table `activities` ne
change qu'**après** `queue:work` — la journalisation n'est plus synchrone.

### Planificateur — rapport hebdomadaire

```php
// routes/console.php
Schedule::command('taskflow:send-weekly-report')->mondays()->at('08:00')->withoutOverlapping();
```
`withoutOverlapping()` : si l'envoi d'un lundi prenait plus d'une minute (le scheduler
est réévalué chaque minute via `schedule:work` ou une entrée cron), on ne veut jamais
deux exécutions simultanées qui enverraient le rapport en double.
Doc : https://laravel.com/docs/12.x/scheduling#preventing-task-overlaps

`SendWeeklyReport` calcule de vraies statistiques par équipe (tâches terminées cette
semaine, projets actifs, meilleur contributeur — mêmes requêtes que le tableau de bord
du Module 4/6, filtrées par équipe) et envoie `WeeklyReportMail` (Markdown,
`ShouldQueue`) aux Owner/Admin de chaque équipe. Exécuté manuellement pour vérifier
(`php artisan taskflow:send-weekly-report`, hors calendrier) : barre de progression
réelle, 2 e-mails mis en file (un par destinataire), contenu vérifié dans le log —
« Tâches terminées cette semaine : 13 », etc., pas des valeurs inventées.

### Commande Artisan `taskflow:cleanup-archived`

Pour lui donner un sens réel, un projet archivé mémorise désormais *depuis quand*
(`archived_at`, jamais dans `$fillable` — positionné uniquement par un hook
`static::updating()` dans `Project::booted()`, pour qu'aucun client ne puisse l'falsifier
afin d'échapper au nettoyage) :
```php
protected static function booted(): void
{
    static::updating(function (Project $project) {
        if ($project->isDirty('is_archived')) {
            $project->archived_at = $project->is_archived ? now() : null;
        }
    });
}
```
```
php artisan taskflow:cleanup-archived --days=90 [--force]
```
Affiche d'abord un tableau (`$this->table()`) des projets concernés, demande confirmation
sauf `--force`, supprime avec une barre de progression. La suppression du projet suffit à
faire disparaître ses tâches : `tasks.project_id` a `cascadeOnDelete()` depuis le
Module 3. **Vérifié réellement** : projet antidaté à 120 jours d'archivage, commande
lancée sans `--force` (tableau affiché, annulation par défaut confirmée), puis avec
`--force` — projet et ses 12 tâches bien absents de la base après coup.

### Broadcasting temps réel — Reverb + Echo

`php artisan install:broadcasting --reverb` : génère `config/broadcasting.php`,
`config/reverb.php`, `routes/channels.php`, `resources/js/echo.js`, installe
`laravel-echo`/`pusher-js` côté npm, ajoute les clés `REVERB_*` à `.env`
(`BROADCAST_CONNECTION=reverb`).

`TaskMoved implements ShouldBroadcast` (déjà écrit pour l'événement ci-dessus) diffuse
sur un **canal privé** scopé au projet :
```php
public function broadcastOn(): array
{
    return [new PrivateChannel('projects.'.$this->task->project_id)];
}
```
Autorisation dans `routes/channels.php` — un canal privé exige un callback qui décide qui
a le droit d'écouter, comme une Policy :
```php
Broadcast::channel('projects.{projectId}', function (User $user, int $projectId) {
    $project = Project::withoutGlobalScope(TeamScope::class)->find($projectId);
    return $project !== null && $user->roleIn($project->team) !== null;
});
```
Côté navigateur, `resources/js/echo.js` (généré) expose `window.Echo` globalement ; le
kanban s'y abonne (`window.Echo.private('projects.'+id).listen('.task.moved', ...)`) et
réagit à l'événement — pas de glisser-déposer pour l'instant (le vrai kanban interactif
est le sujet du Module 13), juste deux boutons « déplacer » suffisants pour prouver le
temps réel avec le minimum de surface ajoutée.

**Vérifié avec deux navigateurs réellement ouverts en parallèle**, pas en lisant la
doc : Alice et Bob, connectés chacun avec leur propre session, sur le même tableau
kanban. Alice clique « En cours → » sur une tâche. **Sans aucune action de Bob**, sa
page affiche la bannière « Une tâche vient d'être déplacée — actualisation… » et se
recharge d'elle-même moins de deux secondes plus tard. Chaîne complète empruntée pour de
vrai : requête HTTP d'Alice → `TaskObserver` → `event(TaskMoved)` → job de diffusion
automatique en file → `queue:work` (worker lancé pour le test) → serveur Reverb
(`php artisan reverb:start`, lancé pour le test) → canal privé authentifié
(`POST /broadcasting/auth`, visible dans les requêtes de la page de Bob) → `Echo` →
rechargement.

### Validation du module

Aucune action lente ne bloque une réponse HTTP : miniatures, imports CSV, e-mails et
diffusion temps réel partent tous en file d'attente. `php artisan test` reste vert
(25 tests), `pint`/`phpstan` verts.

---

## Module 9 — API REST et Sanctum

### Sanctum : un guard de plus, pas un système à part

`php artisan install:api` crée `routes/api.php`, la table `personal_access_tokens`, et
demande d'ajouter `HasApiTokens` sur `User`. Le guard `sanctum` vérifie un en-tête
`Authorization: Bearer …` ; le guard `web` (Module 6) continue de vérifier la session.
Les **mêmes** `ProjectPolicy`/`TaskPolicy` protègent les deux — l'autorisation ne dépend
jamais de la façon dont on s'est authentifié, seulement de qui on est et de ce qu'on a le
droit de faire.
Doc : https://laravel.com/docs/12.x/sanctum#issuing-api-tokens

### Versionnement et structure

`routes/api.php` groupe tout sous `Route::prefix('v1')->name('api.v1.')`, et les
contrôleurs vivent dans `App\Http\Controllers\Api\V1\*` (namespace séparé des
contrôleurs web, mêmes noms de classe sans collision). Versionner dès la v1 évite de
devoir retrofit un préfixe `/v2` en urgence le jour où un breaking change devient
nécessaire — le coût de la discipline est nul tant qu'il n'y a qu'une version.

### API Resources : `whenLoaded`, `whenCounted`, liens

```php
'tasks_count' => $this->whenCounted('tasks'),      // absent si pas de withCount('tasks')
'assignee' => new UserResource($this->whenLoaded('assignee')),  // absent si pas de with('assignee')
'links' => ['self' => route('api.v1.projects.show', $this->resource)],
```
Une Resource ne déclenche **jamais** de requête elle-même : `whenLoaded`/`whenCounted`
rendent visible, au niveau du contrôleur, la nécessité d'un `with()`/`withCount()`
explicite — la Resource se contente d'omettre la clé si la relation n'a pas été chargée,
plutôt que de la charger en douce (qui recréerait un N+1, cette fois caché dans la
couche de sérialisation plutôt que dans le contrôleur).
Doc : https://laravel.com/docs/12.x/eloquent-resources#conditional-relationships

Pagination : `ProjectResource::collection($query->paginate())` sur un `LengthAwarePaginator`
produit automatiquement `data`/`links`/`meta` — vérifié dans une vraie réponse HTTP, avec
les libellés de pagination déjà traduits en français depuis le Module 5 (« Précédent »,
« Suivant »), sans rien reconfigurer pour l'API.

### Piège réel : un bug caché depuis le Module 5, révélé par la Resource

`TaskController::store()` (web **et** API) créait une tâche sans jamais renseigner
`status` explicitement, comptant sur le `DEFAULT 'todo'` de la colonne (Module 3). Ça
fonctionnait en apparence : la ligne SQL avait bien `status = 'todo'`. Mais le **modèle
Eloquent en mémoire**, juste après `create()`, n'a jamais relu cette valeur — `$task->status`
valait `null` jusqu'au prochain rechargement depuis la base. Le contrôleur web ne l'a
jamais remarqué : `response()->json($task, 201)` sérialise un enum casté à `null` en
`"status": null` sans erreur. `TaskResource::toArray()`, elle, fait
`$this->status->value` explicitement — et plante en accédant à une propriété sur `null`.

**La Resource n'a pas introduit le bug, elle l'a rendu visible.** Corrigé en renseignant
`'status' => TaskStatus::Todo->value` explicitement à la création, dans les deux
contrôleurs. Leçon : ne jamais compter sur un défaut posé côté base de données pour une
valeur que le code applicatif lit dans la même requête — soit le relire (`fresh()`), soit
(mieux ici) l'écrire explicitement des deux côtés.

### Filtres et tri : à la main, puis `spatie/laravel-query-builder`

Version manuelle (`?filter[status]=done&filter[tag]=urgent&sort=-due_date`), une
condition par champ :
```php
if ($status = $request->input('filter.status')) { $query->where('status', $status); }
if ($tag = $request->input('filter.tag')) { $query->whereHas('tags', fn ($q) => $q->where('slug', $tag)); }
// ... pareil pour le tri, avec une liste blanche in_array() à la main
```
Douleur ressentie : chaque champ filtrable/triable se copie-colle, et rien n'empêche
d'oublier la liste blanche pour un nouveau champ (donc d'exposer une colonne par erreur).
Remplacé par :
```php
QueryBuilder::for($project->tasks()->with(['assignee', 'tags']))
    ->allowedFilters(['status', AllowedFilter::callback('tag', fn ($q, $v) => $q->whereHas('tags', fn ($q2) => $q2->where('slug', $v)))])
    ->allowedSorts(['due_date', 'priority', 'created_at'])
    ->defaultSort('-created_at')
    ->paginate();
```
**Bénéfice concret, pas seulement moins de lignes** : un tri sur un champ non autorisé
(`?sort=not_a_real_column`) renvoie **400** avec le paquet — la version manuelle
l'ignorait silencieusement (aucune erreur, juste un tri qui ne se produit pas). Vérifié
avec le même client réel avant/après le remplacement : comportement identique sur les cas
valides, rejet explicite sur les cas invalides.
Doc : https://spatie.be/docs/laravel-query-builder

### Abilities de token : la même Policy protège web et API

Une *ability* est attachée au **jeton**, pas à l'utilisateur : `$user->tokenCan('tasks:write')`.
Ajoutée dans `before()` de chaque policy, **avant** le court-circuit Owner — sinon le
bypass Owner rendrait les abilities inutiles pour ce rôle :
```php
public function before(User $user, string $ability, mixed $arg = null): Response|bool|null
{
    $required = in_array($ability, ['view', 'viewAny'], true) ? 'tasks:read' : 'tasks:write';
    if (! $user->tokenCan($required)) {
        return Response::deny("Ce jeton n'a pas la permission « {$required} ».");
    }
    // ... logique de rôle existante, inchangée
}
```
Point clé de Sanctum : `tokenCan()` renvoie **toujours** `true` pour une authentification
par session (web) — les abilities ne concernent que les vrais jetons, aucun changement de
comportement pour l'interface web. Vérifié avec un jeton créé volontairement en lecture
seule (`projects:read` uniquement) : `POST /api/v1/projects` → **403**, message français,
alors que le même compte via la session web peut créer un projet sans restriction.

Gestion des jetons : page profil (`profile/partials/api-tokens.blade.php`), jeton en
clair affiché **une seule fois** à la création (Sanctum ne stocke qu'un hash — impossible
de le retrouver après coup, comme un mot de passe).

### Rate limiting par jeton, pas par utilisateur

```php
RateLimiter::for('api', function (Request $request) {
    $user = $request->user();
    $key = $user !== null ? $user->currentAccessToken()->id : $request->ip();
    return Limit::perMinute(60)->by((string) $key);
});
```
Par **jeton** plutôt que par utilisateur : un script d'import et une appli mobile du même
compte ne doivent pas se gêner, chacun a son propre compteur — sinon un client mal
codé qui boucle épuiserait aussi le quota de l'autre.
Doc : https://laravel.com/docs/12.x/routing#rate-limiting

### Format d'erreur homogène — un vrai problème trouvé en testant

Une 422 (validation) rendait déjà proprement `{"message": "...", "errors": {...}}` par
défaut. Mais une **404** réelle (`GET /api/v1/projects/n-existe-pas`) renvoyait la trace
de débogage complète — chemins de fichiers du serveur, stack trace entière — parce que
`APP_DEBUG=true` en local. Incohérent avec la 422, et une vraie fuite d'information si ça
partait tel quel en production avec le debug activé par erreur. Corrigé dans
`bootstrap/app.php` via `$exceptions->render()`, scopé à `/api/*` uniquement (les pages
web gardent leur page de debug habituelle) :
```php
$exceptions->render(function (Throwable $e, Request $request) {
    if (! $request->is('api/*')) return null;
    $status = match (true) { /* Authentication->401, Authorization->403, NotFound->404, Validation->422, ... */ };
    $message = $status < 500 || config('app.debug') ? $e->getMessage() : 'Une erreur interne est survenue.';
    return response()->json(['message' => $message, ...], $status);
});
```
**Vérifié pour les quatre cas** avec de vraies requêtes : 401 (pas de jeton), 403 (jeton
sans l'ability), 404 (ressource absente), 422 (validation) — même forme `{"message": ...}`
partout, jamais de trace de débogage sur `/api/*`.

### Tests d'API avec `assertJsonStructure`

`tests/Feature/Api/{Project,Task,Comment}ApiTest.php`, `Sanctum::actingAs($user, $abilities)`
(pas de vrai jeton à générer en test — Sanctum fournit ce helper spécifiquement pour ça).
Cas couverts : isolation par équipe, validation, création réussie avec structure JSON
exacte, refus d'un jeton en lecture seule, refus d'un rôle Invité, suppression réservée à
Owner, filtre/tri (dont le rejet d'un champ non autorisé). 15 tests, tous verts.

### Validation du module

Parcours complet mené **entièrement en HTTP brut** (`Invoke-RestMethod` + jeton Bearer,
sans navigateur ni interface web) : créer un projet, créer une tâche, poster un
commentaire, changer le statut, filtrer par statut, supprimer la tâche puis le projet —
chaque étape vérifiée avec la vraie réponse JSON avant de passer à la suivante.
`php artisan test` : 40 tests verts, `pint`/`phpstan` verts.

---

## Module 10 — Tests

### Couverture de code : sciemment mise de côté

Ni Xdebug ni PCOV (les deux seules extensions capables de mesurer la couverture) ne sont
installés sur cette machine — contrairement à `gd`/`zip`/`intervention-image`, ce ne sont
pas des extensions déjà présentes mais désactivées : il aurait fallu télécharger un
binaire externe. Décision : écrire une suite large et rigoureuse (le vrai objectif du
module) sans le pourcentage chiffré, documenté comme limite d'environnement plutôt que
comme raccourci silencieux.

### Pest installé, coexiste avec les tests PHPUnit existants

`composer require pestphp/pest pestphp/pest-plugin-laravel --dev` puis
`./vendor/bin/pest --init` (pas `php artisan pest:install`, qui n'existe pas — c'est
`pest --init` qui scaffold `tests/Pest.php`). Les tests Breeze/API des Modules 6-9,
écrits en classes PHPUnit (`class XTest extends TestCase`), continuent de tourner
**sans modification** aux côtés des nouveaux tests Pest (fonctions `it()`/`test()`) —
Pest exécute nativement les deux styles dans la même suite. Choix assumé : ne pas
convertir l'existant (travail mécanique à faible valeur), mais écrire tout le nouveau
code de ce module en Pest, conformément à la convention du projet (`CLAUDE.md`).
Doc : https://pestphp.com/docs/underlying-test-case

`tests/Pest.php` centralise `RefreshDatabase` pour tous les tests Feature/Unit, et une
fonction utilitaire globale `memberOf(Team $team, TeamRole $role)` — répétée dans
suffisamment de fichiers pour justifier une factorisation à un seul endroit.

### Deux vrais bugs trouvés en écrivant les tests, aucun des deux dans le code testé en premier lieu

**1. Notification envoyée au mauvais assigné après une réassignation.** `TaskObserver::
notifyAssignee()` faisait `$task->assignee?->notify(...)`. Un test avec
`Notification::fake()` — créer une tâche assignée à Alice, la réassigner à Bob, vérifier
que **Bob** est notifié — échouait : la notification ne partait ni vers Bob, ni (comme on
aurait pu le croire) vers personne, mais restait accrochée à Alice. Cause : `created()`
accède déjà à `$task->assignee` (pour notifier Alice à la création), ce qui **met la
relation Eloquent en cache** sur cette instance. `update(['assignee_id' => $bob->id])`
change l'attribut, mais ne rafraîchit jamais une relation déjà chargée — `$task->assignee`
renvoie ensuite Alice indéfiniment sur cette même instance. Corrigé en interrogeant
`User::find($task->assignee_id)` directement dans l'Observer, qui contourne le cache de
relation. Leçon générale : une relation BelongsTo lue une fois reste figée sur cette
instance même après un changement de la clé étrangère sous-jacente — vrai dès qu'un
modèle est créé puis modifié dans le **même** cycle de requête.

**2. Toute l'interface web bloquée depuis le Module 9, jamais remarqué.** `ProjectPolicy`/
`TaskPolicy::before()` vérifiaient `$user->tokenCan($required)` en supposant (documenté à
tort dans le Module 9) que cette méthode renvoie toujours `true` pour une session web.
Faux : cette garantie ne vaut que si l'authentification passe par le guard `sanctum`
lui-même (mode SPA à cookie, avec un `TransientToken`). Nos routes web utilisent le guard
`web` classique — `currentAccessToken()` y est toujours `null`, donc `tokenCan()` valait
toujours `false`, et **before() refusait chaque page** depuis son introduction. Invisible
jusqu'ici car toute la vérification du Module 9 passait par l'API (jetons réels ou
`Sanctum::actingAs()`), jamais par une vraie session web — le premier test Pest écrit
avec `actingAs()` (pas `Sanctum::actingAs()`) l'a révélé immédiatement. Corrigé en ne
vérifiant l'ability que si un jeton est réellement en jeu (`$user->currentAccessToken()`)
— avec un piège Larastan au passage : cette méthode Sanctum est documentée
`@var TToken` (sans `|null`), donc jamais nulle aux yeux de l'analyse statique, qui
déclarait mort tout contrôle de nullité direct alors que le bug prouvait le contraire.
Résolu avec un passage par un paramètre `mixed` (`ChecksTokenAbility::isNotNull()`) —
pas un contournement : `mixed` est le type honnête de cette valeur, c'est le docblock de
Sanctum qui se trompe.

**Leçon commune aux deux** : ce sont exactement les régressions que Module 10 existe
pour attraper — invisibles en lisant le code, invisibles même en le testant *seulement*
via l'API ou seulement à la création, révélées uniquement par un test qui rejoue le
scénario complet (créer **puis** modifier ; session web **et** jeton API).

### Un vrai gap de validation trouvé via un dataset Pest

```php
it('accepts every valid priority value', function (string $priority) { ... })
    ->with(['low', 'normal', 'high']);
```
En écrivant un test symétrique (« refuse d'assigner quelqu'un d'une autre équipe »),
découverte que rien ne l'empêchait : `assignee_id` n'était validé qu'avec
`exists:users,id` (n'importe quel utilisateur de la base). Corrigé avec
`Rule::exists('team_user', 'user_id')->where('team_id', $project->team_id)` dans
`StoreTaskRequest` **et** `UpdateTaskRequest`.

### Mocking : `Storage::fake()`, `Mail::fake()`, `Queue::fake()`, `Bus::fake()`

Chacun isole une couche précise, jamais la logique métier :
- `Storage::fake()` : upload réel vers un disque en mémoire, `Storage::assertExists()`/
  `assertMissing()` — aucun fichier n'atteint jamais le vrai disque en test.
- `Queue::fake()` + `Queue::assertPushed(GenerateThumbnail::class, ...)` : vérifie qu'un
  job **a été déposé** avec les bons arguments, sans jamais l'exécuter.
- `Bus::fake()` + `Bus::assertBatched(fn ($batch) => ...)` : même chose pour un
  `Bus::batch()` entier — nombre de jobs, sans lancer l'import CSV pour de vrai.
- `Mail::fake()` + `Mail::assertQueued(TeamInvitationMail::class, ...)` — **pas**
  `assertSent()` : un Mailable `ShouldQueue` envoyé via `->send()` est quand même
  comptabilisé comme *queued* par le fake, pas *sent*. Message d'erreur de Laravel
  explicite (« Did you mean assertQueued()? ») — suivi plutôt que deviné.
- `Notification::fake()` + `assertSentTo($user, ...)` — c'est ce mécanisme qui a révélé
  le bug de relation en cache ci-dessus.

### Voyage dans le temps : `travel()`, `travelBack()`, `freezeTime()`

`taskflow:cleanup-archived` : `$this->travel(-120)->days()` avant d'archiver un projet
(le hook `Project::booted()` enregistre alors un `archived_at` vieux de 120 jours pour de
vrai, sans forcer la colonne à la main), `travelBack()`, puis la commande. Pour
`taskflow:send-weekly-report`, `$this->freezeTime()` fige « cette semaine » pendant tout
le test — sans ça, une exécution à cheval sur minuit dimanche/lundi rendrait le calcul
des tâches « terminées cette semaine » occasionnellement faux.
Doc : https://laravel.com/docs/12.x/mocking#interacting-with-time

### CSRF : pourquoi ce n'est **pas** testable avec le client de test standard

Tentative initiale d'un test « POST sans jeton CSRF → 419 » : échec structurel, pas de
code. `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::handle()` contient
littéralement `$this->runningUnitTests() ||` dans sa condition de passage — Laravel
désactive volontairement cette vérification pendant les tests, pour ne pas obliger
chaque test HTTP à gérer un jeton CSRF. Déjà vérifié en HTTP brut (Module 9, `POST
/projects` sans jeton → **419** réel). Le XSS, en revanche, se teste normalement : une
Resource ou une vue ne sait pas qu'elle est appelée depuis un test.

### CI — GitHub Actions

`.github/workflows/ci.yml` : Pint → Larastan → Pest à chaque push, sur `ubuntu-latest`,
PHP 8.2 (mêmes extensions que le Module 8 : `gd` pour les miniatures). Base SQLite en
mémoire (déjà la config de `phpunit.xml`) : aucun service MySQL nécessaire, les tests
tournent identiquement en local et en CI.

### Validation du module

`php artisan test` : 86 tests verts (0 avant le Module 10 hors Breeze/API : 40 → 86,
soit 46 tests écrits ce module-ci), `pint`/`phpstan` verts. Deux bugs réels trouvés et
corrigés en écrivant ces tests (notification mal adressée, policies web bloquantes
depuis le Module 9), plus un vrai gap de validation (assignation hors équipe). CI
GitHub Actions en place — vérifiée pour de vrai après le push (pas seulement écrite).

---

### Breeze : ce qu'il génère, et le piège de l'installation sur un projet existant

`composer require laravel/breeze --dev` puis `php artisan breeze:install blade` génèrent :
contrôleurs `app/Http/Controllers/Auth/*` (inscription, connexion, vérification e-mail,
reset password, confirmation de mot de passe), leurs Form Requests, les vues
`resources/views/auth/*`, une vue profil, et `routes/auth.php`.

**Piège réel, découvert avant même de lire une ligne de code métier** : `breeze:install`
**écrase sans prévenir** des fichiers déjà présents. Sur ce projet, trois collisions :
- `routes/web.php` remplacé intégralement — toutes les routes `projects`/`tasks`/`dashboard`
  du Module 1 disparues.
- `resources/views/dashboard.blade.php` remplacé par un placeholder `<x-app-layout>` qui
  n'a plus rien à voir avec le vrai tableau de bord du Module 4.
- Le stack Blade de Breeze 2.4 date d'avant Tailwind 4 : il régénère `tailwind.config.js`
  et `postcss.config.js` (config JS, syntaxe v3) et réécrit `resources/css/app.css` avec
  `@tailwind base/components/utilities` au lieu de `@import 'tailwindcss'` — en retirant au
  passage le plugin Vite `@tailwindcss/vite`.

Réaction : **ne rien corriger en silence**. `git diff` sur chaque fichier écrasé pour voir
exactement ce qui a changé, puis fusion manuelle — reprendre nos fichiers existants et n'y
intégrer que l'apport réel de Breeze (`require __DIR__.'/auth.php'`, routes de profil,
dépendances npm utiles comme `@tailwindcss/forms`). Le composant `<x-modal>` du Module 2
a aussi été remplacé par une version Breeze plus complète (pilotée par événements
`$dispatch('open-modal', 'nom')` plutôt que par un slot `trigger`) : gardée, car réellement
meilleure (piégeage du focus, `Échap`, transitions), avec `projects/index.blade.php` adapté
à la nouvelle API plutôt que l'inverse.

### Guards et providers

`config/auth.php` : un **guard** définit *comment* on vérifie l'identité (`web` → session),
un **provider** définit *où* trouver l'utilisateur (`users` → Eloquent, modèle `User`).
Sanctum (Module 9) ajoutera un second guard (`sanctum`, token) sans toucher au premier —
c'est cette séparation qui permet à la même application de servir des pages web
authentifiées par session et une API authentifiée par token.
Doc : https://laravel.com/docs/12.x/authentication#introduction

### `CurrentTeam` enfin peuplé — et un vrai bug de double exécution

Le contexte `CurrentTeam` (singleton, Module 4) était vide jusqu'ici. Peuplé maintenant par
un middleware dédié :

```php
class SetCurrentTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($user = $request->user()) !== null) {
            app(CurrentTeam::class)->set($user->teams()->first());
        }
        return $next($request);
    }
}
```

appliqué avec `auth`/`verified` sur toutes les routes de l'application (`routes/web.php`).
Un utilisateur peut appartenir à plusieurs équipes (table pivot `team_user`) : en
attendant un vrai sélecteur d'équipe dans l'interface, on retient la première — limitation
assumée et documentée dans le code, pas cachée.

Comme un projet ne peut pas exister sans équipe, chaque inscription crée une équipe
personnelle via un listener sur l'événement `Registered` :

```php
class CreatePersonalTeam
{
    public function handle(Registered $event): void
    {
        $team = Team::create(['name' => "Équipe de {$event->user->name}", 'slug' => ...]);
        $team->users()->attach($event->user, ['role' => TeamRole::Owner->value]);
    }
}
```

**Piège réel** : ce listener s'exécutait **deux fois par inscription**, provoquant une
violation de contrainte d'unicité sur le slug (`Duplicate entry 'bob-nouveau-7'`). Cause :
Laravel 12 **découvre automatiquement** les listeners du dossier `app/Listeners` par
convention (le type du paramètre de `handle()` suffit) — l'enregistrer *en plus* à la main
via `Event::listen()` dans `AppServiceProvider::boot()` le faisait tourner deux fois pour
le même événement. Contrairement aux Observers de modèles (toujours enregistrés à la main,
`Task::observe(...)`), les listeners d'événements n'ont pas besoin de l'être. Trouvé en
testant une vraie inscription dans un navigateur, pas en relisant le code.

### Policies : `Gate`, `authorize()`, `before()`

Une policy par modèle sensible (`ProjectPolicy`, `TaskPolicy`), auto-découvertes par
Laravel (convention `App\Models\Foo` → `App\Policies\FooPolicy`, aucun enregistrement
requis). Le rôle réel de l'utilisateur est **toujours relu depuis la base**
(`User::roleIn(Team $team)`, table pivot `team_user`), jamais fait confiance depuis une
requête. Doc : https://laravel.com/docs/12.x/authorization#creating-policies

`before()` court-circuite les autres méthodes pour un cas transverse — ici, le ou la
Owner d'une équipe peut toujours tout faire sur les ressources de cette équipe :

```php
public function before(User $user, string $ability, mixed $arg = null): ?bool
{
    if ($arg instanceof Project && $user->roleIn($arg->team) === TeamRole::Owner) {
        return true; // court-circuite view/update/delete
    }
    return null; // laisse les autres méthodes décider (viewAny/create : $arg est une string)
}
```
Point d'attention réel : `$arg` n'est **pas toujours une instance du modèle**. Pour
`viewAny`/`create` (pas encore d'instance), Laravel passe le **nom de classe**
(`Project::class`, une string) — `before()` doit gérer les deux cas (`instanceof`), sinon
`TypeError` sur un paramètre trop strictement typé.

Chaque policy retourne `Illuminate\Auth\Access\Response::deny('message en français')`
plutôt qu'un simple `false` : un `false` nu produit le message générique anglais de
Laravel (« This action is unauthorized. »), visible sur la page d'erreur 403 — repéré en
testant un accès refusé dans un vrai navigateur, incohérent avec une application entièrement
en français.

Contrôleurs : `$this->authorize('view', $project)` (nécessite le trait
`AuthorizesRequests` sur le contrôleur de base — absent du squelette Laravel 12 minimal,
ajouté à `app/Http/Controllers/Controller.php`). Pour une action **sans instance**
(créer une tâche), l'équipe ne peut pas se déduire d'un objet qui n'existe pas encore : le
projet parent est passé explicitement, `$this->authorize('create', [Task::class, $project])`.

### Fuite de données inter-équipes trouvée en testant avec une deuxième équipe réelle

Jusqu'ici, une seule équipe existait jamais en pratique (le seed du Module 3), donc
certains bugs de filtrage étaient invisibles. Premier test avec un vrai deuxième compte
(inscription libre, sa propre équipe personnelle) : son tableau de bord affichait les
« Top contributeurs » et l'« Activité récente » de l'équipe d'Alice, pas les siens.

Cause : `Project` a le scope global `TeamScope` (Module 4), donc `Project::with(...)->get()`
dans `DashboardController` était déjà correctement filtré. Mais `Activity` (pas de
colonne `team_id`) et la requête `User::withCount(...)` (aucun filtre du tout) ne
l'étaient pas — invisible avec une seule équipe en base, car « toutes les équipes » et
« mon équipe » désignaient alors le même ensemble de lignes. Corrigé en filtrant
explicitement par l'équipe courante :

```php
Activity::whereHasMorph('subject', [Task::class], fn ($q) =>
    $q->whereHas('project', fn ($q2) => $q2->where('team_id', $teamId))
)
User::whereHas('teams', fn ($q) => $q->where('teams.id', $teamId))->withCount([...])
```

Leçon générale : un scope global sur *un* modèle ne protège pas les requêtes qui
interrogent *d'autres* modèles, même liés. Chaque requête d'un contrôleur doit être
vérifiée individuellement, pas supposée protégée « parce qu'il y a un scope quelque part ».

### Invitations par URL signée

`URL::temporarySignedRoute()` génère un lien à durée de vie limitée dont la signature
couvre tous les paramètres de la requête (équipe, e-mail, rôle) :

```php
$signedUrl = URL::temporarySignedRoute('teams.invitations.accept', now()->addDays(7), [
    'team' => $team->id, 'email' => $email, 'role' => $role,
]);
```

Le middleware `signed` sur la route rejette (403, « Invalid signature ») toute URL dont un
seul de ces paramètres a été modifié après génération — **vérifié réellement** en
modifiant `role=member` en `role=owner` dans un lien avant de cliquer dessus : rejeté.
Ce que `signed` ne vérifie **pas** : que la personne qui clique est bien la destinataire.
Ajouté à la main dans le contrôleur : `abort_unless($request->user()->email === ..., 403, ...)`.
Doc : https://laravel.com/docs/12.x/urls#signed-urls

`TeamMemberController::store()` distingue les deux cas : e-mail déjà lié à un compte →
ajout immédiat à la table pivot ; e-mail inconnu → e-mail d'invitation avec ce lien signé.
`Illuminate\Contracts\Auth\MustVerifyEmail`, l'envoi passe par un `Mailable` volontairement
minimal (`TeamInvitationMail`, vue Blade simple, ni Markdown ni `ShouldQueue`) — signalé
explicitement : le Module 7 (e-mails et notifications) le reprendra pour le mettre en
file d'attente et le passer au format Markdown ; ce n'est pas encore le sujet ici.

### Rate limiting sur la connexion : déjà fourni par Breeze, pas réinventé

`App\Http\Requests\Auth\LoginRequest::ensureIsNotRateLimited()` (généré par Breeze)
limite déjà les tentatives à 5 par couple e-mail+IP (`RateLimiter::tooManyAttempts`),
avec verrouillage progressif et message traduit (`lang/fr/auth.php['throttle']`). Choix
assumé : ne **pas** ajouter de mécanisme personnalisé par-dessus — un second système de
rate limiting ferait double emploi sans bénéfice réel, et irait à l'encontre de la
consigne « lire le code généré avant d'écrire le sien ». Vérifié avec 6 échecs de connexion
réels d'affilée sur le même compte :
```
tentative 6 -> "Trop de tentatives de connexion. Merci de réessayer dans 50 secondes."
```

### Sécurité : ce qui protège déjà, vérifié pour de vrai

| Protection | Où | Vérifié comment |
|---|---|---|
| CSRF | Middleware `web` (par défaut sur toutes les routes de `routes/web.php`) | `POST /projects` sans jeton `_token` → **419** |
| XSS | Échappement Blade `{{ }}` (jamais `{!! !!}` sur du contenu utilisateur) | Nom de projet `<script>window.__xss=true</script>` créé puis affiché : rendu en texte littéral (`&lt;script&gt;...`), rien exécuté |
| Mass assignment | `$fillable` explicite sur chaque modèle (jamais `$guarded = []`) | Depuis le Module 3 : `team_id` sur `Project` n'est mass-assignable que parce qu'il est listé, et vient toujours du serveur (`CurrentTeam`), jamais du formulaire |
| Verrouillage après échecs | `RateLimiter` de Breeze (voir ci-dessus) | 6 tentatives → blocage temporaire |

### Vérification de bout en bout (navigateur réel, pas seulement `php artisan test`)

Inscription → e-mail de vérification (driver `log`, lien signé extrait du log) → clic →
tableau de bord ; déconnexion → `/projects` redirige vers `/login` ; deux comptes de deux
équipes différentes ne voient jamais les projets/activités l'un de l'autre ; un compte
hors équipe visitant l'URL d'un projet d'une autre équipe reçoit un **403** en français ;
invitation d'un compte existant → ajout immédat (201) ; invitation d'une adresse inconnue →
e-mail avec lien signé (202) puis rattachement effectif au clic. `php artisan test` reste
vert (25 tests, dont les 15 générés par Breeze, tous lus avant d'être acceptés tels quels).

---

## Module 11 — Architecture et code propre

### Action classes : un cas d'usage, une classe

`CreateTaskAction` (`app/Actions/Tasks/CreateTaskAction.php`) sort de `TaskController::store()`
tout ce qui n'est pas « recevoir → déléguer → répondre ». Une seule méthode publique
(`handle()`), instanciée automatiquement en paramètre de méthode de contrôleur (injection
de méthode, résolue par le conteneur de service). Le DTO `CreateTaskData` (Module 5)
reste séparé : il ne transporte que des données validées, l'Action ne transporte que du
comportement — chaque classe une seule responsabilité.
Doc (injection automatique) : https://laravel.com/docs/12.x/container#automatic-injection

**Pourquoi une Action plutôt qu'un `TaskService` avec plusieurs méthodes ?** Un service
généraliste (`create`, `update`, `move`, `assign`…) redevient vite un God Object flou.
Une classe par cas d'usage force un nom précis, isole les dépendances, et se teste sans
policy ni HTTP — vérifié directement en Tinker :
```php
(new \App\Actions\Tasks\CreateTaskAction())->handle($project, $data); // crée une tâche, sans route ni contrôleur
```

Bénéfice concret trouvé en pratiquant : le contrôleur **API** (`Api\V1\TaskController::store`)
dupliquait exactement la même logique de création que le contrôleur web avant
l'extraction. Les deux utilisent maintenant la même `CreateTaskAction` — un seul endroit
où « comment créer une tâche » peut avoir un bug, pas deux.

### Query objects : centraliser la construction d'une requête réutilisée

`TaskQuery::for($project)` (`app/Queries/TaskQuery.php`) remplace `$project->tasks` (web,
sans eager loading) et `$project->tasks()->with(['assignee', 'tags'])` (API, dupliqué dans
le contrôleur). Avant cette classe, seul le contrôleur API chargeait ces relations ; le
contrôleur web ne les chargeait jamais. Les deux consommateurs partagent maintenant la même
base — un futur troisième endroit qui liste des tâches ne peut plus oublier ce `with()` et
recréer un N+1 caché.
```php
QueryBuilder::for(TaskQuery::for($project))->allowedFilters([...])->allowedSorts([...])
```
`spatie/laravel-query-builder` (Module 9) continue de gérer le filtrage/tri piloté par
l'URL — `TaskQuery` ne fait pas double emploi avec lui, il centralise seulement ce qui était
dupliqué : *quelle* requête de base interroger, pas *comment* la piloter depuis la requête HTTP.

Piège réel rencontré : `TaskQuery::for()` typé `: Builder` faisait échouer Larastan —
`$project->tasks()` renvoie `HasMany<Task, Project>`, pas un `Builder` générique (une
relation Eloquent *décore* un query builder, elle n'en est pas un au sens du type). Corrigé
en typant le retour `HasMany<Task, Project>`, vérifié avec `$query->toSql()` et
`$query->getEagerLoads()` en Tinker.

### MoveTaskAction : une Action peut justifier sa propre route

`update()` (générique, tous champs) existait déjà ; « déplacer une tâche » n'avait pas
d'équivalent avant ce module. Décision de conception (pas une connaissance Laravel) :
ajouter une route dédiée `PATCH /projects/{project}/tasks/{task}/move`, plus étroite qu'un
`update()` qui accepterait n'importe quel champ — c'est exactement l'interaction qu'un
tableau kanban à glisser-déposer (Module 13) déclenchera. `MoveTaskRequest` valide
uniquement `status` (`Rule::enum(TaskStatus::class)`) ; l'autorisation reste
`TaskPolicy::update` (déplacer est un cas particulier de modifier, pas une règle
différente). Toute la logique métier du changement de statut (`completed_at`, événement
`TaskMoved`) vit déjà dans `TaskObserver` (Module 8) et se déclenche normalement puisque
`MoveTaskAction::handle()` passe par `Task::update()` — rien à dupliquer.

Piège de routing : les routes hors `Route::resource(...)->scoped()` (comme les routes
d'attachments, Module 7) ne vérifient pas automatiquement que `{task}` appartient au
`{project}` de l'URL — `->scoped()` est un raccourci spécifique aux resources. Pour la
route `move`, ajout explicite de `->scopeBindings()`, l'équivalent générique pour une route
non-resource. Vérifié avec une vraie requête HTTP (PowerShell, session + jeton CSRF réels) :
`PATCH /projects/projet-actif-demo/tasks/33/move {"status":"in_progress"}` → tâche
effectivement déplacée, confirmé en relisant la ligne en base.

### Interface + deux implémentations : quand ça vaut le coût, quand c'est du cargo cult

`App\Contracts\AttachmentStorage` (`store`, `delete`, `download`) découple
`AttachmentController` du mécanisme de stockage réel. Point important, assumé
explicitement : Laravel **résout déjà** le problème « changer de disque sans changer de
code » via `config/filesystems.php` + `FILESYSTEM_DISK` — ajouter cette interface pour
« pouvoir passer de local à s3 » aurait été le repository cargo cult que ce module met en
garde contre. Le bénéfice réel visé ici est différent : substituer l'implémentation
**entière** via le conteneur, pour des tests qui ne touchent aucun disque, ni réel ni faux.

- `DiskAttachmentStorage` : implémentation par défaut, délègue à la façade `Storage`
  (donc `Storage::fake()`, Module 10, continue de fonctionner de façon transparente —
  vérifié en relançant `AttachmentTest.php` sans le modifier, toujours vert).
- `InMemoryAttachmentStorage` : implémentation de test, garde les fichiers dans un
  tableau PHP privé, n'écrit jamais sur un filesystem, réel ou temporaire.

Bindées dans `AppServiceProvider::register()` :
```php
$this->app->bind(AttachmentStorage::class, DiskAttachmentStorage::class);
```
puis substituée dans un test avec `$this->app->singleton(AttachmentStorage::class, InMemoryAttachmentStorage::class)`
(`AttachmentStorageSwapTest.php`) — **`singleton`, pas `bind`** : avec `bind`, chaque
résolution recrée une nouvelle instance avec un tableau `$files` vide, donc l'instance
utilisée par le contrôleur pendant la requête HTTP ne serait pas celle interrogée ensuite
dans l'assertion du test. Bug trouvé en écrivant le test lui-même, avant même de le lancer.

### Validation du module

`TaskController` et `AttachmentController` : chaque méthode tient sous 10 lignes de code
(hors commentaires). `php artisan test` : 93 tests verts (dont 7 nouveaux pour ce module :
`MoveTaskTest`, `AttachmentStorageSwapTest`), `pint`/`phpstan` verts.

### Exercices de consolidation

*(Regroupés ici plutôt que posés en cours de séance, à la demande de l'apprenant — 2026-08-24.)*

- **CreateTaskAction vs CreateTaskData** — pourquoi ces deux classes restent séparées
  plutôt que fusionnées en une seule. *Répondu correctement dans la séance : le DTO ne
  transporte que des données, l'Action ne transporte que du comportement de création —
  chaque classe une seule responsabilité.*
- **AttachmentStorage** — `Storage::fake()` donne déjà un disque factice pour les tests
  (Module 10). Qu'est-ce que l'interface `AttachmentStorage` + `InMemoryAttachmentStorage`
  apporte que `Storage::fake()` ne donnait pas déjà ? (Indice : où vit la substitution —
  au niveau du disque, ou au niveau de la classe qui l'utilise ?)
- **TaskQuery** — le fichier a un seul point d'entrée statique (`TaskQuery::for()`)
  plutôt qu'une instance avec des méthodes de filtrage chaînables (`->overdue()`,
  `->assignedTo()`…). Dans quel cas cette classe grossirait au point de justifier de
  vraies méthodes d'instance plutôt qu'un simple point d'entrée statique ?

---

## Module 12 — Performance et montée en charge

### Environnement local : Redis et Meilisearch via WSL

Ni Redis ni Meilisearch n'étaient disponibles nativement sur cette machine Windows
(Herd, dans sa forme actuelle, ne les fournit pas). Les deux tournent dans WSL (Ubuntu),
avec le transfert automatique `localhost` de WSL2 vers Windows — `redis-cli`/`meilisearch`
écoutent sur `127.0.0.1` côté WSL, directement joignables depuis PHP côté Windows sans
configuration réseau supplémentaire. Piège rencontré : WSL arrête sa VM légère (et donc
tous les services qui y tournent) dès qu'aucune commande n'y est active — une commande
`tail -f /dev/null` maintenue en arrière-plan garde la session (et Redis/Meilisearch)
vivante pour la durée du travail. **À redémarrer à chaque nouvelle session de travail**
si Windows ou WSL a redémarré entretemps :
```bash
wsl -d Ubuntu -e bash -c "sudo service redis-server start && sleep 1 && ~/meilisearch --db-path ~/meili-data --http-addr 127.0.0.1:7700"
```
Second piège, plus insidieux : le DNS de WSL (`/etc/resolv.conf`, régénéré à chaque boot)
pointait vers un résolveur d'entreprise injoignable depuis ce conteneur — `apt`/`curl`
échouaient avec des erreurs de résolution DNS. Corrigé durablement via `/etc/wsl.conf`
(`[network]` `generateResolvConf = false`) puis un `/etc/resolv.conf` fixe (`8.8.8.8`),
plutôt qu'à corriger à la main à chaque nouvelle invocation.

### `Model::preventLazyLoading()` : ne protège que les collections

Doc : https://laravel.com/docs/12.x/eloquent-relationships#preventing-lazy-loading
```php
Model::preventLazyLoading(! app()->isProduction());
```
Jamais en production : un N+1 dégrade une réponse, il ne doit jamais la faire planter
chez un vrai utilisateur.

**Piège réel, vérifié par la lecture du code source d'Eloquent** (`Builder::hydrate()`) :
la protection ne s'active sur une instance que si la requête a renvoyé **plus d'un**
modèle (`if (count($items) > 1)`). Un `Task::first()` isolé n'écrira jamais l'exception,
même avec une relation non chargée — seule une vraie collection (`$project->tasks` puis
boucle) le fait, ce qui est cohérent avec l'intention : un N+1 est par définition une
boucle sur plusieurs lignes, pas un accès isolé. Vérifié en Tinker : `Task::first()->assignee`
ne lève rien, `$project->tasks->each(fn ($t) => $t->assignee)` lève
`LazyLoadingViolationException`.

Aucun N+1 réel trouvé dans les vues actuelles (`dashboard.blade.php`, `board.blade.php`) :
déjà eager-chargées depuis les modules précédents (Module 4, Module 9). Figé par un test
qui prouve à la fois que le mécanisme est actif et que les pages réelles ne le déclenchent
jamais — la seule façon de garantir qu'un futur ajout de champ dans la vue ne recrée pas
silencieusement un N+1.

### Cache : `Cache::tags()`, invalidation par événement, pas seulement par TTL

`Cache::tags()` nécessite un store qui le supporte explicitement — jamais `database` ni
`file` (`BadMethodCallException` à l'exécution, pas à la configuration). D'où le passage
de `CACHE_STORE=database` à `redis` (`REDIS_CLIENT=predis`, client pur PHP, pas
d'extension à compiler — plus simple que `phpredis` sur Windows).

`TeamStatsCache::remember(Team $team)` : projets, tâches par statut, tâches terminées ce
mois — un `GROUP BY` en base, jamais un `->get()` de toutes les tâches suivi d'un
`groupBy()` PHP (Module 4 avait fait l'inverse dans `DashboardController`, remplacé ici).
Invalidation par `ProjectObserver`/`TaskObserver` sur les écritures qui changent
réellement ces chiffres (création/suppression de tâche ou de projet, **changement de
statut uniquement** — un changement de titre ou d'assigné ne rend pas ces chiffres
obsolètes, inutile de vider le cache pour ça). Vérifié avec le vrai Redis (pas seulement
le store `array` des tests) : 3 requêtes SQL au premier appel, 0 au second ; invalidation
confirmée en HTTP réel (créer une tâche change `/team/stats` immédiatement, sans attendre
le TTL de 15 minutes).
Doc : https://laravel.com/docs/12.x/cache#cache-tags

### Recherche full-text : à la main, puis Scout + Meilisearch

Recherche nommée explicitement dans `CLAUDE.md` comme un des trois exemples où on code à
la main avant d'adopter un package. Version manuelle (`WHERE title LIKE '%...%'`),
douleur vérifiée par un vrai test : une seule lettre de faute de frappe
(« paginaton » au lieu de « pagination ») ne trouvait rien. Remplacée par
`laravel/scout` + Meilisearch : `Task implements Searchable`, `toSearchableArray()`
n'expose que `id`/`title`/`project_id` (`project_id` seul champ déclaré filtrable dans
`config/scout.php` — jamais indexer un champ qu'on ne recherche jamais). La même faute
de frappe retrouve maintenant la tâche, triée par pertinence.
Doc : https://laravel.com/docs/12.x/scout

**Piège réel, découvert en écrivant les tests** : Meilisearch indexe de façon
asynchrone en interne, même quand Scout appelle son API de façon synchrone —
`addDocuments()` (dans `MeilisearchEngine::update()`) est fire-and-forget, sans
`waitForTask()`. Un test qui crée une tâche puis la cherche immédiatement est en course
avec l'indexation réelle : parfois vert, parfois rouge selon le timing de la machine.
Corrigé par `waitForMeilisearch()` (`tests/Pest.php`), qui interroge l'API des tâches
Meilisearch jusqu'à ce qu'il n'y en ait plus en cours (max 5 s), plutôt qu'un `sleep()`
fixe et fragile.

CI : Meilisearch n'existe pas sur le runner GitHub Actions par défaut — ajout d'un
service dédié (`getmeili/meilisearch` en conteneur, avec healthcheck) et d'une étape
`scout:sync-index-settings` avant les tests (sans elle, `->where('project_id', ...)`
échoue sur un index frais sans attribut filtrable déclaré — `update()` ne pousse jamais
les réglages tout seul).

### Pagination performante : l'exercice à 500 000 tâches, mesuré à chaque étape

Objectif du module : liste paginée sous 200 ms sur un projet de 500 000 tâches
(`taskflow:seed-benchmark`, commande réutilisable — `INSERT` en masse via `DB::table()`,
jamais `Task::factory()->create()` en boucle, qui aurait déclenché 500 000 fois les
observers pour rien).

**Étape 1 — sans index dédié.** Même la page 1 (`OFFSET 0`) coûtait **~2,9 s** : `ORDER BY
created_at` sans index adapté force un tri complet en mémoire (`Using filesort`) sur les
500 000 lignes du projet, avant même de considérer l'`OFFSET`.

**Étape 2 — piège réel, l'index seul ne suffit pas.** Migration ajoutant
`(project_id, created_at)`. Résultat inchangé (~2,2 s), et `EXPLAIN` — même avec
`FORCE INDEX` — continuait d'afficher `Using filesort`. Cause identifiée : le tri
demandé était `ORDER BY created_at DESC, id DESC` (le `id DESC` ajouté comme
« tie-breaker » pour un curseur univoque) — un tri à **deux colonnes**, que MySQL 8.0
refusait de servir depuis l'index même quand il était forcé. Un index qui existe et
correspond en apparence à la requête ne garantit pas que l'optimiseur l'utilise pour le
tri : vérifié, pas supposé.

**Étape 3 — la vraie correction : trier sur la clé primaire.** `defaultSort('-id')`
plutôt que `-created_at` : les `id` croissent dans l'ordre de création, donc « plus
récent d'abord » reste vrai, et un tri sur la clé primaire ne filesort jamais. Mesuré :
**42 ms** (contre 2,2 s). L'index `(project_id, created_at)` reste utile pour un tri
explicite (`?sort=created_at`, plus rare) — conservé, jamais sur le chemin par défaut.

**Étape 4 — `cursorPaginate()` plutôt que `paginate()`.** `OFFSET` reste coûteux quelle
que soit sa taille, indépendamment de tout index : MySQL doit parcourir et jeter les
lignes avant l'offset. Un curseur (`WHERE id < dernier_id_vu`) repart directement du bon
endroit : **2,8 ms** en première page comme à 300 000 lignes de profondeur (même valeur,
mesurée avec un `id` de référence obtenu une seule fois, hors chronométrage — jamais
recalculé à chaque requête, contrairement à ce qu'un curseur naïf pourrait faire).
Contrepartie assumée : plus de « page 42 sur 1000 », seulement précédent/suivant —
`TaskResource::collection()` gère nativement un `CursorPaginator` (`data`/`links`, sans
`meta.total`).
Doc : https://laravel.com/docs/12.x/pagination#cursor-pagination

**Étape 5 — trois niveaux de mesure, jusqu'au vrai constat.** Un test HTTP réel via
`php artisan serve` donnait encore ~1,2 s — écart énorme avec les 42 ms mesurées en SQL
brut. Décomposé : `kernel->handle()` **en process**, sans passer par le réseau, donnait
**264 ms au premier appel** puis **22-25 ms** aux appels suivants dans le même process.
`php artisan serve` (serveur de développement PHP intégré) répète le coût de démarrage
à chaque requête, contrairement à un pool PHP-FPM qui reste chaud entre les requêtes —
**pas représentatif de la production**, un piège de méthodologie de mesure plutôt qu'un
vrai problème de code. Confirmé en désactivant Telescope (aucun effet, hypothèse écartée
avant d'être retenue) puis en mesurant directement dans le process.

### Validation du module

`taskflow:seed-benchmark` génère un jeu de données réel de 500 000 tâches, mesuré à
chaque étape ci-dessus, puis nettoyé (la commande reste, réutilisable). Aucune mesure à
cette échelle n'est rejouée dans la suite de tests — seul le comportement (curseur, tri,
absence de doublon/saut entre pages) l'est, à petite échelle
(`CursorPaginationTest`, `TeamStatsCacheTest`, `PreventLazyLoadingTest`,
`TaskSearchTest`). `php artisan test` : 110 tests verts, `pint`/`phpstan` verts.

---
