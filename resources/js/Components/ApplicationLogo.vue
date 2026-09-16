<script setup>
defineProps({
    mark: { type: Boolean, default: false },
});
</script>

<template>
    <!-- Фірмовий знак Monsory: ромб з літерою M — та сама форма, що вже
         використовується у навігації Home.vue, тепер єдиний компонент
         замість дефолтного лого Laravel. -->
    <span v-if="mark" class="inline-flex rotate-45 items-center justify-center rounded-md border border-gold-400/40 bg-gradient-to-br from-gold-400/20 to-transparent">
        <span class="-rotate-45 font-display text-gold-300"><slot>M</slot></span>
    </span>
    <svg v-else viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="monsory-logo-gold" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#f1e2b8" />
                <stop offset="45%" stop-color="#d4af37" />
                <stop offset="100%" stop-color="#a9822f" />
            </linearGradient>
        </defs>
        <rect x="18" y="18" width="64" height="64" rx="10" transform="rotate(45 50 50)" fill="none" stroke="url(#monsory-logo-gold)" stroke-width="3" />
        <text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-family="'Cormorant Garamond', serif" font-size="42" font-weight="600" fill="url(#monsory-logo-gold)">M</text>
    </svg>
</template>
