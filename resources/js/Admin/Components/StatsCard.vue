<template>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-gray-500">{{ title }}</p>
        <h3 class="text-2xl font-bold text-dark">{{ value }}</h3>
        <p :class="['text-sm flex items-center', 
                    change.startsWith('+') ? 'text-green-500' : 'text-red-500']">
          <i :class="['fas mr-1', 
                     change.startsWith('+') ? 'fa-arrow-up' : 'fa-arrow-down']"></i>
          {{ change }} vs mois dernier
        </p>
      </div>
      <div :class="[
        'p-3 rounded-full',
        {
          'bg-blue-100 text-primary': color === 'primary',
          'bg-green-100 text-secondary': color === 'secondary',
          'bg-purple-100 text-purple-600': color === 'purple',
          'bg-yellow-100 text-yellow-600': color === 'yellow'
        }
      ]">
        <i :class="['fas fa-' + icon, 'text-xl']"></i>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  value: {
    type: String,
    required: true
  },
  change: {
    type: String,
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  color: {
    type: String,
    default: 'primary'
  }
})
</script> 