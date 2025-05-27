<template>
  <div class="flex items-center justify-between">
    <div class="flex items-center">
      <p class="text-sm text-gray-700">
        Showing
        <span class="font-medium">{{ startItem }}</span>
        to
        <span class="font-medium">{{ endItem }}</span>
        of
        <span class="font-medium">{{ modelValue.total }}</span>
        results
      </p>
    </div>
    <div class="flex items-center space-x-2">
      <button
        @click="goToPage(modelValue.currentPage - 1)"
        :disabled="modelValue.currentPage === 1"
        class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md"
        :class="[
          modelValue.currentPage === 1
            ? 'text-gray-300 cursor-not-allowed'
            : 'text-gray-700 hover:bg-gray-50'
        ]"
      >
        Previous
      </button>
      
      <div class="hidden md:flex space-x-2">
        <template v-for="page in visiblePages" :key="page">
          <button
            v-if="page !== '...'"
            @click="goToPage(page)"
            class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md"
            :class="[
              page === modelValue.currentPage
                ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                : 'text-gray-700 hover:bg-gray-50'
            ]"
          >
            {{ page }}
          </button>
          <span
            v-else
            class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700"
          >
            ...
          </span>
        </template>
      </div>

      <button
        @click="goToPage(modelValue.currentPage + 1)"
        :disabled="modelValue.currentPage === modelValue.lastPage"
        class="relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md"
        :class="[
          modelValue.currentPage === modelValue.lastPage
            ? 'text-gray-300 cursor-not-allowed'
            : 'text-gray-700 hover:bg-gray-50'
        ]"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
    validator: (value) => {
      return [
        'currentPage',
        'lastPage',
        'perPage',
        'total'
      ].every(prop => prop in value)
    }
  }
})

const emit = defineEmits(['update:modelValue', 'paginate'])

const startItem = computed(() => {
  return (props.modelValue.currentPage - 1) * props.modelValue.perPage + 1
})

const endItem = computed(() => {
  return Math.min(
    props.modelValue.currentPage * props.modelValue.perPage,
    props.modelValue.total
  )
})

const visiblePages = computed(() => {
  const current = props.modelValue.currentPage
  const last = props.modelValue.lastPage
  const delta = 2
  const range = []
  const rangeWithDots = []
  let l

  for (let i = 1; i <= last; i++) {
    if (
      i === 1 ||
      i === last ||
      (i >= current - delta && i <= current + delta)
    ) {
      range.push(i)
    }
  }

  for (let i of range) {
    if (l) {
      if (i - l === 2) {
        rangeWithDots.push(l + 1)
      } else if (i - l !== 1) {
        rangeWithDots.push('...')
      }
    }
    rangeWithDots.push(i)
    l = i
  }

  return rangeWithDots
})

const goToPage = (page) => {
  if (
    page >= 1 &&
    page <= props.modelValue.lastPage &&
    page !== props.modelValue.currentPage
  ) {
    emit('update:modelValue', {
      ...props.modelValue,
      currentPage: page
    })
    emit('paginate')
  }
}
</script> 