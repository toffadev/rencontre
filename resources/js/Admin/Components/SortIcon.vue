<template>
  <span class="ml-1 inline-flex flex-col">
    <i class="fas fa-sort-up -mb-1" 
       :class="active && direction === 'asc' ? 'text-primary' : 'text-gray-400'"></i>
    <i class="fas fa-sort-down -mt-1" 
       :class="active && direction === 'desc' ? 'text-primary' : 'text-gray-400'"></i>
  </span>
</template>

<script setup>
defineProps({
  active: {
    type: Boolean,
    default: false
  },
  direction: {
    type: String,
    default: 'asc'
  }
})
</script> 