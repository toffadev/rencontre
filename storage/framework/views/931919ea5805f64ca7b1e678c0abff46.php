<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages clients en attente</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .btn-hover { transition: all 0.3s ease; transform: translateY(0); }
        .btn-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3); }
        .fade-in { animation: fadeIn 0.8s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 640px) { .container-responsive { margin: 0 16px; } }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="text-center mb-8 fade-in">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-full mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo e(config('app.name')); ?></h1>
        </div>
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden fade-in">
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 px-8 py-6">
                <h2 class="text-2xl font-bold text-white text-center">
                    Messages clients en attente
                </h2>
                <div class="flex items-center justify-center mt-3">
                    <div class="w-2 h-2 bg-yellow-300 rounded-full animate-pulse mr-2"></div>
                    <span class="text-indigo-100 text-sm">Notification importante</span>
                </div>
            </div>
            <div class="px-8 py-8">
                <div class="mb-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">
                        Bonjour <?php echo e($user->name ?? $notifiable->name); ?>,
                    </h3>
                </div>
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-400 rounded-r-lg p-6 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-4">
                            <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="text-gray-700 leading-relaxed">
                                Il y a <span class="font-semibold text-indigo-600"><?php echo e($pendingMessagesCount); ?></span> messages clients qui attendent une réponse.<br>
                                Votre intervention est nécessaire pour maintenir une bonne expérience utilisateur.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="text-center mb-8">
                    <div class="inline-flex items-center bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-full px-6 py-3">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        <span class="text-green-800 font-medium">
                            Merci de votre réactivité !
                        </span>
                    </div>
                </div>
                <div class="text-center mb-8">
                    <a href="<?php echo e($url); ?>" class="btn-hover inline-flex items-center px-8 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-full text-lg shadow-lg hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-indigo-300">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        Répondre maintenant
                    </a>
                </div>
                <div class="border-t border-gray-200 my-8"></div>
                <div class="text-center">
                    <p class="text-gray-600 mb-4">
                        Si vous avez des questions, n'hésitez pas à nous contacter.
                    </p>
                    <div class="flex justify-center space-x-6 text-sm">
                        <a href="#" class="text-indigo-600 hover:text-indigo-800 transition-colors duration-200 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25A9.75 9.75 0 002.25 12c0 5.384 4.365 9.75 9.75 9.75s9.75-4.366 9.75-9.75S17.634 2.25 12 2.25zM12 6v6l4 2"></path>
                            </svg>
                            Centre d'aide
                        </a>
                        <a href="#" class="text-indigo-600 hover:text-indigo-800 transition-colors duration-200 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Contact
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center mt-8 fade-in">
            <p class="text-gray-600 mb-2">
                Merci,<br>
                <span class="font-semibold text-indigo-600">L'équipe <?php echo e(config('app.name')); ?></span>
            </p>
            <div class="flex justify-center space-x-4 mt-4">
                <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center hover:bg-indigo-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                    </svg>
                </div>
                <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center hover:bg-indigo-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.521 8.521 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-4">
                Cet email a été envoyé automatiquement. Veuillez ne pas répondre à cette adresse.
            </p>
        </div>
    </div>
</body>
</html> <?php /**PATH C:\Users\ROYAL COMPUTER\Desktop\projets\laravel\rencontre\resources\views/emails/pending-message.blade.php ENDPATH**/ ?>