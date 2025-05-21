<template>
  <AdminLayout>
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
      <StatsCard title="Singles" value="12" change="+20%" icon="music" color="primary" />
      <StatsCard title="Événements" value="8" change="+5%" icon="calendar-alt" color="secondary" />
      <StatsCard title="Actualités" value="24" change="+15%" icon="newspaper" color="purple" />
      <StatsCard title="Médias" value="56" change="+30%" icon="photo-video" color="yellow" />
    </div>

    <!-- Content Overview -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
        <h2 class="text-lg font-semibold text-dark mb-4">Actualités récentes</h2>
        <div class="space-y-4">
          <div v-for="item in recentActualities" :key="item.id"
            class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <span class="inline-flex items-center justify-center h-10 w-10 rounded-full" :class="{
                  'bg-blue-100 text-blue-600': item.category === 'event',
                  'bg-purple-100 text-purple-600': item.category === 'news',
                  'bg-green-100 text-green-600': item.category === 'release'
                }">
                  <i :class="[
                    'fas',
                    item.category === 'event' ? 'fa-calendar-alt' :
                      item.category === 'news' ? 'fa-newspaper' : 'fa-music'
                  ]"></i>
                </span>
              </div>
              <div class="ml-4 flex-1">
                <div class="flex items-center justify-between">
                  <h3 class="text-sm font-medium text-gray-900">{{ item.title }}</h3>
                  <span class="text-xs text-gray-500">{{ item.date }}</span>
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ item.shortContent }}</p>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-4 text-center">
          <button class="text-primary font-medium hover:underline text-sm">Voir toutes les actualités</button>
        </div>
      </div>
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-dark mb-4">Contenu du site</h2>
        <div class="space-y-6">
          <div v-for="stat in contentStats" :key="stat.id">
            <div class="flex items-center justify-between mb-2">
              <span class="text-gray-600">{{ stat.name }}</span>
              <span class="font-medium">{{ stat.count }}</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div class="h-2 rounded-full" :class="stat.color" :style="{ width: `${stat.percentage}%` }"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Upcoming Events & New Project -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-dark">Événements à venir</h2>
        </div>
        <div class="divide-y divide-gray-200">
          <div v-for="event in upcomingEvents" :key="event.id" class="p-4 flex items-center">
            <div class="flex-shrink-0 h-16 w-16 rounded-md overflow-hidden bg-gray-200">
              <img :src="event.image" :alt="event.title" class="h-full w-full object-cover">
            </div>
            <div class="ml-4 flex-1">
              <h3 class="text-sm font-medium text-dark">{{ event.title }}</h3>
              <p class="text-sm text-gray-500">{{ event.location }}</p>
              <p class="text-xs text-gray-400 mt-1">{{ event.date }}</p>
            </div>
            <div class="ml-4">
              <span :class="[
                'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full',
                event.isSoldOut ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
              ]">
                {{ event.isSoldOut ? 'Complet' : 'Places disponibles' }}
              </span>
            </div>
          </div>
        </div>
        <div class="p-4 border-t border-gray-200 text-center">
          <button class="text-primary font-medium hover:underline">Voir tous les événements</button>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-6 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-dark">Nouveau projet</h2>
        </div>
        <div class="p-6">
          <div class="flex items-start space-x-4">
            <img :src="newProject.cover_image" :alt="newProject.title" class="h-32 w-32 rounded-lg object-cover">
            <div>
              <h3 class="text-xl font-bold text-gray-900">{{ newProject.title }}</h3>
              <div class="mt-1">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="{
                  'bg-blue-100 text-blue-800': newProject.type === 'single',
                  'bg-purple-100 text-purple-800': newProject.type === 'album',
                  'bg-green-100 text-green-800': newProject.type === 'ep'
                }">
                  {{ newProject.type.toUpperCase() }}
                </span>
              </div>
              <p class="mt-2 text-sm text-gray-600">{{ newProject.description }}</p>
              <p class="mt-2 text-sm text-gray-500">Date de sortie: {{ newProject.release_date }}</p>
              <div class="mt-4">
                <button class="text-primary hover:underline">Gérer ce projet</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import AdminLayout from '../Layouts/AdminLayout.vue'
import StatsCard from '../Components/StatsCard.vue'

// Données statiques pour la démo
const recentActualities = ref([
  {
    id: 1,
    title: 'Nouveau concert annoncé',
    category: 'event',
    date: '10 avril 2023',
    shortContent: 'Un nouveau concert vient d\'être ajouté à la tournée.'
  },
  {
    id: 2,
    title: 'Interview exclusive',
    category: 'news',
    date: '5 avril 2023',
    shortContent: 'Découvrez notre nouvelle interview exclusive.'
  },
  {
    id: 3,
    title: 'Nouvel album en préparation',
    category: 'release',
    date: '1 avril 2023',
    shortContent: 'Un nouvel album est en cours de préparation.'
  }
])

const contentStats = ref([
  {
    id: 1,
    name: 'Musiques',
    count: 24,
    percentage: 80,
    color: 'bg-primary'
  },
  {
    id: 2,
    name: 'Événements',
    count: 12,
    percentage: 40,
    color: 'bg-secondary'
  },
  {
    id: 3,
    name: 'Actualités',
    count: 36,
    percentage: 60,
    color: 'bg-purple-500'
  },
  {
    id: 4,
    name: 'Médias',
    count: 45,
    percentage: 75,
    color: 'bg-yellow-500'
  }
])

const upcomingEvents = ref([
  {
    id: 1,
    title: 'Concert à Paris',
    location: 'Paris, France',
    date: '15 mai 2023, 20:00',
    isSoldOut: false,
    image: 'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?ixlib=rb-1.2.1&auto=format&fit=crop&w=80&h=80&q=80'
  },
  {
    id: 2,
    title: 'Festival d\'été',
    location: 'Lyon, France',
    date: '22 juin 2023, 18:30',
    isSoldOut: true,
    image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?ixlib=rb-1.2.1&auto=format&fit=crop&w=80&h=80&q=80'
  },
  {
    id: 3,
    title: 'Showcase',
    location: 'Marseille, France',
    date: '10 juillet 2023, 19:00',
    isSoldOut: false,
    image: 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?ixlib=rb-1.2.1&auto=format&fit=crop&w=80&h=80&q=80'
  }
])

const newProject = ref({
  title: 'Horizons Infinis',
  type: 'album',
  description: 'Notre nouvel album conceptuel explorant des thèmes universels à travers des mélodies innovantes.',
  release_date: '15 juin 2023',
  cover_image: 'https://images.unsplash.com/photo-1496293455970-f8581aae0e3b?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&h=300&q=80'
})
</script>