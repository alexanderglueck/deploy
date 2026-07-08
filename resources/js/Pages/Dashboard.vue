<script setup>
import { computed, watch } from 'vue';
import { Link, usePoll } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    team: Object,
    projects: Array,
    recentDeployments: Array,
    currentDeployments: Array,
});

const deployUrl = (endpoint) => route('api.deployment.store', endpoint);

// While deployments are running, keep the dashboard lists live.
const hasCurrent = computed(() => props.currentDeployments.length > 0);

const { start, stop } = usePoll(
    5000,
    { only: ['currentDeployments', 'recentDeployments'] },
    { autoStart: false },
);

watch(hasCurrent, (active) => {
    active ? start() : stop();
}, { immediate: true });
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ team.name }} — Dashboard
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Current deployments -->
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-700 flex items-center justify-between">
                        <span>Current deployments</span>
                        <span v-if="hasCurrent" class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-700">
                            <span class="relative flex size-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75" />
                                <span class="relative inline-flex size-2 rounded-full bg-blue-500" />
                            </span>
                            Live
                        </span>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="deployment in currentDeployments" :key="deployment.ulid">
                            <Link :href="route('project.show', deployment.project)" class="block px-4 py-3 hover:bg-gray-50">
                                <span class="font-medium text-gray-800">{{ deployment.project.name }}</span>
                                <span class="text-gray-400 text-sm"> ({{ deployUrl(deployment.project.deploy_endpoint) }})</span>
                            </Link>
                        </li>
                        <li v-if="!currentDeployments.length" class="px-4 py-3 text-sm text-gray-400">
                            No deployments are currently running.
                        </li>
                    </ul>
                </div>

                <!-- Recent deployments -->
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-700">
                        Recent deployments
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="deployment in recentDeployments" :key="deployment.ulid">
                            <Link :href="route('project.show', deployment.project)" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
                                <span>
                                    <span class="font-medium text-gray-800">{{ deployment.project.name }}</span>
                                    <span class="text-gray-400 text-sm"> {{ deployment.ref }}</span>
                                </span>
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="deployment.status === 'deployed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                >
                                    {{ deployment.status === 'deployed' ? 'Deployed' : 'Failed' }}
                                </span>
                            </Link>
                        </li>
                        <li v-if="!recentDeployments.length" class="px-4 py-3 text-sm text-gray-400">
                            No recent deployments yet.
                        </li>
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
