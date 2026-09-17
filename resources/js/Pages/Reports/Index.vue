<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    reports: { type: Object, required: true },
});

const today = new Date().toISOString().slice(0, 10);

// Погодинні слоти капта — той самий список, що й на бекенді
// (Report::KAPT_TIMES), фіксується для майбутнього підрахунку премій.
const kaptTimes = ['10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00'];

const form = useForm({
    type: 'contract',
    report_date: today,
    wins_count: 0,
    losses_count: 0,
    kapt_times: [],
    light_count: 0,
    medium_count: 0,
    heavy_count: 0,
    amount: null,
    description: '',
});

const showForm = ref(false);

function toggleKaptTime(time) {
    const idx = form.kapt_times.indexOf(time);
    if (idx === -1) {
        form.kapt_times.push(time);
    } else {
        form.kapt_times.splice(idx, 1);
    }
}

function submit() {
    form.post(route('reports.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('description', 'wins_count', 'losses_count', 'kapt_times', 'light_count', 'medium_count', 'heavy_count', 'amount');
            showForm.value = false;
        },
    });
}

const statusMeta = {
    pending: { label: 'На розгляді', class: 'bg-gold-400/15 text-gold-300 border-gold-400/30' },
    approved: { label: 'Затверджено', class: 'bg-emerald-400/15 text-emerald-300 border-emerald-400/30' },
    rejected: { label: 'Відхилено', class: 'bg-ember-500/15 text-ember-500 border-ember-500/30' },
};

const typeLabels = { kapt: 'KAPT', contract: 'Контракт', bizwar: 'Бізвар', investment: 'Інвестиції', other: 'Інше' };

function fmtDate(iso) {
    return new Date(iso).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function fmtDateOnly(iso) {
    return new Date(iso).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
</script>

<template>
    <Head title="Мої звіти" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-6">
                <div>
                    <Link :href="route('dashboard')" class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300">
                        ← Кабінет
                    </Link>
                    <h1 class="font-display mt-2 text-3xl font-semibold text-white">
                        Мої <span class="text-gradient-gold italic">звіти</span>
                    </h1>
                </div>
                <button
                    class="rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-6 py-3 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03]"
                    @click="showForm = !showForm"
                >
                    {{ showForm ? 'Скасувати' : 'Подати звіт' }}
                </button>
            </div>
        </header>

        <div class="mx-auto max-w-5xl px-6 py-10">
            <Transition name="fade-slide">
                <form v-if="showForm" class="mb-10 rounded-2xl border border-white/10 bg-white/[0.03] p-6" @submit.prevent="submit">
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Тип</label>
                        <select v-model="form.type" class="w-full max-w-xs rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white">
                            <option value="contract">Контракт</option>
                            <option value="bizwar">Бізвар</option>
                            <option value="investment">Інвестиції</option>
                            <option value="other">Інше</option>
                        </select>
                    </div>

                    <!-- Бізвар: дата, перемоги/поразки, час капта -->
                    <div v-if="form.type === 'bizwar'" class="mt-5 space-y-5">
                        <div class="grid gap-5 sm:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Дата</label>
                                <input v-model="form.report_date" type="date" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                                <p v-if="form.errors.report_date" class="mt-1 text-xs text-ember-500">{{ form.errors.report_date }}</p>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Перемог</label>
                                <input v-model.number="form.wins_count" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                                <p v-if="form.errors.wins_count" class="mt-1 text-xs text-ember-500">{{ form.errors.wins_count }}</p>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Поразок</label>
                                <input v-model.number="form.losses_count" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                                <p v-if="form.errors.losses_count" class="mt-1 text-xs text-ember-500">{{ form.errors.losses_count }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Час капта</label>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="time in kaptTimes"
                                    :key="time"
                                    class="cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium tracking-wide transition-colors"
                                    :class="form.kapt_times.includes(time)
                                        ? 'border-gold-400/50 bg-gold-400/10 text-gold-200'
                                        : 'border-white/10 text-white/40 hover:border-white/25 hover:text-white'"
                                >
                                    <input type="checkbox" class="hidden" :checked="form.kapt_times.includes(time)" @change="toggleKaptTime(time)" />
                                    {{ time }}
                                </label>
                            </div>
                            <p v-if="form.errors.kapt_times" class="mt-1 text-xs text-ember-500">{{ form.errors.kapt_times }}</p>
                        </div>
                    </div>

                    <!-- Контракт: дата виконання й кількість за кожною вагою -->
                    <div v-else-if="form.type === 'contract'" class="mt-5 space-y-5">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Дата виконання контрактів</label>
                            <input v-model="form.report_date" type="date" class="w-full max-w-xs rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                            <p v-if="form.errors.report_date" class="mt-1 text-xs text-ember-500">{{ form.errors.report_date }}</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Легкий</label>
                                <input v-model.number="form.light_count" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                                <p v-if="form.errors.light_count" class="mt-1 text-xs text-ember-500">{{ form.errors.light_count }}</p>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Середній</label>
                                <input v-model.number="form.medium_count" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                                <p v-if="form.errors.medium_count" class="mt-1 text-xs text-ember-500">{{ form.errors.medium_count }}</p>
                            </div>
                            <div>
                                <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Тяжкий</label>
                                <input v-model.number="form.heavy_count" type="number" min="0" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                                <p v-if="form.errors.heavy_count" class="mt-1 text-xs text-ember-500">{{ form.errors.heavy_count }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Інвестиції: сума -->
                    <div v-else-if="form.type === 'investment'" class="mt-5 max-w-xs">
                        <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Сума</label>
                        <input
                            v-model.number="form.amount"
                            type="number"
                            min="0"
                            class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white placeholder:text-white/30"
                            placeholder="0"
                        />
                        <p v-if="form.errors.amount" class="mt-1 text-xs text-ember-500">{{ form.errors.amount }}</p>
                    </div>

                    <div class="mt-5">
                        <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">
                            Опис {{ form.type === 'bizwar' ? '(необов\'язково — напр. причина поразки)' : '(необов\'язково)' }}
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white placeholder:text-white/30"
                            placeholder="Деталі операції..."
                        ></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-xs text-ember-500">{{ form.errors.description }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="mt-5 rounded-full border border-gold-400/40 px-6 py-2.5 text-sm font-medium tracking-widest text-gold-200 transition-all hover:border-gold-300 disabled:opacity-40"
                    >
                        Надіслати на розгляд
                    </button>
                </form>
            </Transition>

            <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
                <div
                    v-for="report in reports.data"
                    :key="report.id"
                    class="flex items-center justify-between gap-4 border-b border-white/5 px-6 py-4 last:border-0"
                >
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-white">{{ typeLabels[report.type] }}</span>
                            <span v-if="report.outcome" class="text-xs uppercase text-white/40">{{ report.outcome }}</span>
                            <span v-if="report.weight" class="text-xs uppercase text-white/40">{{ report.weight }}</span>
                            <span v-if="report.wins_count !== null" class="text-xs uppercase text-white/40">W: {{ report.wins_count }}</span>
                            <span v-if="report.losses_count !== null" class="text-xs uppercase text-white/40">L: {{ report.losses_count }}</span>
                            <span v-if="report.kapt_times?.length" class="text-xs uppercase text-white/40">{{ report.kapt_times.join(', ') }}</span>
                            <span v-if="report.light_count !== null" class="text-xs uppercase text-white/40">
                                л:{{ report.light_count }} с:{{ report.medium_count }} т:{{ report.heavy_count }}
                            </span>
                            <span v-if="report.amount" class="text-xs uppercase text-white/40">{{ report.amount.toLocaleString('uk-UA') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-white/30">
                            {{ report.report_date ? fmtDateOnly(report.report_date) + ' · подано ' : '' }}{{ fmtDate(report.created_at) }}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-medium uppercase tracking-wide" :class="statusMeta[report.status]?.class">
                        {{ statusMeta[report.status]?.label }}
                    </span>
                </div>
                <div v-if="reports.data.length === 0" class="px-6 py-12 text-center text-white/30">
                    Звітів поки немає — подайте перший
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.3s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
