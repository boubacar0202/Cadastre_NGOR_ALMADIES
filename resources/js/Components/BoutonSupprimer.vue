<script setup>
import { computed } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
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
    'inline-flex items-center justify-center whitespace-nowrap rounded-md border border-red-700 bg-white font-semibold text-red-700 transition duration-150 ease-in-out hover:bg-red-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-40',
    props.size === 'md' ? 'min-w-36 px-4 py-2 text-sm' : 'px-2.5 py-1 text-xs',
]);
</script>

<template>
    <button :type="type" :disabled="disabled" v-bind="$attrs" :class="classes">
        <slot>Supprimer</slot>
    </button>
</template>
