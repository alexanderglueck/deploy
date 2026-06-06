<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    team: Object,
});

const form = useForm({
    name: '',
    user: 'root',
    ip: '',
    port: 22,
});

const submit = () => {
    form.post(route('server.store', props.team));
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
