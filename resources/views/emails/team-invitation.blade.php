<x-mail::message>
# Invitation à rejoindre {{ $team->name }}

Vous avez été invité(e) à rejoindre l'équipe **{{ $team->name }}** sur TaskFlow.

<x-mail::button :url="$signedUrl">
Accepter l'invitation
</x-mail::button>

Ce lien est valable 7 jours. Si vous n'avez pas encore de compte, connectez-vous ou
inscrivez-vous avec cette même adresse e-mail, puis cliquez de nouveau sur ce lien.

Si vous ne connaissez pas cette équipe, vous pouvez ignorer cet e-mail sans risque.

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
