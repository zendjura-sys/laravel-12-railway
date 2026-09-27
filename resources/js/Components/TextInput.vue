<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="w-full rounded-lg border border-white/10 bg-obsidian-900/60 px-3 py-2 text-white placeholder:text-white/30 focus:border-gold-400/50 focus:outline-none focus:ring-1 focus:ring-gold-400/40"
        v-model="model"
        ref="input"
    />
</template>
