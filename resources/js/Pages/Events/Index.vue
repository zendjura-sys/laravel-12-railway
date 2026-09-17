<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    events: { type: Array, default: () => [] },
});

function fmtDate(iso) {
    return new Date(iso).toLocaleDateString('uk-UA', { day: '2-digit', month: 'long', year: 'numeric' });
}
function fmtTime(iso) {
    return new Date(iso).toLocaleTimeString('uk-UA', { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Події родини" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-6">
                <div>
                    <Link :href="route('dashboard')" class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300">
                        ← Кабінет
                    </Link>
                    <h1 class="font-display mt-2 text-3xl font-light text-white">
                        Події <span class="text-gradient-gold italic">родини</span>
                    </h1>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <div class="space-y-5">
                <div v-for="event in events" :key="event.id" v-glow class="glass-panel p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <h3 class="font-display text-xl text-white">{{ event.title }}</h3>
                        <span class="shrink-0 rounded-full border border-gold-400/30 bg-gold-400/10 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-gold-300">
                            {{ fmtDate(event.starts_at) }} · {{ fmtTime(event.starts_at) }}
                        </span>
                    </div>
                    <p v-if="event.location" class="mt-2 text-xs uppercase tracking-widest text-white/40">📍 {{ event.location }}</p>
                    <p v-if="event.description" class="mt-3 text-sm leading-relaxed text-white/50">{{ event.description }}</p>
                </div>

                <div v-if="events.length === 0" class="rounded-2xl border border-white/5 bg-white/[0.02] p-12 text-center text-white/30">
                    Найближчих подій поки немає
                </div>
            </div>
        </div>
    </div>
</template>
