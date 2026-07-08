<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import LogOutput from '@/Components/LogOutput.vue';

defineProps({
    name: String,
    content: String,
    truncated: Boolean,
    modifiedAt: String,
});
</script>

<template>
    <AppLayout :title="name">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Legacy logs — {{ name }}
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="text-sm text-gray-500">
                    Last modified {{ new Date(modifiedAt).toLocaleString() }}
                    <span v-if="truncated"> · showing the last 512 KB</span>
                </div>

                <LogOutput :content="content" placeholder="Empty log." />

                <Link :href="route('legacy-log.index')" class="text-sm text-gray-500 hover:underline">
                    &larr; Back to legacy logs
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
