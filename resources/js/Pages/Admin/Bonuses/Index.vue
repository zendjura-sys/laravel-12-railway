<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    settings: { type: Object, required: true },
    tiers: { type: Array, required: true },
    payouts: { type: Object, required: true },
    filters: { type: Object, default: () => ({ from: '', to: '', paid: '' }) },
});

const filterForm = ref({ from: props.filters.from, to: props.filters.to, paid: props.filters.paid });
function applyFilters() {
    router.get(route('admin.bonuses.index'), filterForm.value, { preserveState: true, preserveScroll: true });
}
function resetFilters() {
    filterForm.value = { from: '', to: '', paid: '' };
    router.get(route('admin.bonuses.index'), {}, { preserveState: true, preserveScroll: true });
}

function describeFailure(e, fallback) {
    const serverMessage = e.response?.data?.message;
    if (serverMessage) return serverMessage;
    if (!e.response) return `${fallback} (немає відповіді сервера — перевірте з'єднання)`;
    if (e.response.status === 403) return `${fallback} (немає прав)`;
    return `${fallback} (код ${e.response.status})`;
}

function fmt(amount) {
    return new Intl.NumberFormat('uk-UA').format(amount ?? 0) + '₴';
}

function fmtDate(d) {
    return new Date(d).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

/* ------------------------- Налаштування ставок ------------------------- */

const settingsForm = useForm({
    bizwar_base_rate: props.settings.bizwar_base_rate,
    contract_light_rate: props.settings.contract_light_rate,
    contract_medium_rate: props.settings.contract_medium_rate,
    contract_heavy_rate: props.settings.contract_heavy_rate,
    streak_threshold: props.settings.streak_threshold,
    streak_bonus_amount: props.settings.streak_bonus_amount,
    contracts_count_threshold: props.settings.contracts_count_threshold,
    contracts_count_bonus_amount: props.settings.contracts_count_bonus_amount,
    min_digest_amount: props.settings.min_digest_amount,
});

function saveSettings() {
    settingsForm.put(route('admin.bonuses.settings.update'), { preserveScroll: true });
}

/* ------------------------- Інвестиційні тіри ------------------------- */

const tierForm = useForm({ label: '', threshold_amount: '', bonus_amount: '' });
function addTier() {
    tierForm.post(route('admin.bonuses.tiers.store'), {
        preserveScroll: true,
        onSuccess: () => tierForm.reset(),
    });
}

function removeTier(tier) {
    if (!confirm(`Видалити тір «${tier.label}»?`)) return;
    router.delete(route('admin.bonuses.tiers.destroy', tier.id), { preserveScroll: true });
}

/* ------------------------- Виплати ------------------------- */

const markingPaid = ref(null);
async function markPaid(payout) {
    markingPaid.value = payout.id;
    try {
        await window.axios.post(route('admin.bonuses.payouts.mark-paid', payout.id));
        payout.paid = true;
    } catch (e) {
        alert(describeFailure(e, 'Не вдалося позначити виплаченим'));
    } finally {
        markingPaid.value = null;
    }
}

const running = ref(false);
async function runNow() {
    if (!confirm('Порахувати поточний тиждень і надіслати дайджест у Telegram зараз?')) return;
    running.value = true;
    try {
        await window.axios.post(route('admin.bonuses.run-now'));
        router.reload({ only: ['payouts'] });
    } catch (e) {
        alert(describeFailure(e, 'Не вдалося запустити розрахунок'));
    } finally {
        running.value = false;
    }
}
</script>

<template>
    <Head title="Премії — Monsory Connect" />

    <AdminLayout title="Премії">
        <div class="mb-6 flex justify-end">
            <button
                :disabled="running"
                class="rounded-full border border-gold-400/40 px-5 py-2 text-xs font-medium uppercase tracking-widest text-gold-200 transition-all hover:border-gold-300 disabled:opacity-40"
                @click="runNow"
            >
                {{ running ? 'Рахую…' : '⟳ Розрахувати зараз' }}
            </button>
        </div>

        <!-- ================= СТАВКИ ================= -->
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="mb-4 font-display text-lg text-white">Ставки й пороги</h2>
            <form class="grid gap-5 sm:grid-cols-2" @submit.prevent="saveSettings">
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-white/40">Бізвар — ставка за 100% перемог (₴)</label>
                    <input v-model.number="settingsForm.bizwar_base_rate" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                    <p class="mt-1 text-[11px] text-white/30">Виплата = ставка × частка перемог за тиждень (90% перемог → 0.9 × ставка)</p>
                </div>
                <div></div>

                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-white/40">Контракт — легкий (₴)</label>
                    <input v-model.number="settingsForm.contract_light_rate" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-white/40">Контракт — середній (₴)</label>
                    <input v-model.number="settingsForm.contract_medium_rate" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-white/40">Контракт — важкий (₴)</label>
                    <input v-model.number="settingsForm.contract_heavy_rate" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                </div>
                <div></div>

                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-white/40">Бонус за серію перемог — поріг (к-сть поспіль)</label>
                    <input v-model.number="settingsForm.streak_threshold" type="number" min="1" placeholder="вимкнено" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-white/40">Бонус за серію перемог — сума (₴)</label>
                    <input v-model.number="settingsForm.streak_bonus_amount" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                </div>

                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-white/40">Бонус за к-сть контрактів за тиждень — поріг</label>
                    <input v-model.number="settingsForm.contracts_count_threshold" type="number" min="1" placeholder="вимкнено" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-white/40">Бонус за к-сть контрактів — сума (₴)</label>
                    <input v-model.number="settingsForm.contracts_count_bonus_amount" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                </div>

                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-white/40">Мінімум для дайджесту в Telegram (₴)</label>
                    <input v-model.number="settingsForm.min_digest_amount" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                </div>

                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="settingsForm.processing"
                        class="rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-6 py-2.5 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03] disabled:opacity-40"
                    >
                        Зберегти
                    </button>
                </div>
            </form>
        </section>

        <!-- ================= ІНВЕСТИЦІЙНІ ТІРИ ================= -->
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="mb-4 font-display text-lg text-white">Досягнення за інвестиції</h2>

            <div v-if="tiers.length" class="mb-5 space-y-2">
                <div v-for="tier in tiers" :key="tier.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-white/10 px-4 py-2.5">
                    <div>
                        <span class="font-medium text-white">{{ tier.label }}</span>
                        <span class="ml-3 text-sm text-white/40">від {{ fmt(tier.threshold_amount) }} кумулятивно → +{{ fmt(tier.bonus_amount) }}</span>
                    </div>
                    <button class="shrink-0 text-xs text-ember-500/70 hover:text-ember-500" @click="removeTier(tier)">Видалити</button>
                </div>
            </div>
            <p v-else class="mb-5 text-sm text-white/30">Тірів ще немає.</p>

            <form class="grid gap-3 sm:grid-cols-4" @submit.prevent="addTier">
                <input v-model="tierForm.label" type="text" placeholder="Назва (напр. Інвестор I)" class="rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-sm text-white sm:col-span-2" />
                <input v-model.number="tierForm.threshold_amount" type="number" min="1" placeholder="Поріг, ₴" class="rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-sm text-white" />
                <input v-model.number="tierForm.bonus_amount" type="number" min="0" placeholder="Премія, ₴" class="rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-sm text-white" />
                <div class="sm:col-span-4">
                    <button
                        type="submit"
                        :disabled="tierForm.processing"
                        class="rounded-full border border-gold-400/40 px-5 py-2 text-xs font-medium uppercase tracking-widest text-gold-200 hover:border-gold-300 disabled:opacity-40"
                    >
                        + Додати тір
                    </button>
                </div>
            </form>
        </section>

        <!-- ================= ІСТОРІЯ ВИПЛАТ ================= -->
        <section class="rounded-2xl border border-white/10 bg-white/[0.02]">
            <div class="flex flex-wrap items-end justify-between gap-3 p-6 pb-0">
                <h2 class="font-display text-lg text-white">Історія нарахувань</h2>
                <a
                    :href="route('admin.bonuses.export', filterForm)"
                    class="rounded-full border border-white/15 px-4 py-2 text-xs text-white/60 hover:border-white/30"
                >
                    ⬇ Експорт CSV
                </a>
            </div>

            <div class="flex flex-wrap items-end gap-3 p-6 pb-0">
                <div>
                    <label class="mb-1 block text-[10px] uppercase tracking-widest text-white/40">Тиждень від</label>
                    <input v-model="filterForm.from" type="date" class="rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-sm text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-[10px] uppercase tracking-widest text-white/40">Тиждень до</label>
                    <input v-model="filterForm.to" type="date" class="rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-sm text-white" />
                </div>
                <div>
                    <label class="mb-1 block text-[10px] uppercase tracking-widest text-white/40">Виплата</label>
                    <select v-model="filterForm.paid" class="rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-sm text-white focus:border-gold-400/50 focus:outline-none">
                        <option value="">Усі</option>
                        <option value="1">Виплачено</option>
                        <option value="0">Не виплачено</option>
                    </select>
                </div>
                <button type="button" class="rounded-full border border-gold-400/40 px-4 py-2 text-xs font-medium tracking-widest text-gold-200 hover:border-gold-300" @click="applyFilters">
                    Застосувати
                </button>
                <button
                    v-if="filters.from || filters.to || filters.paid"
                    type="button"
                    class="text-xs text-white/40 hover:text-white"
                    @click="resetFilters"
                >
                    Скинути
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="mt-4 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-[11px] uppercase tracking-widest text-white/30">
                            <th class="px-6 py-2">Тиждень</th>
                            <th class="px-3 py-2">Учасник</th>
                            <th class="px-3 py-2">Бізвар</th>
                            <th class="px-3 py-2">Контракти</th>
                            <th class="px-3 py-2">Бонуси</th>
                            <th class="px-3 py-2">Разом</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in payouts.data" :key="p.id" class="border-b border-white/5">
                            <td class="px-6 py-3 text-white/50">{{ fmtDate(p.week_start) }}</td>
                            <td class="px-3 py-3 text-white">{{ p.user?.name }}</td>
                            <td class="px-3 py-3 text-white/60">
                                {{ fmt(p.bizwar_amount) }}
                                <span v-if="p.bizwar_winrate !== null" class="text-white/30">({{ p.bizwar_winrate }}%)</span>
                            </td>
                            <td class="px-3 py-3 text-white/60">{{ fmt(p.contract_amount) }} <span class="text-white/30">×{{ p.contracts_count }}</span></td>
                            <td class="px-3 py-3 text-white/60">{{ fmt(p.streak_bonus_amount + p.contracts_count_bonus_amount + p.investment_bonus_amount) }}</td>
                            <td class="px-3 py-3 font-medium text-gold-200">{{ fmt(p.total_amount) }}</td>
                            <td class="px-3 py-3">
                                <span v-if="p.paid" class="text-xs text-emerald-400/70">✓ виплачено</span>
                                <button
                                    v-else
                                    :disabled="markingPaid === p.id"
                                    class="rounded-full border border-white/15 px-3 py-1 text-xs text-white/50 hover:border-gold-400/40 hover:text-gold-200 disabled:opacity-40"
                                    @click="markPaid(p)"
                                >
                                    Позначити виплаченим
                                </button>
                            </td>
                        </tr>
                        <tr v-if="payouts.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-white/30">Нарахувань ще не було</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="payouts.links?.length > 3" class="flex flex-wrap gap-2 p-6 pt-4">
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
        </section>
    </AdminLayout>
</template>
