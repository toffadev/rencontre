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

        <Link href="/admin/music-releases"
          :class="['flex items-center space-x-3 p-3 rounded-lg',
            $page.component === 'Admin/Pages/MusicReleases' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Musiques</span>
        </Link>

        <Link href="/admin/new-project"
          :class="['flex items-center space-x-3 p-3 rounded-lg',
            $page.component === 'Admin/Pages/NewProjects' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Nouveautés</span>
        </Link>

        <Link href="/admin/actualities"
          :class="['flex items-center space-x-3 p-3 rounded-lg',
            $page.component === 'Admin/Pages/Actualities' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Actualités</span>
        </Link>

        <Link href="/admin/comedy-shows"
          :class="['flex items-center space-x-3 p-3 rounded-lg',
            $page.component === 'Admin/Pages/ComedyShows' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Comédie</span>
        </Link>

        <Link href="/admin/events" :class="['flex items-center space-x-3 p-3 rounded-lg',
            $page.component === 'Admin/Pages/Events' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Événements</span>
        </Link>

        <Link href="/admin/media-gallery"
          :class="['flex items-center space-x-3 p-3 rounded-lg',
          $page.component === 'Admin/Pages/MediaGallery' ? 'bg-primary text-white' : 'text-gray-700 hover:bg-gray-100']">
        <i class="fas fa-tags"></i>
        <span class="md:hidden lg:block">Médias</span>
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
            <Link :href="route('admin.logout')" method="post" as="button"
              class="text-xs text-gray-500 hover:text-amber-600">
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