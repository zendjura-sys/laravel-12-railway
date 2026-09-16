<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    reports: { type: Object, required: true },
});

const form = useForm({
    type: 'kapt',
    outcome: 'win',
    weight: 'light',
    description: '',
});

const showForm = ref(false);

function submit() {
    form.post(route('reports.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('description');
            showForm.value = false;
        },
    });
}

const statusMeta = {
    pending: { label: 'На розгляді', class: 'bg-gold-400/15 text-gold-300 border-gold-400/30' },
    approved: { label: 'Затверджено', class: 'bg-emerald-400/15 text-emerald-300 border-emerald-400/30' },
    rejected: { label: 'Відхилено', class: 'bg-ember-500/15 text-ember-500 border-ember-500/30' },
};

const typeLabels = { kapt: 'KAPT', contract: 'Контракт', other: 'Інше' };

function fmtDate(iso) {
    return new Date(iso).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
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
                    <div class="grid gap-5 sm:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Тип</label>
                            <select v-model="form.type" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white">
                                <option value="kapt">KAPT</option>
                                <option value="contract">Контракт</option>
                                <option value="other">Інше</option>
                            </select>
                        </div>
                        <div v-if="form.type === 'kapt'">
                            <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Результат</label>
                            <select v-model="form.outcome" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white">
                                <option value="win">Win</option>
                                <option value="loss">Loss</option>
                            </select>
                        </div>
                        <div v-if="form.type === 'contract'">
                            <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Вага</label>
                            <select v-model="form.weight" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white">
                                <option value="light">Light</option>
                                <option value="medium">Medium</option>
                                <option value="heavy">Heavy</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5">
                        <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Опис (необов'язково)</label>
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
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-white">{{ typeLabels[report.type] }}</span>
                            <span v-if="report.outcome" class="text-xs uppercase text-white/40">{{ report.outcome }}</span>
                            <span v-if="report.weight" class="text-xs uppercase text-white/40">{{ report.weight }}</span>
                        </div>
                        <p class="mt-1 text-xs text-white/30">{{ fmtDate(report.created_at) }}</p>
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
