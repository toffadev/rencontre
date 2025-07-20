<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vous nous manquez, <?php echo e($user->name); ?> !</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        /* Animation pour le bouton */
        .btn-hover {
            transition: all 0.3s ease;
            transform: translateY(0);
        }
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(236, 72, 153, 0.4);
        }
        
        /* Animation pour les éléments */
        .fade-in {
            animation: fadeIn 0.8s ease-in-out;
        }
        
        .slide-up {
            animation: slideUp 1s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Animation du cœur */
        .heart-beat {
            animation: heartBeat 2s ease-in-out infinite;
        }
        
        @keyframes heartBeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.1); }
            28% { transform: scale(1); }
            42% { transform: scale(1.1); }
            70% { transform: scale(1); }
        }
        
        /* Animation des avatars flottants */
        .float {
            animation: float 3s ease-in-out infinite;
        }
        
        .float:nth-child(2) { animation-delay: 0.5s; }
        .float:nth-child(3) { animation-delay: 1s; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        /* Responsive design */
        @media (max-width: 640px) {
            .container-responsive {
                margin: 0 16px;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-pink-50 via-purple-50 to-indigo-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <!-- Header avec logo/branding -->
        <div class="text-center mb-8 fade-in">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-pink-500 to-purple-600 rounded-full mb-4">
                <svg class="w-8 h-8 text-white heart-beat" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo e(config('app.name')); ?></h1>
        </div>

        <!-- Card principale -->
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden fade-in">
            <!-- Header de la card avec gradient et motif -->
            <div class="relative bg-gradient-to-r from-pink-500 via-purple-500 to-indigo-600 px-8 py-8">
                <!-- Motifs décoratifs -->
                <div class="absolute top-0 left-0 w-full h-full opacity-10">
                    <div class="absolute top-4 left-8 w-20 h-20 bg-white rounded-full"></div>
                    <div class="absolute top-12 right-12 w-16 h-16 bg-white rounded-full"></div>
                    <div class="absolute bottom-8 left-1/3 w-12 h-12 bg-white rounded-full"></div>
                </div>
                
                <div class="relative z-10">
                    <h2 class="text-3xl font-bold text-white text-center mb-4">
                        Vous nous manquez, <?php echo e($user->name); ?> !
                    </h2>
                    
                    <!-- Indicateur de temps -->
                    <div class="flex items-center justify-center">
                        <div class="bg-white bg-opacity-20 backdrop-blur-sm rounded-full px-4 py-2 flex items-center">
                            <svg class="w-5 h-5 text-white mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-white font-medium">Absent depuis 48h</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="px-8 py-8">
                <!-- Salutation -->
                <div class="mb-6 slide-up">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">
                        Bonjour <?php echo e($user->name); ?>,
                    </h3>
                </div>

                <!-- Message principal avec illustration -->
                <div class="bg-gradient-to-r from-pink-50 to-purple-50 border-2 border-pink-200 rounded-2xl p-6 mb-8 slide-up">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-start mb-4">
                                <div class="flex-shrink-0 mr-4">
                                    <div class="w-12 h-12 bg-gradient-to-r from-pink-400 to-purple-500 rounded-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <p class="text-gray-700 text-lg leading-relaxed">
                                        Cela fait <span class="font-bold text-pink-600">48 heures</span> que vous n'êtes pas venu sur notre application.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section des profils en attente -->
                <div class="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-2xl p-6 mb-8 slide-up">
                    <div class="text-center">
                        <!-- Avatars flottants -->
                        <div class="flex justify-center items-center mb-4 space-x-2">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-indigo-500 rounded-full flex items-center justify-center float">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-teal-500 rounded-full flex items-center justify-center float">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-pink-500 rounded-full flex items-center justify-center float">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                <span class="text-xs font-semibold text-gray-600">+5</span>
                            </div>
                        </div>
                        
                        <div class="inline-flex items-center bg-white border-2 border-indigo-200 rounded-full px-6 py-3">
                            <svg class="w-5 h-5 text-indigo-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a2 2 0 01-2-2v-6a2 2 0 012-2h8z"></path>
                            </svg>
                            <span class="text-indigo-800 font-semibold text-lg">
                                De nombreux profils attendent de discuter avec vous !
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Bouton CTA principal -->
                <div class="text-center mb-8">
                    <a href="<?php echo e($url); ?>" class="btn-hover inline-flex items-center px-10 py-5 bg-gradient-to-r from-pink-500 to-purple-600 text-white font-bold rounded-full text-xl shadow-lg hover:shadow-2xl focus:outline-none focus:ring-4 focus:ring-pink-300">
                        <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path>
                        </svg>
                        Revenir sur l'application
                        <div class="ml-2 w-2 h-2 bg-white rounded-full animate-pulse"></div>
                    </a>
                </div>

                <!-- Stats d'engagement -->
                <div class="grid grid-cols-3 gap-4 mb-8">
                    <div class="text-center p-4 bg-gradient-to-b from-pink-50 to-white rounded-xl border border-pink-100">
                        <div class="text-2xl font-bold text-pink-600 mb-1">12</div>
                        <div class="text-xs text-gray-600">Nouveaux likes</div>
                    </div>
                    <div class="text-center p-4 bg-gradient-to-b from-purple-50 to-white rounded-xl border border-purple-100">
                        <div class="text-2xl font-bold text-purple-600 mb-1">8</div>
                        <div class="text-xs text-gray-600">Messages</div>
                    </div>
                    <div class="text-center p-4 bg-gradient-to-b from-indigo-50 to-white rounded-xl border border-indigo-100">
                        <div class="text-2xl font-bold text-indigo-600 mb-1">5</div>
                        <div class="text-xs text-gray-600">Matchs</div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="border-t border-gray-200 my-8"></div>

                <!-- Support -->
                <div class="text-center">
                    <p class="text-gray-600 mb-4">
                        Si vous avez des questions, n'hésitez pas à nous contacter.
                    </p>
                    
                    <!-- Liens de support -->
                    <div class="flex justify-center space-x-6 text-sm">
                        <a href="#" class="text-pink-600 hover:text-pink-800 transition-colors duration-200 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25A9.75 9.75 0 002.25 12c0 5.384 4.365 9.75 9.75 9.75s9.75-4.366 9.75-9.75S17.634 2.25 12 2.25zM12 6v6l4 2"></path>
                            </svg>
                            Centre d'aide
                        </a>
                        <a href="#" class="text-pink-600 hover:text-pink-800 transition-colors duration-200 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Contact
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 fade-in">
            <p class="text-gray-600 mb-2">
                Merci,<br>
                <span class="font-semibold bg-gradient-to-r from-pink-600 to-purple-600 bg-clip-text text-transparent">L'équipe <?php echo e(config('app.name')); ?></span>
            </p>
            
            <!-- Social links -->
            <div class="flex justify-center space-x-4 mt-4">
                <div class="w-8 h-8 bg-gradient-to-r from-pink-400 to-purple-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform cursor-pointer">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                    </svg>
                </div>
                <div class="w-8 h-8 bg-gradient-to-r from-purple-400 to-indigo-500 rounded-full flex items-center justify-center hover:scale-110 transition-transform cursor-pointer">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
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
</html><?php /**PATH C:\Users\ROYAL COMPUTER\Desktop\projets\laravel\rencontre\resources\views/emails/reactivation.blade.php ENDPATH**/ ?>