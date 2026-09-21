<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EmojiPicker from '@/Components/Messenger/EmojiPicker.vue';
import GifPicker from '@/Components/Messenger/GifPicker.vue';
import StickerPicker from '@/Components/Messenger/StickerPicker.vue';

const props = defineProps({
    conversation: { type: Object, required: true },
    messages: { type: Array, required: true },
    myId: { type: Number, required: true },
    giphyEnabled: { type: Boolean, default: false },
});

const list = ref([...props.messages]);
const draft = ref('');
const sending = ref(false);
const scroller = ref(null);
const activePicker = ref(null);
const photoInput = ref(null);
const photoFile = ref(null);
const photoPreview = ref(null);
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

function togglePicker(name) {
    activePicker.value = activePicker.value === name ? null : name;
}

function pickPhoto() {
    activePicker.value = null;
    photoInput.value?.click();
}

function onPhotoChosen(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;

    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
    photoFile.value = file;
    photoPreview.value = URL.createObjectURL(file);
}

function cancelPhoto() {
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
    photoFile.value = null;
    photoPreview.value = null;
}

function insertEmoji(emoji) {
    draft.value += emoji;
    activePicker.value = null;
}

async function sendGif(url) {
    activePicker.value = null;
    await sendPayload({ type: 'gif', gif_url: url });
}

async function sendSticker(stickerId) {
    activePicker.value = null;
    await sendPayload({ type: 'sticker', sticker_id: stickerId });
}

async function sendPayload(data) {
    if (sending.value) return;
    sending.value = true;
    try {
        const { data: res } = await window.axios.post(route('messenger.messages.store', props.conversation.id), data);
        list.value.push(res.data.message);
        scrollToBottom();
    } finally {
        sending.value = false;
    }
}

async function send() {
    const body = draft.value.trim();
    if (sending.value) return;

    if (photoFile.value) {
        sending.value = true;
        try {
            const form = new FormData();
            form.append('type', 'photo');
            form.append('photo', photoFile.value);
            if (body) form.append('body', body);
            const { data } = await window.axios.post(route('messenger.messages.store', props.conversation.id), form);
            list.value.push(data.data.message);
            draft.value = '';
            cancelPhoto();
            scrollToBottom();
        } finally {
            sending.value = false;
        }
        return;
    }

    if (!body) return;
    await sendPayload({ type: 'text', body });
    draft.value = '';
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
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
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
                            <p v-if="!m.isMine && conversation.type === 'family'" class="mb-0.5 flex items-center gap-1.5 px-1 text-[11px] font-medium text-gold-300">
                                {{ m.senderName }}
                                <span
                                    v-if="m.senderPosition"
                                    class="rounded-full border border-gold-400/30 bg-gold-400/10 px-1.5 py-0.5 text-[9px] font-normal uppercase tracking-wide text-gold-300/80"
                                >{{ m.senderPosition }}</span>
                            </p>

                            <!-- gif/стікер: без бульбашки, як у звичайних месенджерах -->
                            <div v-if="m.type === 'gif' || m.type === 'sticker'" class="inline-block">
                                <img
                                    :src="m.attachmentUrl"
                                    :alt="m.type === 'gif' ? 'GIF' : 'Стікер'"
                                    class="max-h-48 rounded-lg"
                                    :class="m.type === 'sticker' ? 'max-w-32' : 'max-w-56'"
                                />
                            </div>

                            <!-- фото: бульбашка з картинкою й необов'язковим підписом -->
                            <div
                                v-else-if="m.type === 'photo'"
                                class="inline-block overflow-hidden rounded-2xl"
                                :class="m.isMine ? 'bg-gold-400/90' : 'bg-white/5 ring-1 ring-white/10'"
                            >
                                <img :src="m.attachmentUrl" alt="" class="max-h-72 w-full object-cover" />
                                <p
                                    v-if="m.body"
                                    class="whitespace-pre-wrap break-words px-4 py-2 text-sm"
                                    :class="m.isMine ? 'text-obsidian-950' : 'text-white/85'"
                                >
                                    {{ m.body }}
                                </p>
                            </div>

                            <!-- звичайний текст -->
                            <div
                                v-else
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

                <div v-if="photoPreview" class="relative mt-3 inline-flex w-fit items-start gap-2 rounded-xl bg-white/5 p-2 ring-1 ring-white/10">
                    <img :src="photoPreview" alt="" class="h-16 w-16 rounded-lg object-cover" />
                    <button type="button" class="text-xs text-white/40 hover:text-white/70" @click="cancelPhoto">Скасувати</button>
                </div>

                <div class="relative mt-2 flex items-center gap-1">
                    <EmojiPicker v-if="activePicker === 'emoji'" @select="insertEmoji" />
                    <GifPicker v-if="activePicker === 'gif'" @select="sendGif" />
                    <StickerPicker v-if="activePicker === 'sticker'" @select="sendSticker" />

                    <button type="button" title="Фото" class="rounded-lg p-2 text-lg transition hover:bg-white/5" @click="pickPhoto">📎</button>
                    <button type="button" title="Емодзі" class="rounded-lg p-2 text-lg transition hover:bg-white/5" @click="togglePicker('emoji')">😊</button>
                    <button
                        v-if="giphyEnabled"
                        type="button"
                        title="GIF"
                        class="rounded-lg px-2 py-1.5 text-xs font-semibold text-white/60 transition hover:bg-white/5 hover:text-white/90"
                        @click="togglePicker('gif')"
                    >
                        GIF
                    </button>
                    <button type="button" title="Стікери" class="rounded-lg p-2 text-lg transition hover:bg-white/5" @click="togglePicker('sticker')">🏷️</button>
                    <input ref="photoInput" type="file" accept="image/*" class="hidden" @change="onPhotoChosen" />
                </div>

                <form class="mt-2 flex items-end gap-3" @submit.prevent="send">
                    <textarea
                        v-model="draft"
                        rows="1"
                        :placeholder="photoFile ? 'Підпис до фото (необов\'язково)…' : 'Повідомлення…'"
                        class="flex-1 resize-none rounded-xl border-white/10 bg-obsidian-900/60 text-sm text-white placeholder:text-white/30 focus:border-gold-400 focus:ring-gold-400"
                        @keydown.enter.exact.prevent="send"
                    />
                    <button
                        type="submit"
                        :disabled="sending || (!draft.trim() && !photoFile)"
                        class="rounded-xl bg-gold-400 px-5 py-2.5 text-sm font-semibold text-obsidian-950 transition hover:bg-gold-300 disabled:opacity-40"
                    >
                        Надіслати
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
