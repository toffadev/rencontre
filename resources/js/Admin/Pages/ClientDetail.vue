<template>
  <AdminLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Détails du Client
        </h2>
        <Link
          :href="route('admin.clients.index')"
          class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
        >
          Retour à la liste
        </Link>
      </div>
    </template>

    <div class="py-12">
      <!-- Informations de base -->
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow sm:rounded-lg">
          <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900">
              Informations du Client
            </h3>
          </div>
          <div class="border-t border-gray-200">
            <div class="px-4 py-5 sm:p-6">
              <div class="md:grid md:grid-cols-3 md:gap-8">
                <!-- Photo de profil et infos de base -->
                <div class="md:col-span-1">
                  <div class="flex flex-col items-center">
                    <img
                      :src="client.profile?.profile_photo_url || 'https://via.placeholder.com/150'"
                      alt="Photo de profil"
                      class="h-32 w-32 rounded-full object-cover"
                    />
                    <div class="mt-4 text-center">
                      <h4 class="text-lg font-medium text-gray-900">{{ client.name }}</h4>
                      <p class="text-sm text-gray-500">{{ client.email }}</p>
                    </div>
                    <div class="mt-2">
                      <span
                        :class="{
                          'px-2 py-1 text-xs font-medium rounded-full': true,
                          'bg-green-100 text-green-800': client.status === 'active',
                          'bg-red-100 text-red-800': client.status === 'inactive',
                          'bg-yellow-100 text-yellow-800': client.status === 'suspended'
                        }"
                      >
                        {{ client.status }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Informations personnelles -->
                <div class="mt-5 md:col-span-2 md:mt-0">
                  <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Date d'inscription</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ client.registration_date }}</dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Âge</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ client.profile?.age || 'Non renseigné' }} ans</dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Ville</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ client.profile?.city || 'Non renseigné' }}</dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Pays</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ client.profile?.country || 'Non renseigné' }}</dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Statut relationnel</dt>
                      <dd class="mt-1 text-sm text-gray-900">
                        {{ client.profile?.relationship_status || 'Non renseigné' }}
                      </dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Profession</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ client.profile?.occupation || 'Non renseigné' }}</dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Taille</dt>
                      <dd class="mt-1 text-sm text-gray-900">
                        {{ client.profile?.height ? `${client.profile.height} cm` : 'Non renseigné' }}
                      </dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Orientation</dt>
                      <dd class="mt-1 text-sm text-gray-900">
                        {{ client.profile?.sexual_orientation || 'Non renseigné' }}
                      </dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Recherche</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ client.profile?.seeking_gender || 'Non renseigné' }}</dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">A des enfants</dt>
                      <dd class="mt-1 text-sm text-gray-900">
                        {{ client.profile?.has_children ? 'Oui' : 'Non' }}
                      </dd>
                    </div>

                    <div class="sm:col-span-1">
                      <dt class="text-sm font-medium text-gray-500">Souhaite des enfants</dt>
                      <dd class="mt-1 text-sm text-gray-900">
                        {{ client.profile?.wants_children === null ? 'Non renseigné' : (client.profile.wants_children ? 'Oui' : 'Non') }}
                      </dd>
                    </div>

                    <div class="sm:col-span-2">
                      <dt class="text-sm font-medium text-gray-500">Bio</dt>
                      <dd class="mt-1 text-sm text-gray-900">{{ client.profile?.bio || 'Aucune bio' }}</dd>
                    </div>
                  </dl>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistiques -->
      <div class="mx-auto mt-8 max-w-7xl sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            title="Total Messages"
            :value="stats.total_messages"
            icon="message-square"
          />
          <StatCard
            title="Points Achetés"
            :value="stats.total_points_bought"
            icon="shopping-cart"
          />
          <StatCard
            title="Points Dépensés"
            :value="stats.total_points_spent"
            icon="credit-card"
          />
          <StatCard
            title="Total Dépensé"
            :value="formatCurrency(stats.total_spent)"
            icon="euro-sign"
          />
        </div>
      </div>

      <!-- Onglets -->
      <div class="mx-auto mt-8 max-w-7xl sm:px-6 lg:px-8">
        <div class="border-b border-gray-200">
          <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.name"
              @click="currentTab = tab.name"
              :class="[
                currentTab === tab.name
                  ? 'border-indigo-500 text-indigo-600'
                  : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium'
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <!-- Contenu des onglets -->
        <div class="mt-8">
          <!-- Conversations -->
          <div v-if="currentTab === 'conversations'" class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg font-medium leading-6 text-gray-900">
                Conversations Actives
              </h3>
              <div class="mt-6 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                  <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                    <table class="min-w-full divide-y divide-gray-300">
                      <thead>
                        <tr>
                          <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">
                            Profil
                          </th>
                          <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Messages
                          </th>
                          <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Points Dépensés
                          </th>
                          <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Dernier Message
                          </th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-200">
                        <tr v-for="conv in conversations" :key="conv.profile.id">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm">
                            <div class="flex items-center">
                              <div class="h-10 w-10 flex-shrink-0">
                                <img
                                  :src="conv.profile.main_photo_path"
                                  class="h-10 w-10 rounded-full"
                                />
                              </div>
                              <div class="ml-4">
                                <div class="font-medium text-gray-900">
                                  {{ conv.profile.name }}
                                </div>
                              </div>
                            </div>
                          </td>
                          <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ conv.message_count }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ conv.points_spent }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            {{ conv.last_message }}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Historique Financier -->
          <div v-if="currentTab === 'financial'" class="space-y-8">
            <!-- Transactions -->
            <div class="bg-white shadow sm:rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                  Historique des Transactions
                </h3>
                <div class="mt-6 flow-root">
                  <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                      <table class="min-w-full divide-y divide-gray-300">
                        <thead>
                          <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">
                              Date
                            </th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                              Type
                            </th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                              Points
                            </th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                              Montant
                            </th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                          <tr v-for="transaction in financial_history.transactions" :key="transaction.id">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                              {{ formatDate(transaction.created_at) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                              {{ transaction.type }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                              {{ transaction.points_amount }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                              {{ formatCurrency(transaction.money_amount) }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Consommation de Points -->
            <div class="bg-white shadow sm:rounded-lg">
              <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                  Historique de Consommation des Points
                </h3>
                <div class="mt-6 flow-root">
                  <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                      <table class="min-w-full divide-y divide-gray-300">
                        <thead>
                          <tr>
                            <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900">
                              Date
                            </th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                              Action
                            </th>
                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                              Points Dépensés
                            </th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                          <tr v-for="consumption in financial_history.consumptions" :key="consumption.id">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                              {{ formatDate(consumption.created_at) }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                              {{ consumption.action }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                              {{ consumption.points_spent }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Historique d'Activité -->
          <div v-if="currentTab === 'activity'" class="bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <h3 class="text-lg font-medium leading-6 text-gray-900">
                Historique d'Activité
              </h3>
              <div class="mt-6 flow-root">
                <ul role="list" class="-mb-8">
                  <li v-for="activity in activity_history" :key="activity.date">
                    <div class="relative pb-8">
                      <span
                        class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200"
                        aria-hidden="true"
                      ></span>
                      <div class="relative flex space-x-3">
                        <div>
                          <span
                            :class="[
                              activityTypeStyles[activity.type].bgColor,
                              'h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white'
                            ]"
                          >
                            <component
                              :is="activityTypeStyles[activity.type].icon"
                              class="h-5 w-5 text-white"
                              aria-hidden="true"
                            />
                          </span>
                        </div>
                        <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                          <div>
                            <p class="text-sm text-gray-500">
                              {{ activity.details }}
                            </p>
                          </div>
                          <div class="whitespace-nowrap text-right text-sm text-gray-500">
                            <time :datetime="activity.date">{{ formatDate(activity.date) }}</time>
                            <div
                              :class="[
                                activity.points > 0 ? 'text-green-600' : 'text-red-600',
                                'font-medium'
                              ]"
                            >
                              {{ activity.points > 0 ? '+' : '' }}{{ activity.points }} points
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </li>
                </ul>
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
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import StatCard from '@/Admin/Components/ClientManagement/StatCard.vue'
import {
  MessageSquareIcon,
  ShoppingCartIcon,
  CreditCardIcon,
  EuroIcon,
  UserIcon,
  DollarSignIcon
} from 'lucide-vue-next'

const props = defineProps({
  client: Object,
  stats: Object,
  conversations: Array,
  financial_history: Object,
  activity_history: Array
})

const tabs = [
  { name: 'conversations', label: 'Conversations' },
  { name: 'financial', label: 'Historique Financier' },
  { name: 'activity', label: 'Activité' }
]

const currentTab = ref('conversations')

const activityTypeStyles = {
  message: {
    icon: MessageSquareIcon,
    bgColor: 'bg-blue-500'
  },
  transaction: {
    icon: DollarSignIcon,
    bgColor: 'bg-green-500'
  },
  login: {
    icon: UserIcon,
    bgColor: 'bg-purple-500'
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(value)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script> 