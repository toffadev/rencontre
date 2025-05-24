<template>
    <AdminLayout>
        <!-- Notification -->
        <div
            v-if="showNotification"
            :class="[
                'fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50',
                notificationType === 'success'
                    ? 'bg-green-500 text-white'
                    : 'bg-red-500 text-white',
            ]"
        >
            {{ notificationMessage }}
        </div>

        <!-- Modal de confirmation de suppression -->
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div
                class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0"
            >
                <div
                    class="fixed inset-0 transition-opacity"
                    aria-hidden="true"
                >
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span
                    class="hidden sm:inline-block sm:align-middle sm:h-screen"
                    aria-hidden="true"
                    >&#8203;</span
                >

                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                >
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"
                            >
                                <i
                                    class="fas fa-exclamation-triangle text-red-600"
                                ></i>
                            </div>
                            <div
                                class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left"
                            >
                                <h3
                                    class="text-lg leading-6 font-medium text-gray-900"
                                >
                                    Confirmer la suppression
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Êtes-vous sûr de vouloir supprimer cet
                                        utilisateur ? Cette action est
                                        irréversible.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"
                    >
                        <button
                            type="button"
                            @click="confirmDelete"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Supprimer
                        </button>
                        <button
                            type="button"
                            @click="showDeleteModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Annuler
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Bar -->
        <div
            class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0"
        >
            <div class="relative w-full md:w-64">
                <div
                    class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                >
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input
                    type="text"
                    v-model="searchQuery"
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                    placeholder="Rechercher un utilisateur..."
                />
            </div>

            <div class="flex space-x-3">
                <div class="relative">
                    <button
                        @click="showFilters = !showFilters"
                        class="flex items-center space-x-2 px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                        <i class="fas fa-filter"></i>
                        <span>Filtrer</span>
                        <i
                            class="fas"
                            :class="
                                showFilters
                                    ? 'fa-chevron-up'
                                    : 'fa-chevron-down'
                            "
                        ></i>
                    </button>

                    <!-- Filters Dropdown -->
                    <div
                        v-if="showFilters"
                        class="absolute right-0 mt-2 w-56 origin-top-right bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-10"
                    >
                        <div class="py-1">
                            <div class="px-4 py-2 border-b border-gray-100">
                                <label
                                    class="text-xs font-medium text-gray-500 uppercase"
                                    >Type</label
                                >
                                <select
                                    v-model="filters.type"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
                                >
                                    <option value="">Tous</option>
                                    <option value="moderateur">
                                        Modérateur
                                    </option>
                                    <option value="admin">
                                        Administrateur
                                    </option>
                                </select>
                            </div>
                            <div class="px-4 py-2 border-b border-gray-100">
                                <label
                                    class="text-xs font-medium text-gray-500 uppercase"
                                    >Statut</label
                                >
                                <select
                                    v-model="filters.status"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
                                >
                                    <option value="">Tous</option>
                                    <option value="active">Actif</option>
                                    <option value="inactive">Inactif</option>
                                    <option value="banned">Banni</option>
                                </select>
                            </div>
                            <div class="px-4 py-2">
                                <button
                                    @click="applyFilters"
                                    class="w-full bg-primary text-white py-2 px-4 rounded-md text-sm hover:bg-opacity-90 transition duration-150"
                                >
                                    Appliquer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    @click="showAddForm = true"
                    class="flex items-center space-x-2 px-4 py-2 bg-primary text-white rounded-md shadow-sm text-sm font-medium hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-primary"
                >
                    <i class="fas fa-plus"></i>
                    <span>Ajouter un utilisateur</span>
                </button>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">
                            Total utilisateurs
                        </p>
                        <h3 class="text-2xl font-bold text-dark">
                            {{ stats.total }}
                        </h3>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100 text-secondary">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">
                            Modérateurs
                        </p>
                        <h3 class="text-2xl font-bold text-dark">
                            {{ stats.moderateurs }}
                        </h3>
                    </div>
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">
                            Administrateurs
                        </p>
                        <h3 class="text-2xl font-bold text-dark">
                            {{ stats.admins }}
                        </h3>
                    </div>
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-user-cog text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div
            v-if="!showAddForm && !editingUser"
            class="bg-white shadow-sm rounded-lg overflow-hidden"
        >
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Nom
                        </th>
                        <th
                            scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Email
                        </th>
                        <th
                            scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Type
                        </th>
                        <th
                            scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Statut
                        </th>
                        <th
                            scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="user in filteredUsers" :key="user.id">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                {{ user.name }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                {{ user.email }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                {{ formatType(user.type) }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                :class="[
                                    'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                    user.status === 'active'
                                        ? 'bg-green-100 text-green-800'
                                        : user.status === 'inactive'
                                        ? 'bg-yellow-100 text-yellow-800'
                                        : 'bg-red-100 text-red-800',
                                ]"
                            >
                                {{ formatStatus(user.status) }}
                            </span>
                        </td>
                        <td
                            class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                        >
                            <button
                                @click="editUser(user)"
                                class="text-primary hover:text-primary-dark mr-3"
                            >
                                <i class="fas fa-edit"></i>
                            </button>
                            <button
                                @click="deleteUser(user.id)"
                                class="text-red-600 hover:text-red-900"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add/Edit User Form -->
        <div
            v-if="showAddForm || editingUser"
            class="bg-white shadow-sm rounded-lg p-6"
        >
            <h2 class="text-lg font-medium text-gray-900 mb-6">
                {{
                    editingUser
                        ? "Modifier l'utilisateur"
                        : "Ajouter un utilisateur"
                }}
            </h2>

            <form @submit.prevent="handleFormSubmit">
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label
                                for="name"
                                class="block text-sm font-medium text-gray-700"
                                >Nom</label
                            >
                            <input
                                type="text"
                                id="name"
                                v-model="form.name"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                                required
                            />
                        </div>

                        <div>
                            <label
                                for="email"
                                class="block text-sm font-medium text-gray-700"
                                >Email</label
                            >
                            <input
                                type="email"
                                id="email"
                                v-model="form.email"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                                required
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label
                                for="password"
                                class="block text-sm font-medium text-gray-700"
                            >
                                {{
                                    editingUser
                                        ? "Nouveau mot de passe (laisser vide pour ne pas changer)"
                                        : "Mot de passe"
                                }}
                            </label>
                            <input
                                type="password"
                                id="password"
                                v-model="form.password"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                                :required="!editingUser"
                            />
                        </div>

                        <div>
                            <label
                                for="type"
                                class="block text-sm font-medium text-gray-700"
                                >Type</label
                            >
                            <select
                                id="type"
                                v-model="form.type"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                                required
                            >
                                <option value="moderateur">Modérateur</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label
                            for="status"
                            class="block text-sm font-medium text-gray-700"
                            >Statut</label
                        >
                        <select
                            id="status"
                            v-model="form.status"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                            required
                        >
                            <option value="active">Actif</option>
                            <option value="inactive">Inactif</option>
                            <option value="banned">Banni</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex items-center space-x-3">
                    <button
                        type="submit"
                        class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                        {{ editingUser ? "Mettre à jour" : "Ajouter" }}
                    </button>
                    <button
                        type="button"
                        @click="cancelForm"
                        class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500"
                    >
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import AdminLayout from "../Layouts/AdminLayout.vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    users: {
        type: Array,
        required: true,
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
});

// Search and filters
const searchQuery = ref("");
const showFilters = ref(false);
const filters = ref({
    type: "",
    status: "",
});

// Form display control
const showAddForm = ref(false);
const editingUser = ref(null);

// Form data
const form = ref({
    name: "",
    email: "",
    password: "",
    type: "moderateur",
    status: "active",
});

// Notifications
const showNotification = ref(false);
const notificationMessage = ref("");
const notificationType = ref("success");

// Delete modal
const showDeleteModal = ref(false);
const userToDelete = ref(null);

// Computed properties
const filteredUsers = computed(() => {
    let result = [...props.users];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (user) =>
                user.name.toLowerCase().includes(query) ||
                user.email.toLowerCase().includes(query)
        );
    }

    if (filters.value.type) {
        result = result.filter((user) => user.type === filters.value.type);
    }

    if (filters.value.status) {
        result = result.filter((user) => user.status === filters.value.status);
    }

    return result;
});

const stats = computed(() => {
    return {
        total: props.users.length,
        moderateurs: props.users.filter((u) => u.type === "moderateur").length,
        admins: props.users.filter((u) => u.type === "admin").length,
    };
});

// Methods
const formatType = (type) => {
    const types = {
        moderateur: "Modérateur",
        admin: "Administrateur",
    };
    return types[type] || type;
};

const formatStatus = (status) => {
    const statuses = {
        active: "Actif",
        inactive: "Inactif",
        banned: "Banni",
    };
    return statuses[status] || status;
};

const applyFilters = () => {
    showFilters.value = false;
};

const editUser = (user) => {
    editingUser.value = user;
    form.value = {
        name: user.name,
        email: user.email,
        password: "",
        type: user.type,
        status: user.status,
    };
};

const deleteUser = (id) => {
    userToDelete.value = id;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (userToDelete.value) {
        router.delete(`/admin/users/${userToDelete.value}`, {
            onSuccess: () => {
                notificationMessage.value = "Utilisateur supprimé avec succès";
                notificationType.value = "success";
                showNotification.value = true;
                setTimeout(() => (showNotification.value = false), 3000);
            },
            onError: (errors) => {
                notificationMessage.value = Object.values(errors)[0];
                notificationType.value = "error";
                showNotification.value = true;
                setTimeout(() => (showNotification.value = false), 3000);
            },
        });
    }
    showDeleteModal.value = false;
    userToDelete.value = null;
};

const handleFormSubmit = () => {
    if (editingUser.value) {
        router.put(`/admin/users/${editingUser.value.id}`, form.value, {
            onSuccess: () => {
                notificationMessage.value =
                    "Utilisateur mis à jour avec succès";
                notificationType.value = "success";
                showNotification.value = true;
                setTimeout(() => (showNotification.value = false), 3000);
                cancelForm();
            },
            onError: (errors) => {
                notificationMessage.value = Object.values(errors)[0];
                notificationType.value = "error";
                showNotification.value = true;
                setTimeout(() => (showNotification.value = false), 3000);
            },
        });
    } else {
        router.post("/admin/users", form.value, {
            onSuccess: () => {
                notificationMessage.value = "Utilisateur créé avec succès";
                notificationType.value = "success";
                showNotification.value = true;
                setTimeout(() => (showNotification.value = false), 3000);
                cancelForm();
            },
            onError: (errors) => {
                notificationMessage.value = Object.values(errors)[0];
                notificationType.value = "error";
                showNotification.value = true;
                setTimeout(() => (showNotification.value = false), 3000);
            },
        });
    }
};

const cancelForm = () => {
    showAddForm.value = false;
    editingUser.value = null;
    form.value = {
        name: "",
        email: "",
        password: "",
        type: "moderateur",
        status: "active",
    };
};
</script>
