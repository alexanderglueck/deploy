<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import WorkflowStepsEditor from '@/Components/WorkflowStepsEditor.vue';

const props = defineProps({
    project: Object,
    servers: Array,
    events: Array,
    stepTypes: Array,
});

const form = useForm({
    event: props.events.length ? props.events[0].value : '',
    server: props.servers.length ? props.servers[0].ulid : '',
    steps: [{ type: 'docker_deploy', config: {} }],
});

const submit = () => {
    form.post(route('workflow.store', props.project));
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
                            <InputLabel for="server" value="Server" />
                            <select id="server" v-model="form.server" :class="fieldClass" required>
                                <option v-for="server in servers" :key="server.ulid" :value="server.ulid">
                                    {{ server.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.server" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel value="Steps" />
                            <div class="mt-2">
                                <WorkflowStepsEditor v-model="form.steps" :step-types="stepTypes" :errors="form.errors" />
                            </div>
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
