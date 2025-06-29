 <template>
    <div class="bg-white p-4 rounded-lg shadow">
        <h2 class="text-xl font-bold mb-4">Outil de diagnostic</h2>
        
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">État WebSocket</h3>
            <div class="flex items-center">
                <div :class="`w-4 h-4 rounded-full mr-2 ${webSocketStatusColor}`"></div>
                <span>{{ webSocketStatus }}</span>
                <button 
                    @click="reconnectWebSocket" 
                    class="ml-4 bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600"
                >
                    Reconnecter
                </button>
            </div>
        </div>
        
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">État des modérateurs</h3>
            <button 
                @click="refreshDiagnosticData" 
                class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 mb-2"
            >
                Rafraîchir les données
            </button>
            
            <div v-if="loading" class="text-center py-4">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                <p class="mt-2 text-gray-600">Chargement des données...</p>
            </div>
            
            <div v-else-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <p>{{ error }}</p>
            </div>
            
            <div v-else>
                <div v-if="diagnosticData && diagnosticData.moderators && diagnosticData.moderators.length > 0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="py-2 px-4 text-left">Modérateur</th>
                                    <th class="py-2 px-4 text-left">En ligne</th>
                                    <th class="py-2 px-4 text-left">Inactif</th>
                                    <th class="py-2 px-4 text-left">Profils assignés</th>
                                    <th class="py-2 px-4 text-left">Dernière activité</th>
                                    <th class="py-2 px-4 text-left">Messages en attente</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="moderator in diagnosticData.moderators" :key="moderator.moderator_id" class="border-t">
                                    <td class="py-2 px-4">{{ moderator.moderator_name }}</td>
                                    <td class="py-2 px-4">
                                        <div :class="`w-3 h-3 rounded-full ${moderator.is_online ? 'bg-green-500' : 'bg-red-500'}`"></div>
                                    </td>
                                    <td class="py-2 px-4">
                                        <div :class="`w-3 h-3 rounded-full ${moderator.is_inactive ? 'bg-yellow-500' : 'bg-green-500'}`"></div>
                                    </td>
                                    <td class="py-2 px-4">
                                        <div v-for="assignment in moderator.active_assignments" :key="assignment.assignment_id" class="mb-1">
                                            <span :class="{'font-bold': assignment.is_primary}">
                                                {{ assignment.profile_name }} (ID: {{ assignment.profile_id }})
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4">
                                        <div v-for="assignment in moderator.active_assignments" :key="assignment.assignment_id" class="mb-1">
                                            {{ assignment.last_activity }}
                                        </div>
                                    </td>
                                    <td class="py-2 px-4">
                                        <span v-if="moderator.profiles_with_pending_messages.length > 0" class="text-red-600 font-bold">
                                            {{ moderator.profiles_with_pending_messages.join(', ') }}
                                        </span>
                                        <span v-else class="text-green-600">Aucun</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-else class="text-gray-500 text-center py-4">
                    Aucune donnée disponible
                </div>
            </div>
        </div>
        
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">Profils avec messages en attente</h3>
            <div v-if="diagnosticData && diagnosticData.pending_profiles">
                <div class="bg-yellow-100 p-3 rounded mb-2">
                    <p>Nombre de profils avec messages en attente: <span class="font-bold">{{ diagnosticData.pending_profiles.count }}</span></p>
                </div>
                <div v-if="diagnosticData.pending_profiles.profiles && diagnosticData.pending_profiles.profiles.length > 0">
                    <ul class="list-disc list-inside">
                        <li v-for="profileId in diagnosticData.pending_profiles.profiles" :key="profileId">
                            Profil ID: {{ profileId }}
                        </li>
                    </ul>
                </div>
            </div>
            <div v-else class="text-gray-500">
                Aucun profil avec des messages en attente
            </div>
        </div>
        
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-2">Actions</h3>
            <div class="flex space-x-2">
                <button 
                    @click="triggerProfileReassignment" 
                    class="bg-purple-500 text-white px-3 py-1 rounded hover:bg-purple-600"
                >
                    Forcer la réattribution des profils
                </button>
                <button 
                    @click="clearWebSocketConnections" 
                    class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600"
                >
                    Nettoyer les connexions WebSocket
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { useWebSocketHealth } from '../../../../composables/useWebSocketHealth';
import axios from 'axios';

export default {
    name: 'DiagnosticTool',
    
    setup() {
        const { status, reconnect } = useWebSocketHealth();
        return { status, reconnect };
    },
    
    data() {
        return {
            diagnosticData: null,
            loading: false,
            error: null,
            webSocketStatus: 'Vérification...'
        };
    },
    
    computed: {
        webSocketStatusColor() {
            if (this.status === 'connected') return 'bg-green-500';
            if (this.status === 'connecting') return 'bg-yellow-500';
            if (this.status === 'disconnected') return 'bg-red-500';
            return 'bg-gray-500';
        }
    },
    
    mounted() {
        this.refreshDiagnosticData();
        this.checkWebSocketStatus();
        
        // Rafraîchir les données toutes les 30 secondes
        this.refreshInterval = setInterval(() => {
            this.refreshDiagnosticData();
            this.checkWebSocketStatus();
        }, 30000);
    },
    
    beforeUnmount() {
        clearInterval(this.refreshInterval);
    },
    
    methods: {
        async refreshDiagnosticData() {
            this.loading = true;
            this.error = null;
            
            try {
                const response = await axios.get('/api/moderateur/diagnostic');
                this.diagnosticData = response.data;
            } catch (error) {
                console.error('Erreur lors de la récupération des données de diagnostic:', error);
                this.error = 'Erreur lors de la récupération des données de diagnostic. Veuillez réessayer.';
            } finally {
                this.loading = false;
            }
        },
        
        async checkWebSocketStatus() {
            try {
                const response = await axios.get('/api/websocket/health');
                if (response.data.success) {
                    this.webSocketStatus = response.data.status === 'healthy' ? 'Connecté' : 'Problème de connexion';
                } else {
                    this.webSocketStatus = 'Déconnecté';
                }
            } catch (error) {
                console.error('Erreur lors de la vérification du statut WebSocket:', error);
                this.webSocketStatus = 'Erreur de vérification';
            }
        },
        
        reconnectWebSocket() {
            this.reconnect();
            setTimeout(() => {
                this.checkWebSocketStatus();
            }, 1000);
        },
        
        async triggerProfileReassignment() {
            try {
                const response = await axios.post('/debug/process-messages');
                alert('Réattribution des profils déclenchée. Vérifiez les logs pour plus de détails.');
                setTimeout(() => {
                    this.refreshDiagnosticData();
                }, 2000);
            } catch (error) {
                console.error('Erreur lors du déclenchement de la réattribution des profils:', error);
                alert('Erreur lors du déclenchement de la réattribution des profils.');
            }
        },
        
        async clearWebSocketConnections() {
            if (confirm('Êtes-vous sûr de vouloir nettoyer toutes les connexions WebSocket ? Cela pourrait déconnecter des utilisateurs.')) {
                try {
                    const response = await axios.post('/api/websocket/cleanup');
                    alert('Nettoyage des connexions WebSocket effectué avec succès.');
                    setTimeout(() => {
                        this.checkWebSocketStatus();
                    }, 1000);
                } catch (error) {
                    console.error('Erreur lors du nettoyage des connexions WebSocket:', error);
                    alert('Erreur lors du nettoyage des connexions WebSocket.');
                }
            }
        }
    }
};
</script>