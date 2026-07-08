<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import LogOutput from '@/Components/LogOutput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    server: Object,
    name: String,
    container: Object,
    error: String,
});

const actionForm = useForm({ action: null });
const pendingAction = ref(null);

const runAction = (action) => {
    if (action === 'start') {
        submitAction(action);
    } else {
        pendingAction.value = action;
    }
};

const submitAction = (action) => {
    actionForm.action = action;
    actionForm.post(route('container.action', [props.server, props.name]), {
        preserveScroll: true,
        onFinish: () => (pendingAction.value = null),
    });
};

const isRunning = () => props.container?.state === 'running' || props.container?.state === 'restarting';

// Logs are fetched on demand and polled while this page is open (true
// streaming is deferred — FrankenPHP classic mode).
const logs = ref('');
const tail = ref(500);
let timer = null;

const fetchLogs = async () => {
    const response = await fetch(route('container.logs', [props.server, props.name]) + `?tail=${tail.value}`, {
        headers: { Accept: 'application/json' },
    });
    if (response.ok) logs.value = (await response.json()).logs;
};

onMounted(() => {
    if (props.container) {
        fetchLogs();
        timer = setInterval(fetchLogs, 3000);
    }
});

onBeforeUnmount(() => timer && clearInterval(timer));

const fmt = (value) => (value && !value.startsWith('0001') ? new Date(value).toLocaleString() : '—');
</script>

<template>
    <AppLayout :title="name">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Docker — {{ name }}
                </h2>
                <div v-if="container" class="flex items-center gap-2 text-xs font-medium">
                    <button v-if="!isRunning()" type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 uppercase tracking-widest text-green-700 hover:bg-gray-50" @click="runAction('start')">Start</button>
                    <button v-if="isRunning()" type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 uppercase tracking-widest text-indigo-700 hover:bg-gray-50" @click="runAction('restart')">Restart</button>
                    <button v-if="isRunning()" type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 uppercase tracking-widest text-red-700 hover:bg-gray-50" @click="runAction('stop')">Stop</button>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div v-if="error || !container" class="bg-white shadow sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-red-700">Couldn't load this container.</div>
                    <pre v-if="error" class="mt-2 overflow-x-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><samp>{{ error }}</samp></pre>
                    <p v-else class="mt-1 text-sm text-gray-500">It may have been removed.</p>
                </div>

                <template v-else>
                    <div class="bg-white shadow sm:rounded-lg p-6">
                        <dl class="grid grid-cols-3 gap-y-2 text-sm">
                            <dt class="font-medium text-gray-500">State</dt>
                            <dd class="col-span-2 text-gray-800">{{ container.state }}<span v-if="!container.running && container.exit_code !== null"> (exit {{ container.exit_code }})</span></dd>
                            <dt class="font-medium text-gray-500">Image</dt>
                            <dd class="col-span-2 text-gray-800">{{ container.image }}</dd>
                            <template v-if="container.error">
                                <dt class="font-medium text-gray-500">Error</dt>
                                <dd class="col-span-2 text-red-600">{{ container.error }}</dd>
                            </template>
                            <dt class="font-medium text-gray-500">Created</dt>
                            <dd class="col-span-2 text-gray-800">{{ fmt(container.created) }}</dd>
                            <dt class="font-medium text-gray-500">Started</dt>
                            <dd class="col-span-2 text-gray-800">{{ fmt(container.started_at) }}</dd>
                            <dt class="font-medium text-gray-500">Finished</dt>
                            <dd class="col-span-2 text-gray-800">{{ fmt(container.finished_at) }}</dd>
                            <dt class="font-medium text-gray-500">Restarts</dt>
                            <dd class="col-span-2 text-gray-800">{{ container.restart_count }}</dd>
                            <dt class="font-medium text-gray-500">Restart policy</dt>
                            <dd class="col-span-2 text-gray-800">{{ container.restart_policy || 'no' }}</dd>
                        </dl>
                    </div>

                    <div class="bg-white shadow sm:rounded-lg">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                            <span class="font-medium text-gray-700">Logs</span>
                            <select v-model.number="tail" class="border-gray-300 rounded-md text-xs shadow-sm" @change="fetchLogs">
                                <option :value="200">Last 200 lines</option>
                                <option :value="500">Last 500 lines</option>
                                <option :value="2000">Last 2000 lines</option>
                            </select>
                        </div>
                        <div class="p-4">
                            <LogOutput :content="logs" placeholder="No output." max-height="max-h-[32rem]" auto-scroll />
                        </div>
                    </div>
                </template>

                <Link :href="route('docker.index', { server: server.ulid })" class="text-sm text-gray-500 hover:underline">
                    &larr; Back to Docker
                </Link>
            </div>
        </div>

        <ConfirmationModal :show="pendingAction !== null" @close="pendingAction = null">
            <template #title>
                {{ pendingAction === 'stop' ? 'Stop' : 'Restart' }} container
            </template>
            <template #content>
                {{ pendingAction === 'stop' ? 'Stop' : 'Restart' }}
                <code class="rounded bg-gray-100 px-1 text-sm">{{ name }}</code>?
                This interrupts the running service.
            </template>
            <template #footer>
                <SecondaryButton @click="pendingAction = null">Cancel</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': actionForm.processing }"
                    :disabled="actionForm.processing"
                    @click="submitAction(pendingAction)"
                >
                    {{ pendingAction === 'stop' ? 'Stop' : 'Restart' }}
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
