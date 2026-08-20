<x-mail::message>
# Rapport hebdomadaire — {{ $team->name }}

Voici le résumé de la semaine pour votre équipe.

<x-mail::table>
| Indicateur | Valeur |
| :--- | ---: |
| Tâches terminées cette semaine | {{ $stats['completed'] }} |
| Projets actifs | {{ $stats['active_projects'] }} |
| Meilleur contributeur | {{ $stats['top_contributor'] ?? 'Aucun' }} |
</x-mail::table>

<x-mail::button :url="route('dashboard')">
Voir le tableau de bord
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
