<template>
  <div v-if="mounted && clientId" class="bg-white rounded-xl shadow-md mb-4 overflow-hidden">
    <div class="overflow-x-auto">
      <div class="flex p-3 space-x-3 whitespace-nowrap">
        <template v-if="basicInfo">
          <div v-if="basicInfo.age" 
               class="inline-flex items-center px-3 py-1 rounded-full bg-pink-50 text-pink-600">
            <i class="fas fa-birthday-cake mr-2"></i>
            {{ basicInfo.age }} ans
          </div>

          <div v-if="basicInfo.ville" 
               class="inline-flex items-center px-3 py-1 rounded-full bg-blue-50 text-blue-600">
            <i class="fas fa-map-marker-alt mr-2"></i>
            {{ basicInfo.ville }}
          </div>

          <div v-if="basicInfo.profession" 
               class="inline-flex items-center px-3 py-1 rounded-full bg-green-50 text-green-600">
            <i class="fas fa-briefcase mr-2"></i>
            {{ basicInfo.profession }}
          </div>

          <div v-if="basicInfo.celibataire" 
               class="inline-flex items-center px-3 py-1 rounded-full bg-purple-50 text-purple-600">
            <i class="fas fa-heart mr-2"></i>
            {{ basicInfo.celibataire === "oui" ? "Célibataire" : "En couple" }}
          </div>
        </template>
      </div>
    </div>

    <div class="border-t border-gray-100 p-2 flex justify-center">
      <button @click="$emit('edit')" 
              class="text-sm text-pink-600 flex items-center">
        <i class="fas fa-edit mr-2"></i>
        Voir/Modifier toutes les informations
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({
  clientId: {
    type: [Number, String],
    required: true,
  },
});

const basicInfo = ref(null);
const mounted = ref(false);

const loadBasicInfo = async () => {
  try {
    const response = await axios.get(`/moderateur/clients/${props.clientId}/info`);
    basicInfo.value = response.data.basic_info;
  } catch (error) {
    console.error("Erreur lors du chargement des informations:", error);
    basicInfo.value = null;
  }
};

onMounted(() => {
  mounted.value = true;
  if (props.clientId) {
    loadBasicInfo();
  }
});

onBeforeUnmount(() => {
  mounted.value = false;
  basicInfo.value = null;
});
</script>

<style scoped>
.overflow-x-auto {
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.overflow-x-auto::-webkit-scrollbar {
  display: none;
}
</style> 