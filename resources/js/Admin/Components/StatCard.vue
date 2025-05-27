<template>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-600">{{ title }}</p>
        <p class="mt-2 text-3xl font-semibold text-gray-900">{{ value }}</p>
      </div>
      <div class="p-3 bg-indigo-50 rounded-full">
        <component 
          :is="iconComponent" 
          class="w-6 h-6 text-indigo-600"
          aria-hidden="true"
        />
      </div>
    </div>
    <div class="mt-4">
      <div class="flex items-center">
        <component 
          :is="trendIcon" 
          class="w-4 h-4"
          :class="trendColorClass"
          aria-hidden="true"
        />
        <span 
          class="ml-2 text-sm font-medium"
          :class="trendColorClass"
        >
          {{ trend }}
        </span>
        <span class="ml-2 text-sm text-gray-500">vs. previous period</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { 
  ChatBubbleLeftIcon, 
  ClockIcon, 
  StarIcon, 
  CurrencyEuroIcon,
  ArrowUpIcon,
  ArrowDownIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  value: {
    type: [String, Number],
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  trend: {
    type: String,
    required: true
  }
})

const iconComponent = computed(() => {
  const icons = {
    chat: ChatBubbleLeftIcon,
    clock: ClockIcon,
    star: StarIcon,
    'currency-euro': CurrencyEuroIcon
  }
  return icons[props.icon]
})

const trendIcon = computed(() => {
  const trend = parseFloat(props.trend)
  return trend >= 0 ? ArrowUpIcon : ArrowDownIcon
})

const trendColorClass = computed(() => {
  const trend = parseFloat(props.trend)
  return trend >= 0 ? 'text-green-600' : 'text-red-600'
})
</script> 