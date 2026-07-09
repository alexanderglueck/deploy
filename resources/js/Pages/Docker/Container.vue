<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import LogOutput from '@/Components/LogOutput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { echoClient } from '@/echo';

const props = defineProps({
    server: Object,
    name: String,
    container: Object,
    error: String,
});

const actionForm = useForm({ action: null });
const pendingAction = ref(null);

const IMMEDIATE = ['start', 'unpause'];
const ACTION_WORD = { stop: 'Stop', restart: 'Restart', kill: 'Kill' };

const runAction = (action) => {
    if (IMMEDIATE.includes(action)) {
        submitAction(action);
    } else {
        pendingAction.value = action;
    }
};

const submitAction = (action) => {
    actionForm.action = action;
    actionForm.post(route('container.action', [props.server, props.name]), {
        preserveScroll: true,
        onSuccess: () => (pending.value = true),
        onFinish: () => (pendingAction.value = null),
    });
};

const isRunning = () => props.container?.state === 'running' || props.container?.state === 'restarting';
const isPaused = () => props.container?.state === 'paused';

// Keep the inspect fields (state, exit code, restarts) live alongside the
// log polling below. The Reverb channel makes action outcomes instant when
// available; polling stays on as the fallback.
usePoll(5000, { only: ['container', 'error'] });

// True while an action on this container is queued or running (set
// optimistically on submit, settled by broadcasts).
const pending = ref(false);

const page = usePage();
const teamUlid = page.props.auth?.user?.current_team?.ulid;

onMounted(() => {
    const echo = echoClient(page.props.reverb);
    if (!echo || !teamUlid) return;

    echo.private(`team.${teamUlid}`).listen('.container-action.updated', (event) => {
        if (event.server !== props.server.ulid || event.action.container !== props.name) return;

        pending.value = event.action.status === 'queued' || event.action.status === 'running';

        if (!pending.value) {
            router.reload({ only: ['container', 'error'] });
        }
    });
});

onBeforeUnmount(() => {
    if (teamUlid) echoClient(page.props.reverb)?.leave(`team.${teamUlid}`);
});

// Logs are fetched on demand and polled while this page is open (true
// streaming is deferred — FrankenPHP classic mode).
const logs = ref('');
const logError = ref(null);
const tail = ref(500);
const since = ref('');
const timestamps = ref(true);
let timer = null;

const logParams = () => {
    const params = new URLSearchParams({ tail: tail.value, timestamps: timestamps.value ? 1 : 0 });
    if (since.value) params.set('since', since.value);

    return params;
};

const fetchLogs = async () => {
    try {
        const response = await fetch(route('container.logs', [props.server, props.name]) + `?${logParams()}`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;

        const data = await response.json();
        logs.value = data.logs;
        logError.value = data.error;
    } catch {
        // Transient fetch/parse failures (network blip, expired session
        // redirect) — the next poll or Inertia visit sorts it out.
    }
};

// Full current window (capped at the backend's 5000-line tail) as a file.
const downloadLogs = async () => {
    const params = logParams();
    params.set('tail', 5000);

    const response = await fetch(route('container.logs', [props.server, props.name]) + `?${params}`, {
        headers: { Accept: 'application/json' },
    });
    if (!response.ok) return;

    const data = await response.json();
    const url = URL.createObjectURL(new Blob([data.logs], { type: 'text/plain' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `${props.name}.log`;
    link.click();
    URL.revokeObjectURL(url);
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
                <div v-if="container && pending" class="text-xs font-medium uppercase tracking-widest text-gray-400">
                    working…
                </div>
                <div v-else-if="container" class="flex items-center gap-2 text-xs font-medium">
                    <button v-if="isPaused()" type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 uppercase tracking-widest text-green-700 hover:bg-gray-50" @click="runAction('unpause')">Unpause</button>
                    <button v-else-if="!isRunning()" type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 uppercase tracking-widest text-green-700 hover:bg-gray-50" @click="runAction('start')">Start</button>
                    <button v-if="isRunning()" type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 uppercase tracking-widest text-indigo-700 hover:bg-gray-50" @click="runAction('restart')">Restart</button>
                    <button v-if="isRunning()" type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 uppercase tracking-widest text-red-700 hover:bg-gray-50" @click="runAction('stop')">Stop</button>
                    <button v-if="isRunning() || isPaused()" type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 uppercase tracking-widest text-red-700 hover:bg-gray-50" @click="runAction('kill')">Kill</button>
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
                            <dd class="col-span-2 text-gray-800">
                                {{ container.state }}<span v-if="!container.running && container.exit_code !== null"> (exit {{ container.exit_code }})</span>
                                <span v-if="container.health" :class="container.health === 'unhealthy' ? 'text-red-600' : 'text-gray-500'"> — {{ container.health }}</span>
                            </dd>
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
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-200">
                            <span class="font-medium text-gray-700">Logs</span>
                            <div class="flex flex-wrap items-center gap-3">
                                <select v-model="since" class="border-gray-300 rounded-md text-xs shadow-sm" @change="fetchLogs">
                                    <option value="">All time</option>
                                    <option value="15m">Last 15 minutes</option>
                                    <option value="1h">Last hour</option>
                                    <option value="6h">Last 6 hours</option>
                                    <option value="24h">Last 24 hours</option>
                                </select>
                                <select v-model.number="tail" class="border-gray-300 rounded-md text-xs shadow-sm" @change="fetchLogs">
                                    <option :value="200">Last 200 lines</option>
                                    <option :value="500">Last 500 lines</option>
                                    <option :value="2000">Last 2000 lines</option>
                                </select>
                                <label class="flex items-center gap-1.5 text-xs text-gray-500">
                                    <input v-model="timestamps" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @change="fetchLogs">
                                    Timestamps
                                </label>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="downloadLogs">
                                    Download
                                </button>
                            </div>
                        </div>
                        <div class="p-4">
                            <div v-if="logError" class="mb-2 rounded bg-red-50 px-3 py-2 text-xs text-red-700">
                                {{ logError }}
                            </div>
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
                {{ ACTION_WORD[pendingAction] }} container
            </template>
            <template #content>
                {{ ACTION_WORD[pendingAction] }}
                <code class="rounded bg-gray-100 px-1 text-sm">{{ name }}</code>?
                <template v-if="pendingAction === 'kill'">
                    This sends SIGKILL immediately — no graceful shutdown.
                </template>
                <template v-else>
                    This interrupts the running service.
                </template>
            </template>
            <template #footer>
                <SecondaryButton @click="pendingAction = null">Cancel</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': actionForm.processing }"
                    :disabled="actionForm.processing"
                    @click="submitAction(pendingAction)"
                >
                    {{ ACTION_WORD[pendingAction] }}
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
