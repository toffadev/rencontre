<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>HeartMatch - Trouvez l'amour</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Scripts and Styles -->
    @routes
    @vite(['resources/css/app.css'])
    @if (Request::is('admin*'))
        @vite(['resources/js/admin.js'])
    @else
        @vite(['resources/js/client.js'])
    @endif
        
    @inertiaHead
  </head>
  <body class="font-sans antialiased">
    @inertia
  </body>
</html>