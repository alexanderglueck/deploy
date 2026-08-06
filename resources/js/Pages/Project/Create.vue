<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    repository: '',
    default_branch: '',
    git_base: '',
    git_token_user: '',
    git_token: '',
});

const submit = () => {
    form.post(route('project.store'));
};
</script>

<template>
    <AppLayout title="Create project">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create project
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
                            <InputLabel for="repository" value="Repository (optional)" />
                            <TextInput id="repository" v-model="form.repository" type="text" class="mt-1 block w-full" placeholder="owner/name" />
                            <p class="mt-1 text-sm text-gray-500">
                                Webhooks are only accepted for this GitHub repository. Leave empty to accept any.
                            </p>
                            <InputError :message="form.errors.repository" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="default_branch" value="Manual deploy branch (optional)" />
                            <TextInput id="default_branch" v-model="form.default_branch" type="text" class="mt-1 block w-full" placeholder="repository default" />
                            <p class="mt-1 text-sm text-gray-500">
                                Branch cloned when you deploy manually. Leave empty to use the repository's default branch.
                            </p>
                            <InputError :message="form.errors.default_branch" class="mt-2" />
                        </div>

                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="font-medium text-gray-900">Git source (optional)</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Only needed when this repository is not on the installation's default
                                host. Docker deploy steps build the clone URL from the repository name,
                                so a project hosted elsewhere cannot be cloned without this.
                            </p>
                        </div>

                        <div>
                            <InputLabel for="git_base" value="Git host" />
                            <TextInput id="git_base" v-model="form.git_base" type="text" class="mt-1 block w-full" placeholder="https://gitlab.com" />
                            <p class="mt-1 text-sm text-gray-500">
                                Scheme and host only — no credentials, path or query.
                            </p>
                            <InputError :message="form.errors.git_base" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="git_token_user" value="Token username" />
                            <TextInput id="git_token_user" v-model="form.git_token_user" type="text" class="mt-1 block w-full" placeholder="x-access-token" />
                            <p class="mt-1 text-sm text-gray-500">
                                Host-specific: <code>oauth2</code> on GitLab, <code>x-access-token</code> on GitHub.
                            </p>
                            <InputError :message="form.errors.git_token_user" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="git_token" value="Access token" />
                            <TextInput id="git_token" v-model="form.git_token" type="password" class="mt-1 block w-full" autocomplete="off" />
                            <p class="mt-1 text-sm text-gray-500">
                                Needed for private repositories. Stored encrypted and never shown again.
                            </p>
                            <InputError :message="form.errors.git_token" class="mt-2" />
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
