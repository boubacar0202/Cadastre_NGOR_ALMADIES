<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    href: {
        type: String,
        default: '',
    },
    type: {
        type: String,
        default: 'button',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: 'sm',
    },
});

const classes = computed(() => [
    'inline-flex items-center justify-center whitespace-nowrap rounded-md border border-primary bg-white font-semibold text-primary transition duration-150 ease-in-out hover:bg-primary hover:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-40',
    props.size === 'md' ? 'min-w-36 px-4 py-2 text-sm' : 'px-2.5 py-1 text-xs',
]);
</script>

<template>
    <Link v-if="href" :href="href" v-bind="$attrs" :class="classes">
        <slot>Modifier</slot>
    </Link>
    <button v-else :type="type" :disabled="disabled" v-bind="$attrs" :class="classes">
        <slot>Modifier</slot>
    </button>
</template>
