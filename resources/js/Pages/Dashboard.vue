<script setup>
import { computed, watch } from 'vue';
import { Link, usePoll } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    teams: Array,
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
                Dashboard
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
                        <li v-for="deployment in currentDeployments" :key="deployment.id">
                            <Link
                                :href="route('project.show', [deployment.project.team, deployment.project])"
                                class="block px-4 py-3 hover:bg-gray-50"
                            >
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
                        <li v-for="deployment in recentDeployments" :key="deployment.id">
                            <Link
                                :href="route('project.show', [deployment.project.team, deployment.project])"
                                class="block px-4 py-3 hover:bg-gray-50"
                            >
                                <span class="font-medium text-gray-800">{{ deployment.project.name }}</span>
                                <span class="text-gray-400 text-sm"> ({{ deployUrl(deployment.project.deploy_endpoint) }})</span>
                            </Link>
                        </li>
                        <li v-if="!recentDeployments.length" class="px-4 py-3 text-sm text-gray-400">
                            No recent deployments yet.
                        </li>
                    </ul>
                </div>

                <!-- Teams -->
                <div v-for="team in teams" :key="team.id" class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-700">
                        <Link :href="route('team.show', team)" class="hover:underline">
                            {{ team.name }}
                        </Link>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="project in team.projects" :key="project.id">
                            <Link
                                :href="route('project.show', [team, project])"
                                class="block px-4 py-3 hover:bg-gray-50"
                            >
                                <span class="font-medium text-gray-800">{{ project.name }}</span>
                                <span class="text-gray-400 text-sm"> ({{ deployUrl(project.deploy_endpoint) }})</span>
                            </Link>
                        </li>
                        <li v-if="!team.projects.length" class="px-4 py-3 text-sm text-gray-400">
                            No projects yet.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
