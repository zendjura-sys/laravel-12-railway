<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, default: () => [] }, // [{ key, label, value }]
});

const max = computed(() => Math.max(1, ...props.items.map((i) => i.value)));

function fmt(v) {
    return new Intl.NumberFormat('uk-UA').format(v ?? 0);
}
</script>

<template>
    <div v-if="items.length" class="space-y-3">
        <div v-for="item in items" :key="item.key" class="flex items-center gap-3">
            <span class="w-40 shrink-0 truncate text-xs text-white/50 sm:w-48">{{ item.label }}</span>
            <div class="h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-white/[0.06]">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-gold-600 via-gold-400 to-gold-200 transition-all duration-700"
                    :style="{ width: `${Math.max(2, (item.value / max) * 100)}%` }"
                ></div>
            </div>
            <span class="w-20 shrink-0 text-right font-display text-sm text-gold-200">{{ fmt(item.value) }}</span>
        </div>
    </div>
</template>
