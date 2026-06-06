<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    team: Object,
    project: Object,
    servers: Array,
    events: Array,
});

const form = useForm({
    event: props.events.length ? props.events[0].value : '',
    server_id: props.servers.length ? props.servers[0].id : '',
    actions: '',
});

const submit = () => {
    form.post(route('workflow.store', [props.team, props.project]));
};

const fieldClass = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
</script>

<template>
    <AppLayout title="Create workflow">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create workflow
            </h2>
        </template>

        <div class="py-10">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div>
                            <InputLabel for="event" value="Event" />
                            <select id="event" v-model="form.event" :class="fieldClass" required>
                                <option v-for="event in events" :key="event.value" :value="event.value">
                                    {{ event.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.event" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="server_id" value="Server" />
                            <select id="server_id" v-model="form.server_id" :class="fieldClass" required>
                                <option v-for="server in servers" :key="server.id" :value="server.id">
                                    {{ server.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.server_id" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="actions" value="Actions" />
                            <textarea id="actions" v-model="form.actions" rows="6" :class="fieldClass" required />
                            <InputError :message="form.errors.actions" class="mt-2" />
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
