<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
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

const submit = () => {
    form.post(route('server.setup.store', props.server), {
        onFinish: () => form.reset('password'),
    });
};

const copied = ref(false);

const copyPublicKey = async () => {
    await navigator.clipboard.writeText(props.server.public_key);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
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
                        <dd class="col-span-2">
                            <span v-if="isSetUp" class="text-green-600 font-medium">Set up</span>
                            <span v-else class="text-amber-600 font-medium">Not set up</span>
                        </dd>
                    </dl>
                </div>

                <div v-if="server.type === 'local'" class="bg-white shadow sm:rounded-lg p-6 text-sm text-gray-600">
                    Commands for this server run directly on the machine the app runs on.
                    No SSH setup is needed.
                </div>

                <div v-if="server.type === 'ssh' && server.public_key" class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="font-medium text-gray-700">Public key</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        This server has its own keypair. Add the public key to
                        <code class="rounded bg-gray-100 px-1">~/.ssh/authorized_keys</code>
                        for the <code class="rounded bg-gray-100 px-1">{{ server.user }}</code> user —
                        or use the password setup below to install it automatically.
                    </p>
                    <div class="mt-3 flex items-start gap-2">
                        <pre class="flex-1 overflow-x-auto rounded bg-gray-900 p-3 text-xs text-gray-100"><samp>{{ server.public_key }}</samp></pre>
                        <SecondaryButton type="button" @click="copyPublicKey">
                            {{ copied ? 'Copied!' : 'Copy' }}
                        </SecondaryButton>
                    </div>
                </div>

                <div v-if="server.type === 'ssh' && !isSetUp" class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="font-medium text-gray-700 mb-4">Set up server</h3>
                    <p class="mb-4 text-sm text-gray-500">
                        Logs in once with the password and appends the public key above to
                        the user's authorized keys. The password is not stored.
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
            </div>
        </div>
    </AppLayout>
</template>
