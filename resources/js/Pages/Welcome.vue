<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen bg-gray-100 flex flex-col items-center justify-center px-6">
        <div class="max-w-xl w-full bg-white shadow sm:rounded-lg p-8 text-center">
            <h1 class="text-3xl font-bold text-gray-900">
                Deploy
            </h1>
            <p class="mt-3 text-gray-600">
                Your deployment solution. Connect your servers, define workflows and ship your
                projects with a single push.
            </p>

            <div v-if="canLogin" class="mt-8 flex items-center justify-center gap-4">
                <Link
                    :href="route('login')"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition"
                >
                    Log in
                </Link>

                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition"
                >
                    Register
                </Link>
            </div>
        </div>
    </div>
</template>
