<script setup>
import { onMounted, ref } from 'vue';

const state = ref(null);
const loading = ref(true);
const busy = ref(false);
const error = ref(null);

async function refresh() {
    const { data } = await window.axios.get(route('telegram.status'));
    state.value = data.data;
    loading.value = false;
}

/**
 * Одно нажатие вместо трёх шагов. Раньше тут выдавался код и предлагалось
 * отправить боту команду `/link КОД` — это ровно то, от чего мы ушли,
 * сделав бота кнопочным. Теперь код выписывается молча и сразу
 * подставляется в deep-link: человек просто попадает в чат, где привязка
 * уже произошла.
 */
async function link() {
    busy.value = true;
    error.value = null;

    // Вкладку треба відкрити СИНХРОННО, першим рядком обробника кліку —
    // будь-який await перед window.open() (навіть швидкий запит коду)
    // руйнує "довіру" браузера до жесту користувача, і мобільні браузери
    // (особливо iOS Safari) тихо блокують спливаюче вікно. Тому спершу
    // відкриваємо порожню вкладку, а вже потім підставляємо їй адресу.
    const popup = window.open('', '_blank', 'noopener');

    try {
        const { data } = await window.axios.post(route('telegram.generate-code'));
        const code = data.data?.code;
        const bot = data.data?.bot_username;

        if (!code || !bot) {
            error.value = 'Бот ще не налаштований. Зверніться до адміністратора.';
            popup?.close();
            return;
        }

        const url = `https://t.me/${bot.replace(/^@/, '')}?start=${code}`;
        if (popup) {
            popup.location.href = url;
        } else {
            window.location.href = url;
        }
        await refresh();
    } catch (e) {
        error.value = e.response?.data?.message || 'Не вдалося отримати посилання.';
        popup?.close();
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
            <div
                v-if="state.linked"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3"
            >
                <p class="text-sm text-emerald-200">
                    Привʼязано<span v-if="state.telegram_username"> · @{{ state.telegram_username }}</span>
                </p>
                <button
                    :disabled="busy"
                    class="text-xs uppercase tracking-widest text-white/40 transition-colors hover:text-white disabled:opacity-40"
                    @click="unlink"
                >
                    Відвʼязати
                </button>
            </div>

            <div v-else>
                <button
                    :disabled="busy"
                    class="rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-6 py-3 text-xs font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03] disabled:opacity-40"
                    @click="link"
                >
                    {{ busy ? 'Відкриваємо…' : 'Привʼязати Telegram' }}
                </button>
                <p class="mt-3 text-xs text-white/30">
                    Відкриється чат з ботом — нічого вводити не потрібно.
                </p>
                <p v-if="error" class="mt-2 text-xs text-ember-500">{{ error }}</p>
            </div>
        </div>
    </section>
</template>
