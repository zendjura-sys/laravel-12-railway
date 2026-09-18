<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    payouts: { type: Object, required: true },
    cumulativeInvestment: { type: Number, default: 0 },
    tiers: { type: Array, default: () => [] },
});

function fmt(amount) {
    return new Intl.NumberFormat('uk-UA').format(amount ?? 0) + '₴';
}

function fmtDate(d) {
    return new Date(d).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<template>
    <Head title="Мої премії" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto max-w-3xl px-6 py-6">
                <Link :href="route('dashboard')" class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300">
                    ← Кабінет
                </Link>
                <h1 class="font-display mt-2 text-3xl font-light text-white">Мої премії</h1>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-10">
            <!-- ================= ІНВЕСТИЦІЙНІ ТІРИ ================= -->
            <section v-if="tiers.length" class="mb-8 rounded-2xl border border-white/10 bg-white/[0.02] p-6">
                <h2 class="mb-1 font-display text-lg text-white">Інвестиційні досягнення</h2>
                <p class="mb-4 text-sm text-white/40">Кумулятивно вкладено: {{ fmt(cumulativeInvestment) }}</p>
                <div class="space-y-2">
                    <div
                        v-for="tier in tiers"
                        :key="tier.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-4 py-2.5"
                        :class="tier.earned ? 'border-gold-400/30 bg-gold-400/[0.04]' : 'border-white/10'"
                    >
                        <div>
                            <span class="font-medium" :class="tier.earned ? 'text-gold-200' : 'text-white'">
                                {{ tier.earned ? '✓' : '' }} {{ tier.label }}
                            </span>
                            <span class="ml-3 text-sm text-white/40">від {{ fmt(tier.threshold_amount) }} → +{{ fmt(tier.bonus_amount) }}</span>
                        </div>
                        <span v-if="!tier.earned && cumulativeInvestment < tier.threshold_amount" class="shrink-0 whitespace-nowrap text-xs text-white/30">
                            лишилось {{ fmt(tier.threshold_amount - cumulativeInvestment) }}
                        </span>
                    </div>
                </div>
            </section>

            <!-- ================= ІСТОРІЯ НАРАХУВАНЬ ================= -->
            <section class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
                <h2 class="p-6 pb-4 font-display text-lg text-white">Історія нарахувань</h2>
                <div
                    v-for="p in payouts.data"
                    :key="p.id"
                    class="border-b border-white/5 px-6 py-4 last:border-0"
                >
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-sm text-white/50">Тиждень від {{ fmtDate(p.week_start) }}</span>
                        <span class="shrink-0 text-lg font-semibold text-gold-200">{{ fmt(p.total_amount) }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-white/40">
                        <span v-if="p.bizwar_amount > 0">Бізвар: {{ fmt(p.bizwar_amount) }} <template v-if="p.bizwar_winrate !== null">({{ p.bizwar_winrate }}%)</template></span>
                        <span v-if="p.contract_amount > 0">Контракти: {{ fmt(p.contract_amount) }} (×{{ p.contracts_count }})</span>
                        <span v-if="p.streak_bonus_amount > 0">Серія перемог: +{{ fmt(p.streak_bonus_amount) }}</span>
                        <span v-if="p.contracts_count_bonus_amount > 0">К-сть контрактів: +{{ fmt(p.contracts_count_bonus_amount) }}</span>
                        <span v-if="p.investment_bonus_amount > 0">Інвестиційний тір: +{{ fmt(p.investment_bonus_amount) }}</span>
                    </div>
                    <p class="mt-2 text-[11px]" :class="p.paid ? 'text-emerald-400/70' : 'text-white/30'">
                        {{ p.paid ? '✓ виплачено' : 'ще не виплачено' }}
                    </p>
                </div>
                <div v-if="payouts.data.length === 0" class="px-6 py-12 text-center text-white/30">
                    Нарахувань ще не було
                </div>
            </section>

            <div v-if="payouts.links?.length > 3" class="mt-6 flex flex-wrap gap-2">
                <Link
                    v-for="link in payouts.links"
                    :key="link.label"
                    :href="link.url || ''"
                    v-html="link.label"
                    class="rounded-full border px-3 py-1.5 text-xs"
                    :class="[
                        link.active ? 'border-gold-400/50 bg-gold-400/10 text-gold-200' : 'border-white/10 text-white/40 hover:text-white',
                        !link.url && 'pointer-events-none opacity-30',
                    ]"
                />
            </div>
        </div>
    </div>
</template>
