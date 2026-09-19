<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    points: { type: Array, default: () => [] }, // [{ t: iso-string, xp: number }]
    height: { type: Number, default: 72 },
});

const width = 600; // viewBox units — масштабується через viewBox, реальна ширина йде з CSS.
const padY = 8;

const scaled = computed(() => {
    if (props.points.length === 0) return [];

    const values = props.points.map((p) => p.xp);
    const min = Math.min(...values);
    const max = Math.max(...values);
    // Пласка лінія (усі значення однакові) — не ділимо на нуль, просто
    // кладемо крапку по центру висоти.
    const range = max - min || 1;
    const usableHeight = props.height - padY * 2;
    const stepX = props.points.length > 1 ? width / (props.points.length - 1) : 0;

    return props.points.map((p, i) => ({
        x: props.points.length > 1 ? i * stepX : width / 2,
        y: padY + usableHeight - ((p.xp - min) / range) * usableHeight,
        xp: p.xp,
        t: p.t,
    }));
});

const linePath = computed(() => {
    if (scaled.value.length === 0) return '';
    return scaled.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(2)} ${p.y.toFixed(2)}`).join(' ');
});

const areaPath = computed(() => {
    if (scaled.value.length === 0) return '';
    const first = scaled.value[0];
    const last = scaled.value[scaled.value.length - 1];
    return `${linePath.value} L ${last.x.toFixed(2)} ${props.height} L ${first.x.toFixed(2)} ${props.height} Z`;
});

const hoverIndex = ref(null);
const hovered = computed(() => (hoverIndex.value !== null ? scaled.value[hoverIndex.value] : null));

function onMove(e) {
    if (scaled.value.length === 0) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const ratio = (e.clientX - rect.left) / rect.width;
    const idx = Math.round(ratio * (scaled.value.length - 1));
    hoverIndex.value = Math.min(Math.max(idx, 0), scaled.value.length - 1);
}

function onLeave() {
    hoverIndex.value = null;
}

function fmtDate(iso) {
    return new Date(iso).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit' });
}
</script>

<template>
    <div v-if="points.length > 1" class="relative" :style="{ height: height + 'px' }" @mousemove="onMove" @mouseleave="onLeave">
        <svg :viewBox="`0 0 ${width} ${height}`" preserveAspectRatio="none" class="h-full w-full overflow-visible">
            <defs>
                <linearGradient id="sparkline-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="rgb(var(--gold-400))" stop-opacity="0.35" />
                    <stop offset="100%" stop-color="rgb(var(--gold-400))" stop-opacity="0" />
                </linearGradient>
                <linearGradient id="sparkline-line" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="rgb(var(--gold-600))" />
                    <stop offset="100%" stop-color="rgb(var(--gold-200))" />
                </linearGradient>
            </defs>

            <path :d="areaPath" fill="url(#sparkline-fill)" stroke="none" />
            <path :d="linePath" fill="none" stroke="url(#sparkline-line)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

            <g v-if="hovered">
                <line :x1="hovered.x" :x2="hovered.x" y1="0" :y2="height" stroke="rgb(var(--gold-400))" stroke-opacity="0.25" stroke-width="1" />
                <circle :cx="hovered.x" :cy="hovered.y" r="4" fill="rgb(var(--gold-300))" stroke="#060605" stroke-width="1.5" />
            </g>
        </svg>

        <div
            v-if="hovered"
            class="pointer-events-none absolute top-0 -translate-y-full rounded-lg border border-white/10 bg-obsidian-950/95 px-2.5 py-1.5 text-xs whitespace-nowrap text-white shadow-xl"
            :style="{ left: `${(hovered.x / width) * 100}%`, transform: 'translate(-50%, -8px)' }"
        >
            <span class="font-display text-gold-300">{{ hovered.xp }}</span>
            <span class="ml-1 text-white/40">· {{ fmtDate(hovered.t) }}</span>
        </div>
    </div>
</template>
