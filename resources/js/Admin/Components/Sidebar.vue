<template>
  <div class="sidebar bg-white w-64 md:w-20 lg:w-64 border-r border-gray-200 flex flex-col flex-shrink-0">
    <!-- Logo -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200">
      <div class="flex items-center space-x-2">
        <i class="fas fa-tachometer-alt text-primary text-2xl"></i>
        <span class="text-xl font-bold text-dark md:hidden lg:block">AXEL MERRYL</span>
      </div>
    </div>

    <!-- Menu -->
    <nav class="flex-1 overflow-y-auto p-4">
      <div class="space-y-2">
        <div class="mb-4">
          <p class="text-xs uppercase text-gray-500 font-semibold tracking-wider md:hidden lg:block">Menu Principal</p>
        </div>
        <Link href="/admin"
          :class="['flex items-center space-x-3 p-3 rounded-lg',
            $page.component === 'Admin/Pages/Dashboard' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tachometer-alt"></i>
        <span class="md:hidden lg:block">Tableau de bord</span>
        </Link>

        <Link href="/admin/messages" :class="['flex items-center space-x-3 p-3 rounded-lg',
          $page.component === 'Admin/Pages/Messages' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-envelope"></i>
        <span class="md:hidden lg:block">Messages</span>
        </Link>

        <Link href="/admin/conversations"
          :class="['flex items-center space-x-3 p-3 rounded-lg',
          $page.component === 'Admin/Pages/Conversations' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-envelope"></i>
        <span class="md:hidden lg:block">Conversations</span>
        </Link>

        <Link href="/admin/moderator-performance" :class="['flex items-center space-x-3 p-3 rounded-lg',
          $page.component === 'Admin/Pages/ModeratorPerformance' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Performance des modérateurs</span>
        </Link>

        <Link href="/admin/profiles" :class="['flex items-center space-x-3 p-3 rounded-lg',
          $page.component === 'Admin/Pages/Profiles' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Profil</span>
        </Link>

        <Link href="/admin/users" :class="['flex items-center space-x-3 p-3 rounded-lg',
          $page.component === 'Admin/Pages/Users' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Utilisateurs</span>
        </Link>

        <Link href="/admin/events" :class="['flex items-center space-x-3 p-3 rounded-lg',
          $page.component === 'Admin/Pages/Events' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Événements</span>
        </Link>

        <!-- Autres liens du menu -->
      </div>

      <!-- Profile -->
      <div class="mt-auto p-4 border-t border-gray-200">
        <div class="flex items-center space-x-3">
          <img src="https://ui-avatars.com/api/?name=Admin&background=random" alt="Profile"
            class="w-10 h-10 rounded-full">
          <div class="md:hidden lg:block">
            <p class="font-medium text-dark">{{ $page.props.auth.user.name }}</p>
            <Link :href="route('logout')" method="post" as="button" class="text-xs text-gray-500 hover:text-amber-600">
            Déconnexion
            </Link>
          </div>
        </div>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js';

const page = usePage()
</script>

<style scoped>
.sidebar {
  transition: all 0.3s ease;
}
</style>