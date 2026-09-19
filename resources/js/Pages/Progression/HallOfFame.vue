<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    records: { type: Object, required: true },
});

const SECTIONS = [
    { key: 'xp', icon: '🏆', title: 'Найбільше досвіду за весь час', unit: 'очок досвіду' },
    { key: 'streak', icon: '🔥', title: 'Найдовша серія перемог', unit: 'перемог поспіль' },
    { key: 'bizwar', icon: '⚔', title: 'Найбільше перемог у бізварі', unit: 'перемог' },
    { key: 'bonuses', icon: '💰', title: 'Найбільше премій за весь час', unit: '₴', money: true },
    { key: 'veterans', icon: '⭐', title: 'Найдавніші учасники родини', unit: 'днів у родині' },
];

const rankMeta = [
    { label: '1', class: 'border-gold-400/50 bg-gold-400/10 text-gold-200' },
    { label: '2', class: 'border-white/25 bg-white/[0.06] text-white/70' },
    { label: '3', class: 'border-gold-600/30 bg-gold-600/[0.08] text-gold-500/80' },
];

function fmtValue(value, section) {
    if (value === null || value === undefined) return '—';
    return section.money ? value.toLocaleString('uk-UA') : value;
}

function pluralizeDays(count) {
    const mod10 = count % 10;
    const mod100 = count % 100;
    if (mod10 === 1 && mod100 !== 11) return 'день';
    if ([2, 3, 4].includes(mod10) && ![12, 13, 14].includes(mod100)) return 'дні';
    return 'днів';
}

function unitFor(section, value) {
    return section.key === 'veterans' ? pluralizeDays(value) + ' у родині' : section.unit;
}
</script>

<template>
    <Head title="Зал слави" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto max-w-4xl px-6 py-6">
                <Link
                    v-if="route().has('progression.leaderboard')"
                    :href="route('progression.leaderboard')"
                    class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300"
                >
                    ← Рейтинг родини
                </Link>
                <h1 class="font-display mt-2 text-3xl font-light text-white">
                    Зал <span class="text-gradient-gold italic">слави</span>
                </h1>
                <p class="mt-2 max-w-xl text-sm text-white/40">
                    Не поточний стан, а рекорди за весь час існування родини — навіть якщо рекорд уже перевершили самі не помітивши.
                </p>
            </div>
        </header>

        <div class="mx-auto grid max-w-4xl gap-6 px-6 py-10 sm:grid-cols-2">
            <section
                v-for="(section, i) in SECTIONS.filter((s) => records[s.key]?.length > 0)"
                :key="section.key"
                v-reveal v-glow
                :class="`glass-panel p-6`"
                :style="{ transitionDelay: `${i * 80}ms` }"
            >
                <h2 class="font-display mb-4 flex items-center gap-2 text-lg text-white">
                    <span>{{ section.icon }}</span> {{ section.title }}
                </h2>

                <div class="space-y-2">
                    <div
                        v-for="(entry, idx) in records[section.key]"
                        :key="idx"
                        class="flex items-center gap-3 rounded-xl border border-white/5 bg-white/[0.02] px-4 py-2.5"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border font-display text-sm"
                            :class="rankMeta[idx]?.class"
                        >
                            {{ rankMeta[idx]?.label }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-white">{{ entry.name }}</p>
                            <p v-if="entry.position" class="truncate text-xs text-white/35">{{ entry.position }}</p>
                        </div>
                        <span class="shrink-0 whitespace-nowrap font-display text-base text-gold-300">
                            {{ fmtValue(entry.value, section) }} {{ unitFor(section, entry.value) }}
                        </span>
                    </div>
                </div>
            </section>

            <div v-if="SECTIONS.every((s) => !records[s.key]?.length)" class="glass-panel col-span-full p-12 text-center text-white/30">
                Рекордів поки немає — попереду ще все.
            </div>
        </div>
    </div>
</template>
