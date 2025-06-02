<template>
  <div class="fixed inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Overlay de fond -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="$emit('close')"></div>

      <!-- Centrage du modal -->
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Contenu du modal -->
      <div class="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
        <!-- Header -->
        <div class="sm:flex sm:items-start mb-4">
          <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
              <slot name="title"></slot>
            </h3>
          </div>
          <button 
            type="button" 
            class="absolute top-4 right-4 text-gray-400 hover:text-gray-500"
            @click="$emit('close')"
          >
            <span class="sr-only">Fermer</span>
            <i class="fas fa-times"></i>
          </button>
        </div>

        <!-- Contenu -->
        <div class="mt-2">
          <slot name="content"></slot>
        </div>

        <!-- Footer -->
        <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
          <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineEmits(['close'])
</script> 