<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <?php if(auth()->guard()->check()): ?>
    <meta name="user-id" content="<?php echo e(auth()->id()); ?>">
    <meta name="user-type" content="<?php echo e(auth()->user()->type); ?>">

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
        window.Laravel = <?php echo json_encode([
            'csrfToken' => csrf_token(),
            'user' => [
                'id' => auth()->id(),
                'type' => auth()->user()->type,
                'name' => auth()->user()->name
            ],
            'appUrl' => config('app.url')
        ]); ?>;
        
        // Ajouter également les données utilisateur depuis les props Inertia si disponibles
        document.addEventListener('DOMContentLoaded', function() {
            if (window.page && window.page.props && window.page.props.user) {
                if (!window.Laravel) window.Laravel = {};
                window.Laravel.user = window.Laravel.user || window.page.props.user;
                console.log('Données utilisateur synchronisées depuis Inertia:', window.Laravel.user);
            }
        });
    </script>
    <?php endif; ?>
    <?php if(auth()->guard()->guest()): ?>
    <script>
        window.Laravel = <?php echo json_encode([
            'csrfToken' => csrf_token(),
            'user' => null,
            'appUrl' => config('app.url')
        ]); ?>;
    </script>
    <?php endif; ?>
    <title>HeartMatch - Trouvez l'amour</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Scripts and Styles -->
    <?php echo app('Tighten\Ziggy\BladeRouteGenerator')->generate(); ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css']); ?>
    <?php if(Request::is('admin*')): ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/js/admin.js']); ?>
    <?php else: ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/js/client.js']); ?>
    <?php endif; ?>
        
    <!-- Stripe.js -->
    <script src="https://js.stripe.com/v3/" defer></script>
    
    <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
  </head>
  <body class="font-sans antialiased">
    <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
  </body>
</html><?php /**PATH C:\Users\ROYAL COMPUTER\Desktop\projets\laravel\rencontre\resources\views/app.blade.php ENDPATH**/ ?>