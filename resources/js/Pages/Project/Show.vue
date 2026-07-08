<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePoll } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import LogOutput from '@/Components/LogOutput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    project: Object,
    webhookSecret: String,
    workflows: Array,
    deployments: Array,
});

const deployUrl = route('api.deployment.store', props.project.deploy_endpoint);

const secretRevealed = ref(false);
const copiedField = ref(null);

const copyToClipboard = async (field, value) => {
    await navigator.clipboard.writeText(value);
    copiedField.value = field;
    setTimeout(() => (copiedField.value = null), 2000);
};

const deployForm = useForm({});
const deleteForm = useForm({});
const cancelForm = useForm({});

const settingsForm = useForm({
    name: props.project.name,
    repository: props.project.repository ?? '',
    default_branch: props.project.default_branch ?? '',
});

const saveSettings = () => {
    settingsForm.put(route('project.update', props.project), { preserveScroll: true });
};

const workflowPendingDeletion = ref(null);
const deploymentPendingCancellation = ref(null);
const deploymentPendingRollback = ref(null);
const rollbackForm = useForm({});

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

// A deployment can be rolled back to when it succeeded, was built from a
// known commit, and included a Docker deploy step. Instant when its SHA
// image survived pruning; otherwise the commit is rebuilt from scratch.
const canRollBackTo = (deployment) =>
    deployment.status === 'deployed'
    && deployment.commit_sha
    && deployment.steps?.some((step) => step.type === 'docker_deploy');

const isInstantRollback = (deployment) => !!(deployment.image && deployment.image_available_at);

const confirmRollback = (deployment) => {
    deploymentPendingRollback.value = deployment;
};

const rollBack = () => {
    rollbackForm.post(route('deployment.rollback', [props.project, deploymentPendingRollback.value]), {
        preserveScroll: true,
        onSuccess: () => (deploymentPendingRollback.value = null),
    });
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
    failed: { label: 'Failed', class: 'bg-red-100 text-red-700', active: false },
    canceled: { label: 'Canceled', class: 'bg-gray-200 text-gray-600', active: false },
};

const STEP_META = {
    pending: { label: 'Pending', class: 'bg-gray-100 text-gray-500' },
    running: { label: 'Running', class: 'bg-blue-100 text-blue-700' },
    succeeded: { label: 'OK', class: 'bg-green-100 text-green-700' },
    failed: { label: 'Failed', class: 'bg-red-100 text-red-700' },
    skipped: { label: 'Skipped', class: 'bg-gray-100 text-gray-400' },
};

const STEP_TYPE_LABELS = {
    docker_deploy: 'Docker deploy',
    docker_rollback: 'Docker rollback',
    inline_script: 'Script',
    script_file: 'Script file',
};

const stepDuration = (step) => {
    if (!step.started_at || !step.finished_at) return null;
    const seconds = Math.round((new Date(step.finished_at) - new Date(step.started_at)) / 1000);
    return seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
};

// A ready-to-commit GitHub Actions workflow as an alternative to a native
// webhook. `interp` avoids Vue parsing the GitHub ${{ }} expressions.
const interp = (expr) => '${{ ' + expr + ' }}';
const actionsSnippet = `name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Trigger deployment
        run: |
          curl --fail -X POST "${deployUrl}" \\
            -H "Content-Type: application/json" \\
            -H "X-Deploy-Secret: ${interp('secrets.DEPLOY_SECRET')}" \\
            --data '{"event":"push","ref":"${interp('github.ref')}","repo":"${interp('github.repository')}","sha":"${interp('github.sha')}"}'
`;

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
                            <span v-if="project.repository" class="text-gray-400 text-sm"> ({{ project.repository }})</span>
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
                                    <span class="ms-2 text-xs font-normal text-gray-400">
                                        {{ (workflow.steps?.length ? workflow.steps.map((s) => STEP_TYPE_LABELS[s.type] ?? s.type) : ['Script']).join(' → ') }}
                                    </span>
                                </summary>
                                <ol v-if="workflow.steps?.length" class="mt-2 space-y-1 text-sm text-gray-600">
                                    <li v-for="(step, stepIndex) in workflow.steps" :key="step.ulid">
                                        {{ stepIndex + 1 }}. {{ STEP_TYPE_LABELS[step.type] ?? step.type }}
                                        <code v-if="step.config?.path" class="rounded bg-gray-100 px-1 text-xs">{{ step.config.path }}</code>
                                    </li>
                                </ol>
                                <pre v-else class="mt-2 text-gray-100 bg-gray-900 rounded p-3 overflow-x-auto text-sm"><samp>{{ workflow.actions }}</samp></pre>
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

                <!-- Settings -->
                <div class="bg-white shadow sm:rounded-lg">
                    <details>
                        <summary class="cursor-pointer px-4 py-3 font-medium text-gray-700">
                            Settings
                        </summary>
                        <form class="space-y-4 border-t border-gray-100 p-4" @submit.prevent="saveSettings">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <InputLabel for="settings-name" value="Name" />
                                    <TextInput id="settings-name" v-model="settingsForm.name" type="text" class="mt-1 block w-full" required />
                                    <InputError :message="settingsForm.errors.name" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel for="settings-repository" value="Repository" />
                                    <TextInput id="settings-repository" v-model="settingsForm.repository" type="text" class="mt-1 block w-full" placeholder="owner/name" />
                                    <InputError :message="settingsForm.errors.repository" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel for="settings-branch" value="Manual deploy branch" />
                                    <TextInput id="settings-branch" v-model="settingsForm.default_branch" type="text" class="mt-1 block w-full" placeholder="repository default" />
                                    <p class="mt-1 text-xs text-gray-500">
                                        Branch cloned by the Deploy button. Leave empty to use the repository's default branch.
                                    </p>
                                    <InputError :message="settingsForm.errors.default_branch" class="mt-2" />
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <PrimaryButton :class="{ 'opacity-25': settingsForm.processing }" :disabled="settingsForm.processing">
                                    Save
                                </PrimaryButton>
                            </div>
                        </form>
                    </details>
                </div>

                <!-- Webhook setup -->
                <div class="bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 font-medium text-gray-700">
                        Webhook
                    </div>
                    <div class="p-4 space-y-4 text-sm">
                        <div>
                            <div class="mb-1 text-xs font-medium text-gray-500">Payload URL (POST)</div>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 overflow-x-auto rounded bg-gray-100 px-2 py-1.5 text-xs text-gray-800">{{ deployUrl }}</code>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="copyToClipboard('url', deployUrl)">
                                    {{ copiedField === 'url' ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 text-xs font-medium text-gray-500">Secret</div>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 overflow-x-auto rounded bg-gray-100 px-2 py-1.5 text-xs text-gray-800">
                                    {{ secretRevealed ? webhookSecret : '••••••••••••••••••••' }}
                                </code>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="secretRevealed = !secretRevealed">
                                    {{ secretRevealed ? 'Hide' : 'Reveal' }}
                                </button>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="copyToClipboard('secret', webhookSecret)">
                                    {{ copiedField === 'secret' ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                        </div>
                        <p class="text-gray-500">
                            On GitHub, add a webhook with this payload URL, content type
                            <code class="rounded bg-gray-100 px-1">application/json</code> and this secret
                            (requests are verified via <code class="rounded bg-gray-100 px-1">X-Hub-Signature-256</code>).
                            Other callers can send the secret directly in an
                            <code class="rounded bg-gray-100 px-1">X-Deploy-Secret</code> header.
                        </p>
                        <details>
                            <summary class="cursor-pointer text-gray-600">
                                Prefer a file in the repository? Use a GitHub Actions workflow instead.
                            </summary>
                            <p class="mt-2 text-gray-500">
                                Commit this as <code class="rounded bg-gray-100 px-1">.github/workflows/deploy.yml</code>
                                and add the secret above as a repository secret named
                                <code class="rounded bg-gray-100 px-1">DEPLOY_SECRET</code>.
                            </p>
                            <div class="mt-2 flex items-start gap-2">
                                <pre class="flex-1 overflow-x-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><samp>{{ actionsSnippet }}</samp></pre>
                                <button type="button" class="text-xs font-medium text-indigo-600 hover:underline" @click="copyToClipboard('snippet', actionsSnippet)">
                                    {{ copiedField === 'snippet' ? 'Copied!' : 'Copy' }}
                                </button>
                            </div>
                        </details>
                    </div>
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
                                    <span
                                        v-if="deployment.is_rollback"
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700"
                                    >
                                        Rollback
                                    </span>
                                    <span
                                        v-if="deployment.status === 'deployed' && deployment.image"
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="isInstantRollback(deployment) ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-400'"
                                        :title="isInstantRollback(deployment) ? 'The built image is still on the server' : 'The built image was pruned; rollback rebuilds the commit'"
                                    >
                                        {{ isInstantRollback(deployment) ? 'image cached' : 'image pruned' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-gray-400">{{ fmt(deployment.received_at) }}</span>
                                    <button
                                        v-if="canRollBackTo(deployment)"
                                        type="button"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-500 hover:underline"
                                        @click="confirmRollback(deployment)"
                                    >
                                        {{ isInstantRollback(deployment) ? 'Roll back' : 'Roll back (rebuild)' }}
                                    </button>
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
                                <div><dt class="inline font-medium">Ref:</dt> {{ deployment.ref }}</div>
                                <div><dt class="inline font-medium">Commit:</dt> {{ deployment.commit_sha ? deployment.commit_sha.slice(0, 10) : '—' }}</div>
                                <div><dt class="inline font-medium">Received:</dt> {{ fmt(deployment.received_at) }}</div>
                                <div><dt class="inline font-medium">Processed:</dt> {{ fmt(deployment.processed_at) }}</div>
                                <div><dt class="inline font-medium">Deployed:</dt> {{ fmt(deployment.deployed_at) }}</div>
                                <div v-if="deployment.failed_at"><dt class="inline font-medium">Failed:</dt> {{ fmt(deployment.failed_at) }}</div>
                                <div v-if="deployment.canceled_at"><dt class="inline font-medium">Canceled:</dt> {{ fmt(deployment.canceled_at) }}</div>
                            </dl>

                            <!-- Step-based deployments -->
                            <div v-if="deployment.steps?.length" class="mt-3 space-y-2">
                                <div
                                    v-for="step in deployment.steps"
                                    :key="step.ulid"
                                    class="rounded-lg border border-gray-100"
                                >
                                    <details :open="step.status === 'running' || step.status === 'failed'">
                                        <summary class="flex cursor-pointer items-center justify-between px-3 py-2">
                                            <span class="flex items-center gap-2 text-sm text-gray-700">
                                                <span
                                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                    :class="STEP_META[step.status]?.class"
                                                >
                                                    <span v-if="step.status === 'running'" class="me-1.5 inline-flex size-1.5 animate-pulse rounded-full bg-current" />
                                                    {{ STEP_META[step.status]?.label ?? step.status }}
                                                </span>
                                                {{ step.position }}. {{ STEP_TYPE_LABELS[step.type] ?? step.type }}
                                            </span>
                                            <span class="text-xs text-gray-400">
                                                <template v-if="stepDuration(step)">{{ stepDuration(step) }}</template>
                                                <template v-if="step.exit_code !== null && step.exit_code !== 0"> · exit {{ step.exit_code }}</template>
                                            </span>
                                        </summary>
                                        <div class="border-t border-gray-100 p-2">
                                            <LogOutput
                                                :content="step.output"
                                                placeholder="No output."
                                                max-height="max-h-72"
                                                :auto-scroll="step.status === 'running'"
                                            />
                                        </div>
                                    </details>
                                </div>
                                <details v-if="deployment.log?.log" class="mt-2">
                                    <summary class="cursor-pointer text-sm text-gray-600">System log</summary>
                                    <LogOutput class="mt-2" :content="deployment.log.log" />
                                </details>
                            </div>

                            <!-- Legacy single-log deployments -->
                            <div v-else-if="isActive(deployment)" class="mt-3">
                                <div class="mb-1 text-xs font-medium text-gray-500">Live log</div>
                                <LogOutput
                                    :content="deployment.log?.log"
                                    placeholder="Waiting for output…"
                                    max-height="max-h-72"
                                    auto-scroll
                                />
                            </div>
                            <template v-else>
                                <details v-if="deployment.actions" class="mt-2">
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

        <!-- Rollback confirmation -->
        <ConfirmationModal :show="deploymentPendingRollback !== null" @close="deploymentPendingRollback = null">
            <template #title>
                Roll back
            </template>
            <template #content>
                <template v-if="deploymentPendingRollback && isInstantRollback(deploymentPendingRollback)">
                    Retags the image built for commit
                    <code class="rounded bg-gray-100 px-1 text-sm">{{ deploymentPendingRollback.commit_sha?.slice(0, 10) }}</code>
                    as <code class="rounded bg-gray-100 px-1 text-sm">latest</code> and restarts the app — usually seconds.
                </template>
                <template v-else-if="deploymentPendingRollback">
                    The image for commit
                    <code class="rounded bg-gray-100 px-1 text-sm">{{ deploymentPendingRollback.commit_sha?.slice(0, 10) }}</code>
                    is no longer on the server, so it will be rebuilt from that exact commit — this takes as long as a normal deployment.
                </template>
            </template>
            <template #footer>
                <SecondaryButton @click="deploymentPendingRollback = null">
                    Cancel
                </SecondaryButton>
                <PrimaryButton
                    class="ms-3"
                    :class="{ 'opacity-25': rollbackForm.processing }"
                    :disabled="rollbackForm.processing"
                    @click="rollBack"
                >
                    Roll back
                </PrimaryButton>
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
