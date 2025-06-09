<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="user-type" content="{{ auth()->user()->type }}">
   
    <script>
    // S'assurer que les données d'authentification sont disponibles immédiatement
    document.addEventListener('DOMContentLoaded', function() {
        // Vérifier si les données Laravel sont disponibles
        if (window.Laravel && window.Laravel.user) {
            console.log('Données Laravel disponibles:', window.Laravel.user);
        } else {
            console.warn('Données Laravel non disponibles au chargement du DOM');
        }
    });
    </script>
    <script>
        window.Laravel = {!! json_encode([
            'csrfToken' => csrf_token(),
            'user' => [
                'id' => auth()->id(),
                'type' => auth()->user()->type,
                'name' => auth()->user()->name
            ],
            'appUrl' => config('app.url')
        ]) !!};
    </script>
    @endauth
    @guest
    <script>
        window.Laravel = {!! json_encode([
            'csrfToken' => csrf_token(),
            'user' => null,
            'appUrl' => config('app.url')
        ]) !!};
    </script>
    @endguest
    <title>HeartMatch - Trouvez l'amour</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Scripts and Styles -->
    @routes
    @vite(['resources/css/app.css'])
    @if (Request::is('admin*'))
        @vite(['resources/js/admin.js'])
    @else
        @vite(['resources/js/client.js'])
    @endif
        
    <!-- Stripe.js -->
    <script src="https://js.stripe.com/v3/" defer></script>
    
    @inertiaHead
  </head>
  <body class="font-sans antialiased">
    @inertia
  </body>
</html>