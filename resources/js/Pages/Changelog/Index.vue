<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    entries: { type: Array, default: () => [] },
});

function fmtDate(iso) {
    return new Date(iso).toLocaleDateString('uk-UA', { day: '2-digit', month: 'long', year: 'numeric' });
}
</script>

<template>
    <Head title="Що нового" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto max-w-3xl px-6 py-6">
                <Link :href="route('dashboard')" class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300">
                    ← Кабінет
                </Link>
                <h1 class="font-display mt-2 text-3xl font-light text-white">
                    Що <span class="text-gradient-gold italic">нового</span>
                </h1>
                <p class="mt-2 text-sm text-white/40">Останні зміни й нові можливості сайту родини.</p>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-10">
            <div v-if="entries.length > 0" class="relative space-y-6 border-l border-white/10 pl-8">
                <div
                    v-for="entry in entries"
                    :key="entry.id"
                    v-reveal
                    v-glow
                    class="glass-panel relative p-5"
                >
                    <span class="absolute -left-[calc(2rem+5px)] top-6 h-2.5 w-2.5 rounded-full bg-gold-400 shadow-gold"></span>
                    <p class="text-[11px] uppercase tracking-widest text-gold-300/70">{{ fmtDate(entry.published_at) }}</p>
                    <h2 class="font-display mt-1 text-lg text-white">{{ entry.title }}</h2>
                    <p v-if="entry.description" class="mt-2 whitespace-pre-line text-sm leading-relaxed text-white/60">{{ entry.description }}</p>
                </div>
            </div>
            <div v-else class="rounded-2xl border border-white/5 bg-white/[0.02] p-12 text-center text-white/30">
                Записів поки немає.
            </div>
        </div>
    </div>
</template>
