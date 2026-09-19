<script setup>
defineProps({
    value: {
        type: String,
    },
});
</script>

<template>
    <label class="mb-2 block text-xs font-medium uppercase tracking-widest text-white/40">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
