<template>
  <AdminLayout title="Détails du signalement">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Détails du signalement #{{ report.id }}
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">
            <!-- Informations sur le signalement -->
            <div class="mb-6">
              <h3 class="text-lg font-medium text-gray-900">Informations du signalement</h3>
              <div class="mt-4 space-y-4">
                <div>
                  <span class="font-bold">Statut:</span>
                  <span :class="{
                    'text-yellow-600': report.status === 'pending',
                    'text-green-600': report.status === 'accepted',
                    'text-red-600': report.status === 'dismissed'
                  }">
                    {{ report.status }}
                  </span>
                </div>
                <div>
                  <span class="font-bold">Raison:</span>
                  {{ report.reason }}
                </div>
                <div v-if="report.description">
                  <span class="font-bold">Description:</span>
                  {{ report.description }}
                </div>
                <div>
                  <span class="font-bold">Date de création:</span>
                  {{ report.created_at }}
                </div>
                <div v-if="report.reviewed_at">
                  <span class="font-bold">Date de révision:</span>
                  {{ report.reviewed_at }}
                </div>
              </div>
            </div>

            <!-- Informations sur le signaleur -->
            <div class="mb-6">
              <h3 class="text-lg font-medium text-gray-900">Informations sur le signaleur</h3>
              <div class="mt-4 space-y-4">
                <div>
                  <span class="font-bold">Nom:</span>
                  {{ report.reporter.name }}
                </div>
                <div>
                  <span class="font-bold">Email:</span>
                  {{ report.reporter.email }}
                </div>
              </div>
            </div>

            <!-- Informations sur le profil signalé -->
            <div class="mb-6">
              <h3 class="text-lg font-medium text-gray-900">Profil signalé</h3>
              <div class="mt-4 space-y-4">
                <div>
                  <span class="font-bold">Nom du profil:</span>
                  {{ report.reported_profile.name }}
                </div>
                <div v-if="report.reported_user">
                  <span class="font-bold">Utilisateur associé:</span>
                  {{ report.reported_user.name }} ({{ report.reported_user.email }})
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="mt-8 flex space-x-4" v-if="report.status === 'pending'">
              <button
                @click="acceptReport"
                class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
              >
                Accepter le signalement
              </button>
              <button
                @click="dismissReport"
                class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded"
              >
                Rejeter le signalement
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script>
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { useForm } from '@inertiajs/vue3'

export default {
  components: {
    AdminLayout
  },

  props: {
    report: {
      type: Object,
      required: true
    }
  },

  methods: {
    async acceptReport() {
      try {
        await this.$inertia.post(route('admin.reports.accept', this.report.id))
      } catch (error) {
        console.error('Erreur lors de l\'acceptation du signalement:', error)
      }
    },

    async dismissReport() {
      try {
        await this.$inertia.post(route('admin.reports.dismiss', this.report.id))
      } catch (error) {
        console.error('Erreur lors du rejet du signalement:', error)
      }
    }
  }
}
</script> 