<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    type: 'local',
    user: 'root',
    ip: '',
    port: 22,
});

const submit = () => {
    form.post(route('server.store'));
};
</script>

<template>
    <AppLayout title="Create server">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create server
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div>
                            <InputLabel for="name" value="Name" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>

                        <fieldset>
                            <legend class="text-sm font-medium text-gray-700">Type</legend>
                            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                                <label
                                    class="flex cursor-pointer flex-col rounded-lg border p-4"
                                    :class="form.type === 'local' ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-300'"
                                >
                                    <span class="flex items-center gap-2">
                                        <input v-model="form.type" type="radio" value="local" class="text-indigo-600 focus:ring-indigo-500" />
                                        <span class="font-medium text-gray-800">This server</span>
                                    </span>
                                    <span class="mt-1 text-sm text-gray-500">
                                        Run commands on the machine this app runs on — e.g. to build and start Docker containers.
                                    </span>
                                </label>
                                <label
                                    class="flex cursor-pointer flex-col rounded-lg border p-4"
                                    :class="form.type === 'ssh' ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-300'"
                                >
                                    <span class="flex items-center gap-2">
                                        <input v-model="form.type" type="radio" value="ssh" class="text-indigo-600 focus:ring-indigo-500" />
                                        <span class="font-medium text-gray-800">Remote server (SSH)</span>
                                    </span>
                                    <span class="mt-1 text-sm text-gray-500">
                                        Run commands on another machine over SSH. A dedicated keypair is generated for it.
                                    </span>
                                </label>
                            </div>
                            <InputError :message="form.errors.type" class="mt-2" />
                        </fieldset>

                        <template v-if="form.type === 'ssh'">
                            <div>
                                <InputLabel for="user" value="User" />
                                <TextInput id="user" v-model="form.user" type="text" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.user" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="ip" value="IP" />
                                <TextInput id="ip" v-model="form.ip" type="text" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.ip" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="port" value="Port" />
                                <TextInput id="port" v-model="form.port" type="number" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.port" class="mt-2" />
                            </div>
                        </template>

                        <div class="flex justify-end">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Create
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
