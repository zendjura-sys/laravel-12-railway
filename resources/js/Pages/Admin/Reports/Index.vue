<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    reports: { type: Object, required: true },
    status: { type: String, default: 'pending' },
});

const toasts = ref([]);
let toastId = 0;
function pushToast(ok, message) {
    const id = ++toastId;
    toasts.value.push({ id, ok, message });
    setTimeout(() => (toasts.value = toasts.value.filter((t) => t.id !== id)), 4000);
}

const busy = ref(null);
async function act(report, action) {
    busy.value = report.id;
    try {
        const { data } = await window.axios.post(`/admin/reports/${report.id}/${action}`);
        pushToast(data.ok, data.message);
        if (data.ok) router.reload({ only: ['reports'] });
    } catch (e) {
        pushToast(false, e.response?.data?.message || 'Помилка');
    } finally {
        busy.value = null;
    }
}

function switchStatus(s) {
    router.get(route('admin.reports.index'), { status: s }, { preserveState: true, preserveScroll: true });
}

const typeLabels = { kapt: 'KAPT', contract: 'Контракт', other: 'Інше' };
function fmtDate(iso) {
    return new Date(iso).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Модерація звітів" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <div class="pointer-events-none fixed right-6 top-6 z-[100] flex w-full max-w-sm flex-col gap-3">
            <TransitionGroup name="toast">
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-2xl backdrop-blur-md"
                    :class="t.ok ? 'border-emerald-400/30 bg-emerald-950/80 text-emerald-200' : 'border-ember-500/30 bg-ember-600/20 text-ember-500'"
                >
                    {{ t.message }}
                </div>
            </TransitionGroup>
        </div>

        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                <div>
                    <Link :href="route('dashboard')" class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300">
                        ← Кабінет
                    </Link>
                    <h1 class="font-display mt-2 text-3xl font-semibold text-white">
                        Модерація <span class="text-gradient-gold italic">звітів</span>
                    </h1>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-6xl px-6 py-10">
            <div class="mb-6 flex gap-2">
                <button
                    v-for="s in ['pending', 'approved', 'rejected']"
                    :key="s"
                    class="rounded-full border px-4 py-2 text-sm transition-all"
                    :class="status === s ? 'border-gold-400/50 bg-gold-400/10 text-gold-200' : 'border-white/10 text-white/50 hover:border-white/25'"
                    @click="switchStatus(s)"
                >
                    {{ s === 'pending' ? 'На розгляді' : s === 'approved' ? 'Затверджені' : 'Відхилені' }}
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
                <div
                    v-for="report in reports.data"
                    :key="report.id"
                    class="flex flex-wrap items-center justify-between gap-4 border-b border-white/5 px-6 py-4 last:border-0"
                >
                    <div>
                        <div class="flex items-center gap-2 text-white">
                            <span class="font-medium">{{ report.user?.name }}</span>
                            <span class="text-white/30">·</span>
                            <span>{{ typeLabels[report.type] }}</span>
                            <span v-if="report.outcome" class="text-xs uppercase text-white/40">{{ report.outcome }}</span>
                            <span v-if="report.weight" class="text-xs uppercase text-white/40">{{ report.weight }}</span>
                        </div>
                        <p v-if="report.description" class="mt-1 text-sm text-white/50">{{ report.description }}</p>
                        <p class="mt-1 text-xs text-white/30">
                            подав {{ report.submitter?.name }} · {{ fmtDate(report.created_at) }}
                        </p>
                    </div>
                    <div v-if="report.status === 'pending'" class="flex gap-2">
                        <button
                            :disabled="busy === report.id"
                            class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-4 py-1.5 text-xs font-medium text-emerald-300 hover:bg-emerald-400/20 disabled:opacity-40"
                            @click="act(report, 'approve')"
                        >
                            Затвердити
                        </button>
                        <button
                            :disabled="busy === report.id"
                            class="rounded-full border border-ember-500/25 px-4 py-1.5 text-xs font-medium text-ember-500/80 hover:bg-ember-600/10 disabled:opacity-40"
                            @click="act(report, 'reject')"
                        >
                            Відхилити
                        </button>
                    </div>
                </div>
                <div v-if="reports.data.length === 0" class="px-6 py-12 text-center text-white/30">
                    Нічого немає в цій категорії
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(30px);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(30px) scale(0.95);
}
</style>
