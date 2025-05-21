<template>
  <div>
    <h2 class="text-lg font-semibold text-dark mb-4">Objectifs</h2>
    <div class="space-y-6">
      <div v-for="objective in objectives" :key="objective.id">
        <div class="flex items-center justify-between mb-2">
          <span class="text-gray-600">{{ objective.name }}</span>
          <span class="font-medium">{{ objective.percentage }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
          <div 
            class="h-2 rounded-full" 
            :class="objective.color"
            :style="{ width: `${objective.percentage}%` }"
          ></div>
        </div>
      </div>
      
      <div class="flex justify-center mt-6">
        <div class="relative w-32 h-32">
          <svg class="w-full h-full" viewBox="0 0 100 100">
            <!-- Background circle -->
            <circle class="text-gray-200" stroke-width="8" stroke="currentColor" fill="transparent" r="40" cx="50" cy="50" />
            <!-- Progress circle -->
            <circle 
              class="text-primary progress-ring__circle" 
              stroke-width="8" 
              stroke-linecap="round" 
              stroke="currentColor" 
              fill="transparent" 
              r="40" 
              cx="50" 
              cy="50" 
              :stroke-dasharray="circumference" 
              :stroke-dashoffset="dashOffset"
            />
          </svg>
          <div class="absolute inset-0 flex items-center justify-center">
            <span class="text-2xl font-bold text-dark">{{ totalProgress }}%</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const objectives = ref([
  {
    id: 1,
    name: 'Ventes mensuelles',
    percentage: 75,
    color: 'bg-primary'
  },
  {
    id: 2,
    name: 'Nouveaux clients',
    percentage: 50,
    color: 'bg-secondary'
  },
  {
    id: 3,
    name: 'Satisfaction client',
    percentage: 89,
    color: 'bg-yellow-500'
  }
])

const totalProgress = computed(() => {
  // Calculate average progress
  const sum = objectives.value.reduce((acc, obj) => acc + obj.percentage, 0)
  return Math.round(sum / objectives.value.length)
})

const radius = 40
const circumference = computed(() => 2 * Math.PI * radius)
const dashOffset = computed(() => circumference.value - (totalProgress.value / 100) * circumference.value)

onMounted(() => {
  // Animation effect
  const circles = document.querySelectorAll('.progress-ring__circle')
  circles.forEach(circle => {
    circle.style.transition = 'stroke-dashoffset 1s ease-in-out'
  })
})
</script>

<style scoped>
.progress-ring__circle {
  transform: rotate(-90deg);
  transform-origin: 50% 50%;
  transition: stroke-dashoffset 1s ease-in-out;
}
</style> 