<script setup>
import { onMounted, ref } from 'vue';

const state = ref(null);
const loading = ref(true);
const busy = ref(false);

async function refresh() {
    const { data } = await window.axios.get(route('telegram.status'));
    state.value = data.data;
    loading.value = false;
}

async function generateCode() {
    busy.value = true;
    try {
        await window.axios.post(route('telegram.generate-code'));
        await refresh();
    } finally {
        busy.value = false;
    }
}

async function unlink() {
    if (!confirm("Відв'язати Telegram від акаунту?")) return;
    busy.value = true;
    try {
        await window.axios.post(route('telegram.unlink'));
        await refresh();
    } finally {
        busy.value = false;
    }
}

onMounted(refresh);
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-lg font-medium text-white">Telegram</h2>
            <p class="mt-1 text-sm text-white/40">
                Привʼяжіть Telegram, щоб отримувати особисті сповіщення прямо в чат.
            </p>
        </header>

        <div v-if="!loading" class="mt-6">
            <div v-if="state.linked" class="flex items-center justify-between rounded-lg border border-emerald-400/30 bg-emerald-400/10 px-4 py-3">
                <p class="text-sm text-emerald-200">
                    Привʼязано<span v-if="state.telegram_username"> · @{{ state.telegram_username }}</span>
                </p>
                <button :disabled="busy" class="text-xs uppercase tracking-widest text-white/40 hover:text-white disabled:opacity-40" @click="unlink">
                    Відвʼязати
                </button>
            </div>

            <div v-else>
                <div v-if="state.pending_code" class="rounded-lg border border-gold-400/30 bg-gold-400/10 px-4 py-4">
                    <p class="text-sm text-white/70">
                        Надішліть боту команду:
                        <code class="ml-1 rounded bg-obsidian-950 px-2 py-1 text-gold-200">/link {{ state.pending_code }}</code>
                    </p>
                    <a
                        v-if="state.bot_username"
                        :href="`https://t.me/${state.bot_username}?start=${state.pending_code}`"
                        target="_blank"
                        rel="noopener"
                        class="mt-3 inline-block rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-5 py-2 text-xs font-semibold uppercase tracking-widest text-obsidian-950"
                    >
                        Відкрити бота одним кліком →
                    </a>
                    <p class="mt-2 text-xs text-white/30">Код дійсний 10 хвилин.</p>
                </div>
                <button
                    v-else
                    :disabled="busy"
                    class="glass-pill px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-white/70 hover:text-white disabled:opacity-40"
                    @click="generateCode"
                >
                    Згенерувати код
                </button>
            </div>
        </div>
    </section>
</template>
