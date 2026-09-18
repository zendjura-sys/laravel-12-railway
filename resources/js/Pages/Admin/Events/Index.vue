<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    events: { type: Array, required: true },
});

const showForm = ref(false);
const createForm = useForm({
    title: '',
    description: '',
    location: '',
    starts_at: '',
});

function toggleForm() {
    // Скидаємо поля щоразу при відкритті — інакше повторне відкриття після
    // "Скасувати" (без сабміту) тягнуло значення з попередньої спроби,
    // включно з датою/часом, які легко неправильно прочитати як "порожні".
    if (! showForm.value) {
        createForm.reset();
    }
    showForm.value = ! showForm.value;
}

/**
 * Мобільний datetime-local picker (особливо Android) легко "зʼїдає" частину
 * введеного — торкнулись не того колеса, і в полі лишається шматок від
 * поточної дати замість введеної. Без прев'ю це видно лише постфактум,
 * коли подія вже створена й розіслана всім.
 */
const startsAtPreview = computed(() => {
    if (! createForm.starts_at) return null;
    const d = new Date(createForm.starts_at);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleString('uk-UA', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
});

function createEvent() {
    createForm.post(route('admin.family-events.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showForm.value = false;
        },
    });
}

function destroyEvent(event) {
    if (! confirm(`Видалити подію «${event.title}»?`)) return;
    router.delete(route('admin.family-events.destroy', event.id), { preserveScroll: true });
}

function fmtDateTime(iso) {
    return new Date(iso).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function isPast(iso) {
    return new Date(iso).getTime() < Date.now();
}
</script>

<template>
    <Head title="Події родини — Monsory Connect" />

    <AdminLayout title="Події родини">
        <div class="mb-6">
            <button
                class="rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-6 py-3 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03]"
                @click="toggleForm"
            >
                {{ showForm ? 'Скасувати' : 'Нова подія' }}
            </button>
        </div>

        <Transition name="fade-slide">
            <form v-if="showForm" class="mb-10 rounded-2xl border border-white/10 bg-white/[0.03] p-6" @submit.prevent="createEvent">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Назва</label>
                        <input v-model="createForm.title" type="text" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                        <p v-if="createForm.errors.title" class="mt-1 text-xs text-ember-500">{{ createForm.errors.title }}</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Дата й час</label>
                        <input v-model="createForm.starts_at" type="datetime-local" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                        <p v-if="startsAtPreview" class="mt-1 text-xs text-gold-300/70">Буде збережено: {{ startsAtPreview }}</p>
                        <p v-if="createForm.errors.starts_at" class="mt-1 text-xs text-ember-500">{{ createForm.errors.starts_at }}</p>
                    </div>
                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Локація (необов'язково)</label>
                        <input v-model="createForm.location" type="text" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Опис (необов'язково)</label>
                        <textarea v-model="createForm.description" rows="3" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white"></textarea>
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-white/30">
                    Усі учасники одразу отримають сповіщення (веб + Telegram) і повідомлення в сімейний груповий чат. Нагадування прийде автоматично за день до події.
                </p>
                <button
                    type="submit"
                    :disabled="createForm.processing"
                    class="mt-5 rounded-full border border-gold-400/40 px-6 py-2.5 text-sm font-medium tracking-widest text-gold-200 transition-all hover:border-gold-300 disabled:opacity-40"
                >
                    Створити й оповістити
                </button>
            </form>
        </Transition>

        <div class="space-y-5">
            <div v-for="event in events" :key="event.id" class="glass-panel p-6" :class="isPast(event.starts_at) && 'opacity-50'">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-display text-xl text-white">{{ event.title }}</h3>
                        <p class="text-xs text-white/40">
                            {{ fmtDateTime(event.starts_at) }}
                            <span v-if="event.location">· {{ event.location }}</span>
                            · створив {{ event.creator?.name }}
                            <span v-if="event.reminder_sent_at" class="text-gold-300/60">· нагадування надіслано</span>
                        </p>
                    </div>
                    <button
                        class="shrink-0 rounded-full border border-ember-500/25 px-4 py-1.5 text-xs font-medium text-ember-500/80 hover:bg-ember-600/10"
                        @click="destroyEvent(event)"
                    >
                        Видалити
                    </button>
                </div>
                <p v-if="event.description" class="mt-2 text-sm leading-relaxed text-white/50">{{ event.description }}</p>
            </div>

            <div v-if="events.length === 0" class="rounded-2xl border border-white/5 bg-white/[0.02] p-12 text-center text-white/30">
                Подій ще немає — створіть першу
            </div>
        </div>
    </AdminLayout>
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
