<script setup>
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    date: { type: String, required: true },
    sessions: { type: Array, default: () => [] },
});

function changeDate(event) {
    router.get(route('reports.schedule'), { date: event.target.value }, { preserveState: true });
}

function shiftDate(days) {
    const d = new Date(props.date + 'T00:00:00');
    d.setDate(d.getDate() + days);
    router.get(route('reports.schedule'), { date: d.toISOString().slice(0, 10) }, { preserveState: true });
}

function fmtTimes(times) {
    if (!times.length) return '—';
    return times.join(', ');
}

const statusMeta = {
    pending: { label: 'На розгляді', class: 'bg-gold-400/15 text-gold-300 border-gold-400/30' },
    approved: { label: 'Затверджено', class: 'bg-emerald-400/15 text-emerald-300 border-emerald-400/30' },
    rejected: { label: 'Відхилено', class: 'bg-ember-500/15 text-ember-500 border-ember-500/30' },
};
</script>

<template>
    <Head title="Розклад капта" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-6">
                <div>
                    <Link :href="route('reports.index')" class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300">
                        ← Мої звіти
                    </Link>
                    <h1 class="font-display mt-2 text-3xl font-semibold text-white">
                        Розклад <span class="text-gradient-gold italic">капта</span>
                    </h1>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <p class="mb-6 text-sm text-white/40">
                Хто був присутній на капті і який фактичний результат бою — без подвоєння,
                коли за один і той самий бій звітує кілька людей.
            </p>

            <div class="mb-8 flex items-center gap-3">
                <button
                    class="rounded-full border border-white/10 px-3 py-1.5 text-sm text-white/60 transition-colors hover:border-gold-400/40 hover:text-white"
                    @click="shiftDate(-1)"
                >
                    ← Попередній день
                </button>
                <input
                    type="date"
                    :value="date"
                    class="rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white"
                    @change="changeDate"
                />
                <button
                    class="rounded-full border border-white/10 px-3 py-1.5 text-sm text-white/60 transition-colors hover:border-gold-400/40 hover:text-white"
                    @click="shiftDate(1)"
                >
                    Наступний день →
                </button>
            </div>

            <div v-if="sessions.length === 0" class="rounded-2xl border border-white/10 bg-white/[0.02] px-6 py-12 text-center text-white/30">
                На цю дату бізвар-звітів немає.
            </div>

            <div v-for="session in sessions" :key="session.times.join(',')" class="mb-5 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/5 px-6 py-4">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-white/40">Час капта</p>
                        <p class="mt-1 text-lg font-medium text-white">{{ fmtTimes(session.times) }}</p>
                    </div>

                    <div class="text-right">
                        <p class="text-xs uppercase tracking-widest text-white/40">Підсумок бою</p>
                        <p class="mt-1 text-2xl font-semibold">
                            <span class="text-emerald-300">{{ session.result.wins }}</span>
                            <span class="text-white/30"> – </span>
                            <span class="text-ember-500">{{ session.result.losses }}</span>
                        </p>
                    </div>
                </div>

                <div v-if="!session.agreement" class="border-b border-gold-400/20 bg-gold-400/5 px-6 py-3 text-xs text-gold-300">
                    ⚠ Учасники назвали різний рахунок —
                    <span v-for="(b, i) in session.result_breakdown" :key="i">
                        {{ b.wins }}–{{ b.losses }} ({{ b.count }}){{ i < session.result_breakdown.length - 1 ? ', ' : '' }}
                    </span>
                    . Показано найпоширеніший варіант; варто уточнити в модерації.
                </div>

                <div class="divide-y divide-white/5">
                    <div
                        v-for="p in session.participants"
                        :key="p.report_id"
                        class="flex items-center justify-between gap-4 px-6 py-3"
                    >
                        <span class="text-sm text-white/80">{{ p.name ?? '—' }}</span>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-white/40">{{ p.wins }}–{{ p.losses }}</span>
                            <span class="shrink-0 rounded-full border px-2.5 py-0.5 text-[10px] font-medium uppercase tracking-wide" :class="statusMeta[p.status]?.class">
                                {{ statusMeta[p.status]?.label }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
