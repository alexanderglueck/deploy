<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    project: Object,
    workflow: Object,
    eventLabel: String,
});
</script>

<template>
    <AppLayout :title="`Workflow ${workflow.ulid}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ project.name }} — Workflow
                </h2>
                <Link
                    :href="route('workflow.edit', [project, workflow])"
                    class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition"
                >
                    Edit
                </Link>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <dl class="grid grid-cols-3 gap-y-3 text-sm">
                        <dt class="font-medium text-gray-500">Event</dt>
                        <dd class="col-span-2">
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                                {{ eventLabel }}
                            </span>
                        </dd>

                        <dt class="font-medium text-gray-500">Server</dt>
                        <dd class="col-span-2 text-gray-800">
                            <Link
                                v-if="workflow.server"
                                :href="route('server.show', workflow.server)"
                                class="text-indigo-600 hover:underline"
                            >
                                {{ workflow.server.name }}
                            </Link>
                            <span v-else class="text-gray-400">—</span>
                        </dd>

                        <dt class="font-medium text-gray-500">Created</dt>
                        <dd class="col-span-2 text-gray-800">{{ workflow.created_at }}</dd>

                        <dt class="font-medium text-gray-500">Updated</dt>
                        <dd class="col-span-2 text-gray-800">{{ workflow.updated_at }}</dd>
                    </dl>
                </div>

                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-700">
                        Actions
                    </div>
                    <pre class="text-gray-100 bg-gray-900 p-4 overflow-x-auto text-sm leading-relaxed"><samp>{{ workflow.actions }}</samp></pre>
                </div>

                <div>
                    <Link :href="route('project.show', project)" class="text-sm text-gray-500 hover:underline">
                        &larr; Back to {{ project.name }}
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
