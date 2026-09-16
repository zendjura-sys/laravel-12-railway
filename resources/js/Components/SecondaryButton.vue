<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="glass-pill inline-flex items-center px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-white/70 transition-colors hover:text-white disabled:opacity-25"
    >
        <slot />
    </button>
</template>
