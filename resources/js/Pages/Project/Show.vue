<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePoll } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import LogOutput from '@/Components/LogOutput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    project: Object,
    workflows: Array,
    deployments: Array,
});

const deployUrl = route('api.deployment.store', props.project.deploy_endpoint);

const deployForm = useForm({});
const deleteForm = useForm({});
const cancelForm = useForm({});

const workflowPendingDeletion = ref(null);
const deploymentPendingCancellation = ref(null);

const deploy = () => {
    deployForm.post(route('deployment.store', props.project), { preserveScroll: true });
};

const confirmWorkflowDeletion = (workflow) => {
    workflowPendingDeletion.value = workflow;
};

const deleteWorkflow = () => {
    deleteForm.delete(route('workflow.destroy', [props.project, workflowPendingDeletion.value]), {
        preserveScroll: true,
        onSuccess: () => (workflowPendingDeletion.value = null),
    });
};

const confirmCancellation = (deployment) => {
    deploymentPendingCancellation.value = deployment;
};

const cancelDeployment = () => {
    cancelForm.post(route('deployment.cancel', [props.project, deploymentPendingCancellation.value]), {
        preserveScroll: true,
        onSuccess: () => (deploymentPendingCancellation.value = null),
    });
};

const STATUS_META = {
    pending: { label: 'Pending', class: 'bg-gray-100 text-gray-600', active: true },
    deploying: { label: 'Deploying', class: 'bg-blue-100 text-blue-700', active: true },
    deployed: { label: 'Deployed', class: 'bg-green-100 text-green-700', active: false },
    canceled: { label: 'Canceled', class: 'bg-red-100 text-red-700', active: false },
};

const isActive = (deployment) => STATUS_META[deployment.status]?.active ?? false;
const hasActiveDeployment = computed(() => props.deployments.some(isActive));

const { start, stop } = usePoll(3000, { only: ['deployments'] }, { autoStart: false });

watch(hasActiveDeployment, (active) => {
    active ? start() : stop();
}, { immediate: true });

const fmt = (value) => (value ? new Date(value).toLocaleString() : '—');
</script>

<template>
    <AppLayout :title="project.name">
        <template #header>
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ project.name }}
                </h2>
                <span
                    v-if="hasActiveDeployment"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-blue-700"
                >
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75" />
                        <span class="relative inline-flex size-2 rounded-full bg-blue-500" />
                    </span>
                    Live — auto-refreshing
                </span>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Workflows -->
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <div>
                            <span class="font-medium text-gray-700">{{ project.name }}</span>
                            <span class="text-gray-400 text-sm"> ({{ deployUrl }})</span>
                        </div>
                        <Link
                            :href="route('workflow.create', project)"
                            class="inline-flex items-center px-3 py-1.5 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition"
                        >
                            Create workflow
                        </Link>
                    </div>

                    <ul class="divide-y divide-gray-100">
                        <li v-for="(workflow, index) in workflows" :key="workflow.ulid" class="px-4 py-4">
                            <details>
                                <summary class="cursor-pointer font-medium text-gray-800">
                                    Workflow {{ index + 1 }} on {{ workflow.server.name }}
                                </summary>
                                <pre class="mt-2 text-gray-100 bg-gray-900 rounded p-3 overflow-x-auto text-sm"><samp>{{ workflow.actions }}</samp></pre>
                                <div class="mt-3 flex items-center gap-2">
                                    <Link
                                        :href="route('workflow.edit', [project, workflow])"
                                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition"
                                    >
                                        Edit
                                    </Link>
                                    <DangerButton @click="confirmWorkflowDeletion(workflow)">
                                        Delete
                                    </DangerButton>
                                    <PrimaryButton :disabled="deployForm.processing" @click="deploy">
                                        Deploy
                                    </PrimaryButton>
                                </div>
                            </details>
                        </li>
                        <li v-if="!workflows.length" class="px-4 py-3 text-sm text-gray-400">No workflows yet.</li>
                    </ul>
                </div>

                <!-- Deployments -->
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-700">
                        Deployments
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="deployment in deployments" :key="deployment.ulid" class="px-4 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="STATUS_META[deployment.status].class"
                                    >
                                        <span
                                            v-if="isActive(deployment)"
                                            class="me-1.5 inline-flex size-1.5 animate-pulse rounded-full bg-current"
                                        />
                                        {{ STATUS_META[deployment.status].label }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-400">{{ fmt(deployment.received_at) }}</span>
                                    <button
                                        v-if="isActive(deployment)"
                                        type="button"
                                        class="text-xs font-medium text-red-600 hover:text-red-500 hover:underline"
                                        @click="confirmCancellation(deployment)"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>

                            <dl class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-500 sm:grid-cols-4">
                                <div><dt class="inline font-medium">Received:</dt> {{ fmt(deployment.received_at) }}</div>
                                <div><dt class="inline font-medium">Processed:</dt> {{ fmt(deployment.processed_at) }}</div>
                                <div><dt class="inline font-medium">Deployed:</dt> {{ fmt(deployment.deployed_at) }}</div>
                                <div><dt class="inline font-medium">Canceled:</dt> {{ fmt(deployment.canceled_at) }}</div>
                            </dl>

                            <div v-if="isActive(deployment)" class="mt-3">
                                <div class="mb-1 text-xs font-medium text-gray-500">Live log</div>
                                <LogOutput
                                    :content="deployment.log?.log"
                                    placeholder="Waiting for output…"
                                    max-height="max-h-72"
                                    auto-scroll
                                />
                            </div>
                            <template v-else>
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-sm text-gray-600">Executed actions</summary>
                                    <pre class="mt-2 text-gray-100 bg-gray-900 rounded p-3 overflow-x-auto text-sm"><samp>{{ deployment.actions }}</samp></pre>
                                </details>
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-sm text-gray-600">Log</summary>
                                    <LogOutput class="mt-2" :content="deployment.log?.log" />
                                </details>
                            </template>
                        </li>
                        <li v-if="!deployments.length" class="px-4 py-3 text-sm text-gray-400">No deployments yet.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Delete workflow confirmation -->
        <ConfirmationModal :show="workflowPendingDeletion !== null" @close="workflowPendingDeletion = null">
            <template #title>
                Delete workflow
            </template>
            <template #content>
                Are you sure you want to delete this workflow? This action cannot be undone.
            </template>
            <template #footer>
                <SecondaryButton @click="workflowPendingDeletion = null">
                    Cancel
                </SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleteForm.processing }"
                    :disabled="deleteForm.processing"
                    @click="deleteWorkflow"
                >
                    Delete
                </DangerButton>
            </template>
        </ConfirmationModal>

        <!-- Cancel deployment confirmation -->
        <ConfirmationModal :show="deploymentPendingCancellation !== null" @close="deploymentPendingCancellation = null">
            <template #title>
                Cancel deployment
            </template>
            <template #content>
                Cancel this deployment? A queued deployment will not run; one that is already
                in progress is marked canceled but can't be interrupted mid-run.
            </template>
            <template #footer>
                <SecondaryButton @click="deploymentPendingCancellation = null">
                    Keep
                </SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': cancelForm.processing }"
                    :disabled="cancelForm.processing"
                    @click="cancelDeployment"
                >
                    Cancel deployment
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
