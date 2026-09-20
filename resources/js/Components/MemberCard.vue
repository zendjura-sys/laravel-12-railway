<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    number: { type: String, required: true }, // вже замаскований рядок з бекенду ("**********1234")
    name: { type: String, required: true },
    amount: { type: Number, default: 0 },
});

function fmt(v) {
    return new Intl.NumberFormat('uk-UA').format(v ?? 0) + '₴';
}
</script>

<template>
    <div v-reveal v-glow class="glass-panel-gold glass-panel relative mx-auto w-full max-w-sm overflow-hidden p-6 sm:p-7">
        <div class="glass-sheen"></div>

        <div class="relative flex items-start justify-between">
            <ApplicationLogo mark class="h-8 w-8 text-sm text-gold-300" />
            <span class="text-[10px] font-medium uppercase tracking-[0.3em] text-gold-300/70">Monsory Connect</span>
        </div>

        <p class="relative mt-7 font-mono text-xl tracking-[0.25em] text-white sm:text-2xl">{{ number }}</p>

        <div class="relative mt-5">
            <p class="text-[10px] uppercase tracking-widest text-white/40">Нараховано разом</p>
            <p class="font-display text-2xl text-gold-200">{{ fmt(amount) }}</p>
        </div>

        <div class="relative mt-4">
            <p class="text-[10px] uppercase tracking-widest text-white/40">Учасник</p>
            <p class="truncate font-medium text-white">{{ name }}</p>
        </div>
    </div>
</template>
