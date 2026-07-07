<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    modelValue: Array,
    stepTypes: Array,
    errors: Object,
});

const emit = defineEmits(['update:modelValue']);

const fieldClass = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';

const typeLabel = (type) => props.stepTypes.find((t) => t.value === type)?.label ?? type;

const update = (steps) => emit('update:modelValue', steps);

const addStep = (type) => {
    update([...props.modelValue, { type, config: {} }]);
};

const removeStep = (index) => {
    update(props.modelValue.filter((_, i) => i !== index));
};

const moveStep = (index, delta) => {
    const target = index + delta;
    if (target < 0 || target >= props.modelValue.length) return;
    const steps = [...props.modelValue];
    [steps[index], steps[target]] = [steps[target], steps[index]];
    update(steps);
};

const error = (index, key) => props.errors?.[`steps.${index}.${key}`];
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="(step, index) in modelValue"
            :key="index"
            class="rounded-lg border border-gray-200"
        >
            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-2 rounded-t-lg">
                <span class="text-sm font-medium text-gray-700">
                    {{ index + 1 }}. {{ typeLabel(step.type) }}
                </span>
                <div class="flex items-center gap-1 text-gray-400">
                    <button type="button" class="rounded px-1.5 py-0.5 hover:bg-gray-200 disabled:opacity-30" :disabled="index === 0" title="Move up" @click="moveStep(index, -1)">↑</button>
                    <button type="button" class="rounded px-1.5 py-0.5 hover:bg-gray-200 disabled:opacity-30" :disabled="index === modelValue.length - 1" title="Move down" @click="moveStep(index, 1)">↓</button>
                    <button type="button" class="rounded px-1.5 py-0.5 text-red-500 hover:bg-red-50" title="Remove step" @click="removeStep(index)">✕</button>
                </div>
            </div>

            <div class="space-y-4 p-4">
                <template v-if="step.type === 'inline_script'">
                    <div>
                        <InputLabel :for="`step-${index}-script`" value="Script" />
                        <textarea :id="`step-${index}-script`" v-model="step.config.script" rows="6" :class="fieldClass" placeholder="echo deploying…" />
                        <InputError :message="error(index, 'config.script')" class="mt-2" />
                    </div>
                </template>

                <template v-else-if="step.type === 'script_file'">
                    <div>
                        <InputLabel :for="`step-${index}-path`" value="Path on the server" />
                        <TextInput :id="`step-${index}-path`" v-model="step.config.path" type="text" class="mt-1 block w-full" placeholder="/srv/server-config/deploy/update-server-config.sh" />
                        <InputError :message="error(index, 'config.path')" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel :for="`step-${index}-args`" value="Arguments (optional)" />
                        <TextInput :id="`step-${index}-args`" v-model="step.config.args" type="text" class="mt-1 block w-full" />
                        <InputError :message="error(index, 'config.args')" class="mt-2" />
                    </div>
                </template>

                <template v-else-if="step.type === 'docker_deploy'">
                    <p class="text-sm text-gray-500">
                        Clones the pushed repository, builds
                        <code class="rounded bg-gray-100 px-1">deploy/build.sh</code> →
                        <code class="rounded bg-gray-100 px-1">Dockerfile.dist</code> →
                        <code class="rounded bg-gray-100 px-1">Dockerfile</code>,
                        tags the image with <code class="rounded bg-gray-100 px-1">latest</code> and the commit SHA,
                        then runs <code class="rounded bg-gray-100 px-1">docker compose up -d</code>.
                        All fields are optional — conventions fill the gaps.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel :for="`step-${index}-app`" value="App name" />
                            <TextInput :id="`step-${index}-app`" v-model="step.config.app" type="text" class="mt-1 block w-full" placeholder="repo name" />
                            <InputError :message="error(index, 'config.app')" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel :for="`step-${index}-compose`" value="Compose file" />
                            <TextInput :id="`step-${index}-compose`" v-model="step.config.compose_file" type="text" class="mt-1 block w-full" placeholder="/srv/server-config/apps/{app}/compose.yml" />
                            <InputError :message="error(index, 'config.compose_file')" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel :for="`step-${index}-target`" value="Build target" />
                            <TextInput :id="`step-${index}-target`" v-model="step.config.target" type="text" class="mt-1 block w-full" placeholder="production" />
                            <InputError :message="error(index, 'config.target')" class="mt-2" />
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <InputError :message="errors?.steps" />

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-500">Add step:</span>
            <button
                v-for="type in stepTypes"
                :key="type.value"
                type="button"
                class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50 transition"
                :title="type.description"
                @click="addStep(type.value)"
            >
                + {{ type.label }}
            </button>
        </div>
    </div>
</template>
