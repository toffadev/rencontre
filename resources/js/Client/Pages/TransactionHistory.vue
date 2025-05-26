<template>
  <MainLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Historique des transactions de points
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-6">
            <transaction-history
              :show-profile="true"
            />
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script>
import { defineComponent } from 'vue'
import MainLayout from '@/Client/Layouts/MainLayout.vue'
import TransactionHistory from '@/Components/ProfilePoints/TransactionHistory.vue'

export default defineComponent({
  name: 'ClientTransactionHistory',

  components: {
    MainLayout,
    TransactionHistory
  }
})
</script> 