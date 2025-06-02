<template>
  <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
    <div class="flex items-center">
      <div class="flex-shrink-0">
        <component
          :is="iconComponent"
          class="h-6 w-6 text-gray-400"
          aria-hidden="true"
        />
      </div>
      <div class="ml-5 w-0 flex-1">
        <dl>
          <dt class="truncate text-sm font-medium text-gray-500">{{ title }}</dt>
          <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
            {{ value }}
          </dd>
        </dl>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  UsersIcon,
  UserCheckIcon,
  EuroIcon,
  ChartLineIcon,
  MessageSquareIcon,
  StarIcon
} from 'lucide-vue-next'

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
  }
})

const iconMap = {
  'users': UsersIcon,
  'user-check': UserCheckIcon,
  'euro-sign': EuroIcon,
  'chart-line': ChartLineIcon,
  'message-square': MessageSquareIcon,
  'star': StarIcon
}

const iconComponent = iconMap[props.icon]
</script> 