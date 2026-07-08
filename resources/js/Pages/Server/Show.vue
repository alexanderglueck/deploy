<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    server: Object,
    isSetUp: Boolean,
});

const form = useForm({
    password: '',
});

const testForm = useForm({});

// The two forms are alternative paths to the same goal; a success on either
// makes the other's leftover error stale, so clear it.
const submit = () => {
    form.post(route('server.setup.store', props.server), {
        onFinish: () => form.reset('password'),
        onSuccess: () => testForm.clearErrors(),
    });
};

const testConnection = () => {
    testForm.post(route('server.test.store', props.server), {
        preserveScroll: true,
        onSuccess: () => form.clearErrors(),
    });
};

const copied = ref(false);

const copyPublicKey = async () => {
    await navigator.clipboard.writeText(props.server.public_key);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const settingsForm = useForm({
    name: props.server.name,
    user: props.server.user,
    ip: props.server.ip ?? '',
    port: props.server.port,
});

const saveSettings = () => {
    settingsForm.put(route('server.update', props.server), { preserveScroll: true });
};

const rotateKeyForm = useForm({});
const confirmingKeyRotation = ref(false);

const rotateKey = () => {
    rotateKeyForm.post(route('server.key.store', props.server), {
        preserveScroll: true,
        onSuccess: () => (confirmingKeyRotation.value = false),
    });
};

const deleteServerForm = useForm({});
const confirmingServerDeletion = ref(false);

const deleteServer = () => {
    deleteServerForm.delete(route('server.destroy', props.server), {
        onFinish: () => (confirmingServerDeletion.value = false),
    });
};
</script>

<template>
    <AppLayout :title="server.name">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Servers — {{ server.name }}
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <dl class="grid grid-cols-3 gap-2 text-sm">
                        <dt class="font-medium text-gray-500">Name</dt>
                        <dd class="col-span-2 text-gray-800">{{ server.name }}</dd>
                        <dt class="font-medium text-gray-500">Type</dt>
                        <dd class="col-span-2 text-gray-800">
                            {{ server.type === 'local' ? 'This server' : 'Remote server (SSH)' }}
                        </dd>
                        <template v-if="server.type === 'ssh'">
                            <dt class="font-medium text-gray-500">User</dt>
                            <dd class="col-span-2 text-gray-800">{{ server.user }}</dd>
                            <dt class="font-medium text-gray-500">IP</dt>
                            <dd class="col-span-2 text-gray-800">{{ server.ip }}</dd>
                            <dt class="font-medium text-gray-500">Port</dt>
                            <dd class="col-span-2 text-gray-800">{{ server.port }}</dd>
                        </template>
                        <dt class="font-medium text-gray-500">Status</dt>
                        <dd class="col-span-2 flex items-center gap-3">
                            <span v-if="isSetUp" class="text-green-600 font-medium">Set up</span>
                            <span v-else class="text-amber-600 font-medium">Not set up</span>
                            <SecondaryButton
                                v-if="server.type === 'ssh'"
                                type="button"
                                :class="{ 'opacity-25': testForm.processing }"
                                :disabled="testForm.processing"
                                @click="testConnection"
                            >
                                {{ testForm.processing ? 'Testing…' : 'Test connection' }}
                            </SecondaryButton>
                        </dd>
                    </dl>
                    <InputError :message="testForm.errors.connection" class="mt-3" />
                </div>

                <div v-if="server.type === 'local'" class="bg-white shadow sm:rounded-lg p-6 text-sm text-gray-600">
                    Commands for this server run directly on the machine the app runs on.
                    No SSH setup is needed.
                </div>

                <!-- Settings -->
                <div class="bg-white shadow sm:rounded-lg">
                    <details>
                        <summary class="cursor-pointer px-6 py-4 font-medium text-gray-700">
                            Settings
                        </summary>
                        <form class="space-y-4 px-6 pb-6" @submit.prevent="saveSettings">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <InputLabel for="settings-name" value="Name" />
                                    <TextInput id="settings-name" v-model="settingsForm.name" type="text" class="mt-1 block w-full" required />
                                    <InputError :message="settingsForm.errors.name" class="mt-2" />
                                </div>
                                <template v-if="server.type === 'ssh'">
                                    <div>
                                        <InputLabel for="settings-user" value="User" />
                                        <TextInput id="settings-user" v-model="settingsForm.user" type="text" class="mt-1 block w-full" required />
                                        <InputError :message="settingsForm.errors.user" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="settings-ip" value="IP" />
                                        <TextInput id="settings-ip" v-model="settingsForm.ip" type="text" class="mt-1 block w-full" required />
                                        <InputError :message="settingsForm.errors.ip" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="settings-port" value="Port" />
                                        <TextInput id="settings-port" v-model="settingsForm.port" type="number" class="mt-1 block w-full" required />
                                        <InputError :message="settingsForm.errors.port" class="mt-2" />
                                    </div>
                                </template>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <DangerButton type="button" @click="confirmingServerDeletion = true">
                                        Delete server
                                    </DangerButton>
                                    <SecondaryButton v-if="server.type === 'ssh'" type="button" @click="confirmingKeyRotation = true">
                                        Rotate keypair
                                    </SecondaryButton>
                                </div>
                                <PrimaryButton :class="{ 'opacity-25': settingsForm.processing }" :disabled="settingsForm.processing">
                                    Save
                                </PrimaryButton>
                            </div>
                            <InputError :message="deleteServerForm.errors.server" />
                        </form>
                    </details>
                </div>

                <div v-if="server.type === 'ssh' && server.public_key" class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="font-medium text-gray-700">Public key</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        This server has its own keypair. Add the public key to
                        <code class="rounded bg-gray-100 px-1">~/.ssh/authorized_keys</code>
                        for the <code class="rounded bg-gray-100 px-1">{{ server.user }}</code> user
                        and click "Test connection" to verify and mark the server as set up —
                        or use the password setup below to install it automatically.
                    </p>
                    <div class="mt-3 flex items-start gap-2">
                        <pre class="flex-1 overflow-x-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><samp>{{ server.public_key }}</samp></pre>
                        <SecondaryButton type="button" @click="copyPublicKey">
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </SecondaryButton>
                    </div>
                </div>

                <div v-if="server.type === 'ssh'" class="bg-white shadow sm:rounded-lg">
                    <details :open="!isSetUp">
                        <summary class="cursor-pointer px-6 py-4 font-medium text-gray-700">
                            {{ isSetUp ? 'Re-run key setup' : 'Set up server' }}
                        </summary>
                        <div class="px-6 pb-6">
                            <p class="mb-4 text-sm text-gray-500">
                                Logs in once with the password, appends the public key above to
                                the user's authorized keys, and verifies a key login before
                                marking the server as set up. The password is not stored.
                            </p>
                            <form class="space-y-4" @submit.prevent="submit">
                                <div>
                                    <InputLabel for="password" :value="`Server password for user ${server.user}`" />
                                    <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
                                    <InputError :message="form.errors.password" class="mt-2" />
                                </div>
                                <div class="flex justify-end">
                                    <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                        Setup
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        <!-- Delete server confirmation -->
        <ConfirmationModal :show="confirmingServerDeletion" @close="confirmingServerDeletion = false">
            <template #title>
                Delete server
            </template>
            <template #content>
                Delete "{{ server.name }}"? Servers still referenced by workflows cannot be
                deleted — repoint those workflows first.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingServerDeletion = false">
                    Cancel
                </SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': deleteServerForm.processing }"
                    :disabled="deleteServerForm.processing"
                    @click="deleteServer"
                >
                    Delete server
                </DangerButton>
            </template>
        </ConfirmationModal>

        <!-- Rotate keypair confirmation -->
        <ConfirmationModal :show="confirmingKeyRotation" @close="confirmingKeyRotation = false">
            <template #title>
                Rotate keypair
            </template>
            <template #content>
                Generates a new keypair for this server. The current key stops being used
                immediately — deployments will fail until the new public key is installed
                in the server's authorized keys.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmingKeyRotation = false">
                    Cancel
                </SecondaryButton>
                <DangerButton
                    class="ms-3"
                    :class="{ 'opacity-25': rotateKeyForm.processing }"
                    :disabled="rotateKeyForm.processing"
                    @click="rotateKey"
                >
                    Rotate
                </DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
