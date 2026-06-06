<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    team: Object,
    servers: Array,
});
</script>

<template>
    <AppLayout :title="`${team.name} — Servers`">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ team.name }} — Servers
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
            </div>
        </div>
    </AppLayout>
</template>
