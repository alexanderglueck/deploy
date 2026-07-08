<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    logs: Array,
});

const fmt = (value) => new Date(value).toLocaleString();

const fmtSize = (bytes) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};
</script>

<template>
    <AppLayout title="Legacy logs">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Legacy deploy logs
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 text-sm text-gray-500">
                        Logs written by the external deploy hook (read-only).
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="log in logs" :key="log.name">
                            <Link :href="route('legacy-log.show', log.name)" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
                                <span class="font-medium text-gray-800">{{ log.name }}</span>
                                <span class="text-xs text-gray-400">{{ fmtSize(log.size) }} · {{ fmt(log.modified_at) }}</span>
                            </Link>
                        </li>
                        <li v-if="!logs.length" class="px-4 py-3 text-sm text-gray-400">No logs found.</li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
