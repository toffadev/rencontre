<template>
  <AdminLayout>
    <div class="p-6">
      <h1 class="text-2xl font-semibold mb-6">Tableau de bord</h1>

      <!-- KPIs principaux -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <DashboardStat
          title="Messages"
          :value="stats.messages?.total_messages || 0"
          :subValue="`${stats.messages?.messages_today || 0} aujourd'hui`"
          icon="chat"
          color="blue"
        />
        <DashboardStat
          title="Points vendus"
          :value="stats.financial?.total_points_sold || 0"
          :subValue="`${stats.financial?.points_sold_today || 0} aujourd'hui`"
          icon="coins"
          color="yellow"
        />
        <DashboardStat
          title="Clients actifs"
          :value="stats.general?.active_clients || 0"
          :subValue="`${stats.general?.total_clients || 0} total`"
          icon="users"
          color="green"
        />
        <DashboardStat
          title="Revenus"
          :value="`${stats.financial?.total_revenue || 0}€`"
          :subValue="`${stats.financial?.revenue_today || 0}€ aujourd'hui`"
          icon="euro"
          color="indigo"
        />
      </div>

      <!-- Statistiques des messages -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <Card title="Statistiques des messages">
          <div class="grid grid-cols-2 gap-4">
            <div class="p-4 bg-gray-50 rounded-lg">
              <div class="text-sm text-gray-600">Messages clients</div>
              <div class="text-xl font-semibold">{{ stats.messages?.client_messages || 0 }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
              <div class="text-sm text-gray-600">Messages modérateurs</div>
              <div class="text-xl font-semibold">{{ stats.messages?.moderator_messages || 0 }}</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
              <div class="text-sm text-gray-600">Taux de réponse</div>
              <div class="text-xl font-semibold">{{ stats.messages?.response_rate || 0 }}%</div>
            </div>
            <div class="p-4 bg-gray-50 rounded-lg">
              <div class="text-sm text-gray-600">Temps de réponse moyen</div>
              <div class="text-xl font-semibold">{{ Math.round(stats.messages?.avg_response_time || 0) }}min</div>
            </div>
          </div>
        </Card>

        <!-- Top Modérateurs -->
        <Card title="Top Modérateurs">
          <div class="space-y-4">
            <div v-for="moderator in stats.top_moderators" :key="moderator.id" class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg">
              <div>
                <div class="font-medium">{{ moderator.name }}</div>
                <div class="text-sm text-gray-600">
                  {{ moderator.profiles.map(p => p.name).join(', ') }}
                </div>
              </div>
              <div class="text-right">
                <div class="font-semibold">{{ moderator.messages_count }} messages</div>
                <div class="text-sm text-gray-600">
                  {{ moderator.points_earned }} points gagnés
                </div>
                <div class="text-sm text-gray-600">
                  {{ moderator.response_rate }}% taux de réponse
                </div>
              </div>
            </div>
          </div>
        </Card>
      </div>

      <!-- Top Profils et Transactions -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Profils -->
        <Card title="Top Profils">
          <div class="space-y-4">
            <div v-for="profile in stats.top_profiles" :key="profile.id" class="flex items-center space-x-4 p-2 hover:bg-gray-50 rounded-lg">
              <img :src="profile.photo" class="w-10 h-10 rounded-full object-cover" :alt="profile.name">
              <div class="flex-grow">
                <div class="font-medium">{{ profile.name }}</div>
                <div class="text-sm text-gray-600">
                  {{ profile.messages_count }} messages
                </div>
                <div class="text-sm text-gray-600">
                  Modérateurs: {{ profile.moderators.map(m => m.name).join(', ') }}
                </div>
              </div>
              <div class="text-right">
                <div class="font-semibold">{{ profile.points_earned }} points</div>
                <div class="text-sm text-gray-600">{{ profile.conversion_rate }}% conversion</div>
              </div>
            </div>
          </div>
        </Card>

        <!-- Transactions Récentes -->
        <Card title="Transactions Récentes">
          <div class="space-y-4">
            <div v-for="transaction in stats.recent_transactions" :key="transaction.id" class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg">
              <div>
                <div class="font-medium">{{ transaction.client_name }}</div>
                <div class="text-sm text-gray-600">
                  {{ transaction.type === 'profile' ? `Pour ${transaction.profile_name}` : 'Points généraux' }}
                </div>
                <div class="text-sm text-gray-600">{{ formatDate(transaction.created_at) }}</div>
              </div>
              <div class="text-right">
                <div class="font-semibold">{{ transaction.points_amount }} points</div>
                <div class="text-sm text-gray-600">{{ transaction.money_amount }}€</div>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import { ref, onMounted } from 'vue'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import Card from '@/Admin/Components/Card.vue'
import DashboardStat from '@/Admin/Components/DashboardStat.vue'
import axios from 'axios'

export default {
  components: {
    AdminLayout,
    Card,
    DashboardStat
  },

  setup() {
    const stats = ref({
      general: {},
      messages: {},
      financial: {},
      top_moderators: [],
      top_profiles: [],
      recent_transactions: []
    })

    const formatDate = (date) => {
      return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    const loadStats = async () => {
      try {
        const response = await axios.get('/admin/dashboard/stats')
        stats.value = response.data
      } catch (error) {
        console.error('Erreur lors du chargement des données:', error)
        if (error.response?.data?.error) {
          console.error('Détails:', error.response.data.error)
        }
      }
    }

    onMounted(() => {
      loadStats()
      // Actualiser les données toutes les 5 minutes
      const interval = setInterval(loadStats, 5 * 60 * 1000)
      return () => clearInterval(interval)
    })

    return {
      stats,
      formatDate
    }
  }
}
</script>