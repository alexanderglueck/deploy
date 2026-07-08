<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePoll } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    servers: Array,
    server: Object,
    containers: Array,
    images: Array,
    error: String,
});

const actionForm = useForm({ action: null });
const pendingAction = ref(null); // { container, action }

const selectServer = (ulid) => {
    router.get(route('docker.index'), { server: ulid }, { preserveState: false });
};

// A stopped/created container starts; a running/restarting one restarts or
// stops. Start is immediate; stop and restart cause downtime, so confirm.
const runAction = (container, action) => {
    if (action === 'start') {
        submitAction(container, action);
    } else {
        pendingAction.value = { container, action };
    }
};

const submitAction = (container, action) => {
    actionForm.action = action;
    actionForm.post(route('container.action', [props.server, container.name]), {
        preserveScroll: true,
        onFinish: () => (pendingAction.value = null),
    });
};

const STATE_CLASS = {
    running: 'bg-green-100 text-green-700',
    restarting: 'bg-amber-100 text-amber-700',
    paused: 'bg-amber-100 text-amber-700',
    created: 'bg-gray-100 text-gray-500',
    exited: 'bg-gray-200 text-gray-600',
    dead: 'bg-red-100 text-red-700',
};

// A non-zero exit reads as a failure worth flagging red.
const exitedBadly = (container) => {
    const match = /Exited \((\d+)\)/.exec(container.status || '');
    return match && match[1] !== '0';
};

const stateClass = (container) =>
    exitedBadly(container) ? 'bg-red-100 text-red-700' : (STATE_CLASS[container.state] ?? 'bg-gray-100 text-gray-500');

const isRunning = (container) => container.state === 'running' || container.state === 'restarting';

// Keep the view live while the page is open.
const { start, stop } = usePoll(5000, { only: ['containers', 'images', 'error'] }, { autoStart: false });
watch(() => props.server?.ulid, (has) => (has ? start() : stop()), { immediate: true });
</script>

<template>
    <AppLayout title="Docker">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Docker</h2>
                <div v-if="servers.length > 1" class="flex items-center gap-2">
                    <label class="text-sm text-gray-500">Server</label>
                    <select
                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                        :value="server?.ulid"
                        @change="selectServer($event.target.value)"
                    >
                        <option v-for="s in servers" :key="s.ulid" :value="s.ulid">{{ s.name }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div v-if="!servers.length" class="bg-white shadow sm:rounded-lg p-6 text-sm text-gray-500">
                    No servers yet.
                    <Link :href="route('server.create')" class="text-indigo-600 hover:underline">Create one</Link>
                    to see its containers here.
                </div>

                <div v-else-if="error" class="bg-white shadow sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-red-700">Couldn't reach Docker on {{ server?.name }}.</div>
                    <pre class="mt-2 overflow-x-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><samp>{{ error }}</samp></pre>
                </div>

                <template v-else>
                    <!-- Containers -->
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-700">
                            Containers <span class="text-gray-400 text-sm">({{ containers.length }})</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium">State</th>
                                        <th class="px-4 py-2 text-left font-medium">Name</th>
                                        <th class="px-4 py-2 text-left font-medium">Image</th>
                                        <th class="px-4 py-2 text-left font-medium">Status</th>
                                        <th class="px-4 py-2 text-left font-medium">Ports</th>
                                        <th class="px-4 py-2 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="container in containers" :key="container.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="stateClass(container)">
                                                {{ container.state }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2">
                                            <Link :href="route('container.show', [server, container.name])" class="font-medium text-indigo-600 hover:underline">
                                                {{ container.name }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600">{{ container.image }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ container.status }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ container.ports || '—' }}</td>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center justify-end gap-2 text-xs font-medium">
                                                <button v-if="!isRunning(container)" type="button" class="text-green-600 hover:underline" @click="runAction(container, 'start')">Start</button>
                                                <button v-if="isRunning(container)" type="button" class="text-indigo-600 hover:underline" @click="runAction(container, 'restart')">Restart</button>
                                                <button v-if="isRunning(container)" type="button" class="text-red-600 hover:underline" @click="runAction(container, 'stop')">Stop</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!containers.length">
                                        <td colspan="6" class="px-4 py-3 text-gray-400">No containers on this server.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Images -->
                    <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-700">
                            Images <span class="text-gray-400 text-sm">({{ images.length }})</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm">
                                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-400">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium">Repository</th>
                                        <th class="px-4 py-2 text-left font-medium">Tag</th>
                                        <th class="px-4 py-2 text-left font-medium">ID</th>
                                        <th class="px-4 py-2 text-left font-medium">Size</th>
                                        <th class="px-4 py-2 text-left font-medium">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="image in images" :key="image.id + image.tag" class="hover:bg-gray-50">
                                        <td class="px-4 py-2 text-gray-800" :class="{ 'text-gray-400': image.repository === '<none>' }">{{ image.repository }}</td>
                                        <td class="px-4 py-2 text-gray-600">{{ image.tag }}</td>
                                        <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ (image.id || '').replace('sha256:', '').slice(0, 12) }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ image.size }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ image.created }}</td>
                                    </tr>
                                    <tr v-if="!images.length">
                                        <td colspan="5" class="px-4 py-3 text-gray-400">No images on this server.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <ConfirmationModal :show="pendingAction !== null" @close="pendingAction = null">
            <template #title>
                {{ pendingAction?.action === 'stop' ? 'Stop' : 'Restart' }} container
            </template>
            <template #content>
                {{ pendingAction?.action === 'stop' ? 'Stop' : 'Restart' }}
                <code class="rounded bg-gray-100 px-1 text-sm">{{ pendingAction?.container.name }}</code>?
                This interrupts the running service.
            </template>
            <template #footer>
                <SecondaryButton @click="pendingAction = null">Cancel</SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': actionForm.processing }"
                    :disabled="actionForm.processing"
                    @click="submitAction(pendingAction.container, pendingAction.action)"
                >
                    {{ pendingAction?.action === 'stop' ? 'Stop' : 'Restart' }}
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
