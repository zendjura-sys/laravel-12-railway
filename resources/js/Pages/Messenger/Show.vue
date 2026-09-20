<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    conversation: { type: Object, required: true },
    messages: { type: Array, required: true },
    myId: { type: Number, required: true },
});

const list = ref([...props.messages]);
const draft = ref('');
const sending = ref(false);
const scroller = ref(null);
let pollTimer = null;

function lastId() {
    return list.value.length ? list.value[list.value.length - 1].id : 0;
}

async function scrollToBottom() {
    await nextTick();
    if (scroller.value) {
        scroller.value.scrollTop = scroller.value.scrollHeight;
    }
}

async function poll() {
    try {
        const { data } = await window.axios.get(route('messenger.messages', props.conversation.id), {
            params: { after_id: lastId() },
        });
        const fresh = data.data.messages;
        if (fresh.length) {
            list.value.push(...fresh);
            scrollToBottom();
            window.axios.post(route('messenger.read', props.conversation.id));
        }
    } catch {
        // Тиха невдача опитування — спробуємо ще раз наступним тіком,
        // не варто показувати помилку через кожен пропущений запит.
    }
}

async function send() {
    const body = draft.value.trim();
    if (!body || sending.value) return;

    sending.value = true;
    try {
        const { data } = await window.axios.post(route('messenger.messages.store', props.conversation.id), { body });
        list.value.push(data.data.message);
        draft.value = '';
        scrollToBottom();
    } finally {
        sending.value = false;
    }
}

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('uk-UA', { hour: '2-digit', minute: '2-digit' });
}

onMounted(() => {
    scrollToBottom();
    pollTimer = setInterval(poll, 3000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
});
</script>

<template>
    <Head :title="conversation.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('messenger.index')" class="text-white/40 transition hover:text-white/70">←</Link>
                <h2 class="text-xl font-light tracking-wide text-white">{{ conversation.title }}</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto flex max-w-3xl flex-col px-4 sm:px-6 lg:px-8" style="height: calc(100svh - 220px)">
                <div ref="scroller" class="glass-panel flex-1 space-y-3 overflow-y-auto rounded-2xl p-4">
                    <div
                        v-for="m in list"
                        :key="m.id"
                        class="flex"
                        :class="m.isMine ? 'justify-end' : 'justify-start'"
                    >
                        <div class="max-w-[75%]" :class="m.isMine ? 'text-right' : 'text-left'">
                            <p v-if="!m.isMine && conversation.type === 'family'" class="mb-0.5 px-1 text-[11px] font-medium text-gold-300">
                                {{ m.senderName }}
                            </p>
                            <div
                                class="inline-block rounded-2xl px-4 py-2 text-sm"
                                :class="m.isMine
                                    ? 'bg-gold-400/90 text-obsidian-950'
                                    : 'bg-white/5 text-white/85 ring-1 ring-white/10'"
                            >
                                <p class="whitespace-pre-wrap break-words">{{ m.body }}</p>
                            </div>
                            <p class="mt-0.5 px-1 text-[10px] text-white/25">{{ formatTime(m.createdAt) }}</p>
                        </div>
                    </div>
                    <p v-if="!list.length" class="py-10 text-center text-sm text-white/30">
                        Повідомлень ще немає — напишіть першим.
                    </p>
                </div>

                <form class="mt-4 flex items-end gap-3" @submit.prevent="send">
                    <textarea
                        v-model="draft"
                        rows="1"
                        placeholder="Повідомлення…"
                        class="flex-1 resize-none rounded-xl border-white/10 bg-obsidian-900/60 text-sm text-white placeholder:text-white/30 focus:border-gold-400 focus:ring-gold-400"
                        @keydown.enter.exact.prevent="send"
                    />
                    <button
                        type="submit"
                        :disabled="sending || !draft.trim()"
                        class="rounded-xl bg-gold-400 px-5 py-2.5 text-sm font-semibold text-obsidian-950 transition hover:bg-gold-300 disabled:opacity-40"
                    >
                        Надіслати
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
