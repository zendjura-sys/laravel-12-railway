<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

// Тонкая золотая нить прогресса чтения. Масштабируем transform, а не width:
// width пересчитывает layout на каждый кадр скролла, transform идёт только
// через композитор — на длинной главной это разница между гладким скроллом
// и подёргиванием.
const scale = ref(0);
let ticking = false;

function measure() {
    ticking = false;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    scale.value = max > 0 ? Math.min(window.scrollY / max, 1) : 0;
}

function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(measure);
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    measure();
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);
});
</script>

<template>
    <div
        class="scroll-progress w-full"
        :style="{ transform: `scaleX(${scale})` }"
        aria-hidden="true"
    ></div>
</template>
