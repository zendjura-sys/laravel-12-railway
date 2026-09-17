<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    leaderboard: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Рейтинг родини" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-6">
                <div>
                    <Link
                        v-if="route().has('progression.index')"
                        :href="route('progression.index')"
                        class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300"
                    >
                        ← Мій прогрес
                    </Link>
                    <h1 class="font-display mt-2 text-3xl font-semibold text-white">
                        Рейтинг <span class="text-gradient-gold italic">родини</span>
                    </h1>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-10">
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
                <div
                    v-for="(entry, i) in leaderboard"
                    :key="i"
                    class="flex items-center justify-between gap-4 border-b border-white/5 px-6 py-4 last:border-0"
                    :class="i < 3 ? 'bg-gold-400/[0.04]' : ''"
                >
                    <div class="flex items-center gap-4">
                        <span
                            class="font-display w-8 text-center text-xl"
                            :class="i === 0 ? 'text-gold-300' : i < 3 ? 'text-gold-400/60' : 'text-white/30'"
                        >
                            {{ i + 1 }}
                        </span>
                        <div>
                            <div class="font-medium text-white">{{ entry.name }}</div>
                            <div class="text-xs text-white/40">{{ entry.position || 'без посади' }} · рівень активності {{ entry.level }}</div>
                        </div>
                    </div>
                    <span class="font-display text-xl text-gold-300">{{ entry.xp }} XP</span>
                </div>
                <div v-if="leaderboard.length === 0" class="px-6 py-12 text-center text-white/30">
                    Поки що порожньо
                </div>
            </div>
        </div>
    </div>
</template>
