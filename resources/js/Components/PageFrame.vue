<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";

defineProps({
    pageTitle: { type: String, required: true },
    header: { type: String, required: true },
    banner: { type: String, required: true },
});
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-primary-txt">
                {{ header }}
            </h2>
        </template>

        <div class="py-12">
            <div class="flex justify-center">
                <div class="w-full max-w-8xl">
                    <div class="bg-white shadow-md sm:rounded-xl">
                        <div class="p-4 border-b bg-primary sm:rounded-t-xl">
                            <h1 class="text-lg text-white font-semibold">{{ banner }}</h1>
                        </div>
                        <slot />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
