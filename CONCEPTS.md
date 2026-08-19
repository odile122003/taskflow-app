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
