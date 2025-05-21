<template>
  <div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b border-gray-200">
      <h2 class="text-lg font-semibold text-dark">Commandes récentes</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Commande</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="order in recentOrders" :key="order.id">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-primary">{{ order.number }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.customer }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ order.amount }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span 
                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" 
                :class="getStatusClass(order.status)">
                {{ order.status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="p-4 border-t border-gray-200 text-center">
      <a href="#" class="text-primary font-medium hover:underline">Voir toutes les commandes</a>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const recentOrders = ref([
  { id: 1, number: '#3258', customer: 'Jean Dupont', amount: '149.90 €', status: 'Livré' },
  { id: 2, number: '#3257', customer: 'Marie Martin', amount: '89.50 €', status: 'En cours' },
  { id: 3, number: '#3256', customer: 'Pierre Bernard', amount: '210.00 €', status: 'Expédié' },
  { id: 4, number: '#3255', customer: 'Sophie Leroy', amount: '65.90 €', status: 'Annulé' },
  { id: 5, number: '#3254', customer: 'Thomas Petit', amount: '120.00 €', status: 'Livré' }
])

const getStatusClass = (status) => {
  switch (status) {
    case 'Livré':
      return 'bg-green-100 text-green-800'
    case 'En cours':
      return 'bg-yellow-100 text-yellow-800'
    case 'Expédié':
      return 'bg-blue-100 text-blue-800'
    case 'Annulé':
      return 'bg-red-100 text-red-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}
</script> 