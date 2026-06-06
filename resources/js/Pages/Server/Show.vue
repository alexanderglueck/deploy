<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    team: Object,
    server: Object,
    isSetUp: Boolean,
});

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('server.setup.store', [props.team, props.server]), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AppLayout :title="server.name">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ team.name }} — Servers — {{ server.name }}
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <dl class="grid grid-cols-3 gap-2 text-sm">
                        <dt class="font-medium text-gray-500">Name</dt>
                        <dd class="col-span-2 text-gray-800">{{ server.name }}</dd>
                        <dt class="font-medium text-gray-500">User</dt>
                        <dd class="col-span-2 text-gray-800">{{ server.user }}</dd>
                        <dt class="font-medium text-gray-500">IP</dt>
                        <dd class="col-span-2 text-gray-800">{{ server.ip }}</dd>
                        <dt class="font-medium text-gray-500">Port</dt>
                        <dd class="col-span-2 text-gray-800">{{ server.port }}</dd>
                        <dt class="font-medium text-gray-500">Status</dt>
                        <dd class="col-span-2">
                            <span v-if="isSetUp" class="text-green-600 font-medium">Set up</span>
                            <span v-else class="text-amber-600 font-medium">Not set up</span>
                        </dd>
                    </dl>
                </div>

                <div v-if="!isSetUp" class="bg-white shadow sm:rounded-lg p-6">
                    <h3 class="font-medium text-gray-700 mb-4">Set up server</h3>
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
