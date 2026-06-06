<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    team: Object,
    servers: Array,
    projects: Array,
});

const deployUrl = (endpoint) => route('api.deployment.store', endpoint);
</script>

<template>
    <AppLayout :title="team.name">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ team.name }}
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Servers -->
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <span class="font-medium text-gray-700">Servers</span>
                        <Link
                            :href="route('server.create')"
                            class="inline-flex items-center px-3 py-1.5 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition"
                        >
                            Create server
                        </Link>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="server in servers" :key="server.ulid">
                            <Link :href="route('server.show', server)" class="block px-4 py-3 hover:bg-gray-50 text-gray-800">
                                {{ server.name }}
                            </Link>
                        </li>
                        <li v-if="!servers.length" class="px-4 py-3 text-sm text-gray-400">No servers yet.</li>
                    </ul>
                </div>

                <!-- Projects -->
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <span class="font-medium text-gray-700">Projects</span>
                        <Link
                            :href="route('project.create')"
                            class="inline-flex items-center px-3 py-1.5 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition"
                        >
                            Create project
                        </Link>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="project in projects" :key="project.ulid">
                            <Link :href="route('project.show', project)" class="block px-4 py-3 hover:bg-gray-50">
                                <span class="font-medium text-gray-800">{{ project.name }}</span>
                                <span class="text-gray-400 text-sm"> ({{ deployUrl(project.deploy_endpoint) }})</span>
                            </Link>
                        </li>
                        <li v-if="!projects.length" class="px-4 py-3 text-sm text-gray-400">No projects yet.</li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
