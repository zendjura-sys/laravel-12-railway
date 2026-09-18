<script setup>
import { ref } from 'vue';

defineProps({
    items: { type: Array, required: true },
    /** 'round' — круглі кадри (аватарки), 'wide' — прямокутні (галерея). */
    variant: { type: String, default: 'wide' },
});

const track = ref(null);

function scrollBy(dir) {
    if (!track.value) return;
    const amount = track.value.clientWidth * 0.8 * dir;
    track.value.scrollBy({ left: amount, behavior: 'smooth' });
}
</script>

<template>
    <div class="group/carousel relative">
        <div
            ref="track"
            class="scrollbar-none flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-smooth pb-2"
            style="scroll-padding-left: 1rem"
        >
            <div
                v-for="(item, i) in items"
                :key="i"
                class="glass-panel group shrink-0 snap-start overflow-hidden"
                :class="variant === 'round' ? 'w-40 text-center sm:w-48' : 'w-[260px] sm:w-[340px]'"
            >
                <div :class="variant === 'round' ? 'aspect-square' : 'aspect-[4/3]'" class="relative overflow-hidden">
                    <img
                        :src="item.url"
                        :alt="item.name || item.caption || 'Фото'"
                        loading="lazy"
                        decoding="async"
                        class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.06]"
                    />
                    <div class="glass-sheen"></div>
                </div>
                <div v-if="item.name || item.caption" class="p-4">
                    <p class="truncate font-display text-sm text-white">{{ item.name || item.caption }}</p>
                    <p v-if="item.position" class="truncate text-xs text-white/40">{{ item.position }}</p>
                </div>
            </div>
        </div>

        <button
            v-if="items.length > 2"
            type="button"
            aria-label="Назад"
            class="absolute left-0 top-1/2 hidden -translate-x-4 -translate-y-1/2 rounded-full border border-white/15 bg-obsidian-950/80 p-2.5 text-white/70 opacity-0 backdrop-blur-md transition-opacity hover:text-gold-300 sm:flex group-hover/carousel:opacity-100"
            @click="scrollBy(-1)"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <button
            v-if="items.length > 2"
            type="button"
            aria-label="Далі"
            class="absolute right-0 top-1/2 hidden -translate-y-1/2 translate-x-4 rounded-full border border-white/15 bg-obsidian-950/80 p-2.5 text-white/70 opacity-0 backdrop-blur-md transition-opacity hover:text-gold-300 sm:flex group-hover/carousel:opacity-100"
            @click="scrollBy(1)"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>
</template>

<style scoped>
.scrollbar-none {
    scrollbar-width: none;
}
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
</style>
