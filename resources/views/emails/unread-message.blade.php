@component('mail::message')
# Nouveau message de {{ $profile->name }}

Bonjour {{ $user->name }},

{{ $profile->name }} vous a envoyé un message il y a 30 minutes.

Ne manquez pas cette opportunité de continuer votre conversation !

@component('mail::button', ['url' => $url])
Voir le message
@endcomponent

Si vous avez des questions, n'hésitez pas à nous contacter.

Merci,<br>
L'équipe {{ config('app.name') }}
@endcomponent