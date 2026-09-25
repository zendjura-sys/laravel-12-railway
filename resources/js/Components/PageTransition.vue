<script setup>
// Inertia's вбудований <App> не віддає слот для обгортання в <Transition>,
// тому замість повного ремоунту сторінки — легке затемнення на час
// навігації. Разом із золотим progress-баром це дає відчуття "живого"
// застосунку, а не миттєвого перемикання білого екрана.
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const navigating = ref(false);
router.on('start', () => (navigating.value = true));
router.on('finish', () => (navigating.value = false));
</script>

<template>
    <div class="page-fade" :class="navigating && 'is-navigating'">
        <slot />
    </div>
</template>

<style scoped>
.page-fade {
    transition: opacity 0.18s ease;
}
.page-fade.is-navigating {
    opacity: 0.55;
}
</style>
