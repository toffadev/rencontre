<template>
  <div v-if="visible" class="fixed bottom-4 right-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-lg z-50 max-w-md">
    <div class="flex items-center">
      <div class="mr-3">
        <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <div>
        <p class="font-bold">Alerte d'inactivité</p>
        <p class="text-sm">
          Vous serez déconnecté du profil {{ profileName }} dans {{ remainingSeconds }} secondes en raison d'inactivité.
        </p>
      </div>
    </div>
    <div class="mt-3 flex justify-end space-x-2">
      <button @click="extendTimeout" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
        Prolonger (2 min)
      </button>
      <button @click="acknowledge" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-3 py-1 rounded text-sm">
        J'ai compris
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  profileId: {
    type: Number,
    required: true
  },
  profileName: {
    type: String,
    default: ''
  },
  initialSeconds: {
    type: Number,
    default: 30
  }
});

const emit = defineEmits(['timeout-extended', 'timeout-acknowledged', 'timeout-expired']);

const visible = ref(false);
const remainingSeconds = ref(props.initialSeconds);
let countdownInterval = null;

// Démarrer le compte à rebours
const startCountdown = () => {
  visible.value = true;
  remainingSeconds.value = props.initialSeconds;
  
  if (countdownInterval) clearInterval(countdownInterval);
  
  countdownInterval = setInterval(() => {
    remainingSeconds.value--;
    
    if (remainingSeconds.value <= 0) {
      clearInterval(countdownInterval);
      visible.value = false;
      emit('timeout-expired');
    }
  }, 1000);
};

// Prolonger le délai d'inactivité
const extendTimeout = async () => {
  try {
    const response = await axios.post('/moderateur/inactivity-timeout', {
      profile_id: props.profileId,
      action: 'extend',
      duration: 2 // 2 minutes
    });
    
    if (response.data.success) {
      visible.value = false;
      clearInterval(countdownInterval);
      emit('timeout-extended');
    }
  } catch (error) {
    console.error('Erreur lors de la prolongation du délai:', error);
  }
};

// Accuser réception de l'alerte
const acknowledge = () => {
  visible.value = false;
  clearInterval(countdownInterval);
  
  axios.post('/moderateur/inactivity-timeout', {
    profile_id: props.profileId,
    action: 'acknowledge'
  }).catch(error => {
    console.error('Erreur lors de l\'accusé de réception:', error);
  });
  
  emit('timeout-acknowledged');
};

// Écouter les événements d'alerte d'inactivité
const handleInactivityWarning = (event) => {
  if (event.detail.profileId === props.profileId) {
    remainingSeconds.value = event.detail.remainingSeconds;
    startCountdown();
  }
};

onMounted(() => {
  window.addEventListener('show-inactivity-warning', handleInactivityWarning);
});

onUnmounted(() => {
  window.removeEventListener('show-inactivity-warning', handleInactivityWarning);
  if (countdownInterval) clearInterval(countdownInterval);
});

defineExpose({
  visible,
  remainingSeconds,
  extendTimeout,
  acknowledge
});
</script>