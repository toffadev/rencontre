<template>
    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Messages des modérateurs
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
                        >
                            <div
                                v-for="moderator in moderators"
                                :key="moderator.id"
                                class="bg-white border rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200"
                            >
                                <div class="p-6">
                                    <div class="flex items-center space-x-4">
                                        <div
                                            class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center"
                                        >
                                            <i
                                                class="fas fa-user text-primary text-xl"
                                            ></i>
                                        </div>
                                        <div>
                                            <h3
                                                class="text-lg font-medium text-gray-900"
                                            >
                                                {{ moderator.name }}
                                            </h3>
                                            <p class="text-sm text-gray-500">
                                                ID: {{ moderator.id }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-6 grid grid-cols-2 gap-4 text-center text-sm"
                                    >
                                        <div class="bg-gray-50 p-3 rounded-lg">
                                            <div
                                                class="font-medium text-gray-900"
                                            >
                                                {{ moderator.total_messages }}
                                            </div>
                                            <div class="text-gray-500">
                                                Messages
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 p-3 rounded-lg">
                                            <div
                                                class="font-medium text-gray-900"
                                            >
                                                {{ moderator.total_points }}
                                            </div>
                                            <div class="text-gray-500">
                                                Points
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-6">
                                        <Link
                                            :href="`/admin/moderators/${moderator.id}/messages`"
                                            class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                                        >
                                            Voir les messages
                                            <i
                                                class="fas fa-arrow-right ml-2"
                                            ></i>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import AdminLayout from "@/Admin/Layouts/AdminLayout.vue";

const moderators = ref([]);

const fetchModerators = async () => {
    try {
        const response = await axios.get("/admin/api/moderators");
        moderators.value = response.data.map((moderator) => ({
            ...moderator,
            total_messages: 0,
            total_points: 0,
        }));

        // Fetch statistics for each moderator
        await Promise.all(
            moderators.value.map(async (moderator) => {
                try {
                    const statsResponse = await axios.get(
                        `/admin/moderators/${moderator.id}/messages/data`
                    );
                    moderator.total_messages =
                        statsResponse.data.stats.total_messages;
                    moderator.total_points =
                        statsResponse.data.stats.total_points;
                } catch (error) {
                    console.error(
                        `Error fetching stats for moderator ${moderator.id}:`,
                        error
                    );
                }
            })
        );
    } catch (error) {
        console.error("Error fetching moderators:", error);
    }
};

onMounted(() => {
    fetchModerators();
});
</script>
