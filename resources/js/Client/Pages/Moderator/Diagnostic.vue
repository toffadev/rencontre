<template>
    <MainLayout>
        <div class="container mx-auto px-4 py-8">
            <h1 class="text-2xl font-bold mb-6">Diagnostic du système</h1>

            <div class="grid grid-cols-1 gap-6">
                <DiagnosticTool />
            </div>
        </div>
    </MainLayout>
</template>

<script>
import MainLayout from "../../Layouts/MainLayout.vue";
import DiagnosticTool from "./Components/DiagnosticTool.vue";

export default {
    name: "ModeratorDiagnostic",

    components: {
        MainLayout,
        DiagnosticTool,
    },
};
</script>
