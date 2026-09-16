<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    notifications: { type: Object, required: true },
    unreadCount: { type: Number, default: 0 },
});

const localUnread = ref(props.unreadCount);
const busy = ref(null);

async function markRead(notification) {
    if (notification.read_at) return;
    busy.value = notification.id;
    try {
        await window.axios.post(route('notifications.read', notification.id));
        notification.read_at = new Date().toISOString();
        localUnread.value = Math.max(0, localUnread.value - 1);
    } finally {
        busy.value = null;
    }
}

const markingAll = ref(false);
async function markAllRead() {
    markingAll.value = true;
    try {
        await window.axios.post(route('notifications.read-all'));
        props.notifications.data.forEach((n) => { if (!n.read_at) n.read_at = new Date().toISOString(); });
        localUnread.value = 0;
    } finally {
        markingAll.value = false;
    }
}

const typeLabels = {
    broadcast: 'Оголошення',
    report_reviewed: 'Звіт',
};

function fmtDateTime(iso) {
    return new Date(iso).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Сповіщення" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-6">
                <div>
                    <Link :href="route('dashboard')" class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300">
                        ← Кабінет
                    </Link>
                    <h1 class="font-display mt-2 text-3xl font-semibold text-white">
                        Сповіщення
                        <span v-if="localUnread > 0" class="text-gradient-gold italic">({{ localUnread }})</span>
                    </h1>
                </div>
                <button
                    v-if="localUnread > 0"
                    :disabled="markingAll"
                    class="rounded-full border border-white/15 px-4 py-2 text-xs text-white/60 transition-colors hover:border-white/30 disabled:opacity-40"
                    @click="markAllRead"
                >
                    Позначити всі прочитаними
                </button>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-10">
            <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
                <button
                    v-for="n in notifications.data"
                    :key="n.id"
                    class="block w-full border-b border-white/5 px-6 py-4 text-left last:border-0 transition-colors hover:bg-white/[0.03]"
                    :class="!n.read_at && 'bg-gold-400/[0.03]'"
                    @click="markRead(n)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span v-if="!n.read_at" class="h-1.5 w-1.5 shrink-0 rounded-full bg-gold-400"></span>
                            <span class="font-medium text-white">{{ n.title }}</span>
                        </div>
                        <span class="shrink-0 text-[10px] uppercase tracking-widest text-white/30">{{ typeLabels[n.type] || n.type }}</span>
                    </div>
                    <p v-if="n.body" class="mt-1 text-sm leading-relaxed text-white/50">{{ n.body }}</p>
                    <p class="mt-2 text-[11px] text-white/30">{{ fmtDateTime(n.created_at) }}</p>
                </button>
                <div v-if="notifications.data.length === 0" class="px-6 py-12 text-center text-white/30">
                    Сповіщень поки немає
                </div>
            </div>

            <div v-if="notifications.links?.length > 3" class="mt-6 flex flex-wrap gap-2">
                <Link
                    v-for="link in notifications.links"
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
