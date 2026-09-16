<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    configured: { type: Boolean, required: true },
    botUsername: { type: String, default: null },
    webhookInfo: { type: Object, default: null },
    linkedCount: { type: Number, default: 0 },
});

const webhookInfo = ref(props.webhookInfo);
const busy = ref(false);

/* ---------- сповіщення ---------- */
const toasts = ref([]);
let toastId = 0;
function pushToast(ok, message) {
    const id = ++toastId;
    toasts.value.push({ id, ok, message });
    setTimeout(() => (toasts.value = toasts.value.filter((t) => t.id !== id)), 5000);
}

async function setupWebhook() {
    busy.value = true;
    try {
        const { data } = await window.axios.post(route('admin.telegram.webhook.setup'));
        pushToast(data.ok, data.message);
        if (data.ok) window.location.reload();
    } catch (e) {
        pushToast(false, e.response?.data?.message || 'Помилка');
    } finally {
        busy.value = false;
    }
}

async function removeWebhook() {
    if (!confirm('Зняти webhook? Бот перестане отримувати повідомлення.')) return;
    busy.value = true;
    try {
        const { data } = await window.axios.delete(route('admin.telegram.webhook.remove'));
        pushToast(data.ok, data.message);
        if (data.ok) window.location.reload();
    } catch (e) {
        pushToast(false, e.response?.data?.message || 'Помилка');
    } finally {
        busy.value = false;
    }
}

const hasActiveWebhook = webhookInfo.value?.url && webhookInfo.value.url.length > 0;
</script>

<template>
    <Head title="Telegram-бот — Monsory Connect" />

    <AdminLayout title="Telegram-бот">
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

        <div v-if="!configured" v-glow class="glass-panel-gold glass-panel mb-8 p-6">
            <p class="text-white/80">
                Спочатку вкажіть <strong>Bot Token</strong> у
                <Link :href="route('admin.settings.index')" class="text-gold-300 underline hover:text-gold-200">Налаштуваннях → Telegram</Link>.
            </p>
        </div>

        <div v-else class="grid gap-6 sm:grid-cols-3">
            <div v-glow class="glass-panel p-6">
                <p class="text-xs uppercase tracking-widest text-white/40">Бот</p>
                <p class="font-display mt-2 text-xl text-white">@{{ botUsername || '—' }}</p>
            </div>
            <div v-glow class="glass-panel p-6">
                <p class="text-xs uppercase tracking-widest text-white/40">Webhook</p>
                <p class="font-display mt-2 text-xl" :class="hasActiveWebhook ? 'text-emerald-300' : 'text-white/40'">
                    {{ hasActiveWebhook ? 'Активний' : 'Не встановлено' }}
                </p>
            </div>
            <div v-glow class="glass-panel p-6">
                <p class="text-xs uppercase tracking-widest text-white/40">Привʼязано акаунтів</p>
                <p class="font-display mt-2 text-4xl text-gold-300">{{ linkedCount }}</p>
            </div>
        </div>

        <div v-if="configured" class="mt-8 flex flex-wrap items-center gap-4">
            <button
                :disabled="busy"
                class="rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-6 py-3 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03] disabled:opacity-40"
                @click="setupWebhook"
            >
                {{ hasActiveWebhook ? 'Перевстановити webhook' : 'Встановити webhook' }}
            </button>
            <button
                v-if="hasActiveWebhook"
                :disabled="busy"
                class="rounded-full border border-ember-500/25 px-5 py-2.5 text-xs font-medium text-ember-500/80 hover:bg-ember-600/10 disabled:opacity-40"
                @click="removeWebhook"
            >
                Зняти webhook
            </button>
        </div>

        <div v-if="configured && webhookInfo" class="mt-8 max-w-xl">
            <h2 class="font-display mb-3 text-lg text-white">Деталі webhook</h2>
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02] text-sm">
                <div class="flex justify-between border-b border-white/5 px-5 py-3">
                    <span class="text-white/40">URL</span>
                    <span class="max-w-[60%] truncate text-white/70">{{ webhookInfo.url || '—' }}</span>
                </div>
                <div class="flex justify-between border-b border-white/5 px-5 py-3">
                    <span class="text-white/40">Очікує повідомлень</span>
                    <span class="text-white/70">{{ webhookInfo.pending_update_count ?? 0 }}</span>
                </div>
                <div v-if="webhookInfo.last_error_message" class="flex justify-between px-5 py-3">
                    <span class="text-white/40">Остання помилка</span>
                    <span class="max-w-[60%] text-right text-ember-500">{{ webhookInfo.last_error_message }}</span>
                </div>
            </div>
        </div>
    </AdminLayout>
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
