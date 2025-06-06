<template>
  <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6">
      <div v-if="title" class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-medium text-gray-900">{{ title }}</h3>
        <slot name="actions"></slot>
      </div>
      <slot></slot>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    title: {
      type: String,
      required: false,
      default: ''
    }
  }
}
</script> 