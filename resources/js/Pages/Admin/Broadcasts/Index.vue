<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    broadcasts: { type: Object, required: true },
});

const showForm = ref(false);
const form = useForm({
    title: '',
    body: '',
    pinned: false,
});

function submit() {
    form.post(route('admin.broadcasts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
}

function fmtDateTime(iso) {
    return new Date(iso).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="Розсилки — Monsory Connect" />

    <AdminLayout title="Розсилки">
        <div class="mb-6">
            <button
                class="rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-6 py-3 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03]"
                @click="showForm = !showForm"
            >
                {{ showForm ? 'Скасувати' : 'Нова розсилка' }}
            </button>
        </div>

        <Transition name="fade-slide">
            <form v-if="showForm" class="mb-10 rounded-2xl border border-white/10 bg-white/[0.03] p-6" @submit.prevent="submit">
                <div>
                    <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Заголовок</label>
                    <input v-model="form.title" type="text" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white" />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-ember-500">{{ form.errors.title }}</p>
                </div>
                <div class="mt-5">
                    <label class="mb-2 block text-xs uppercase tracking-widest text-white/40">Текст</label>
                    <textarea v-model="form.body" rows="4" class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white"></textarea>
                    <p v-if="form.errors.body" class="mt-1 text-xs text-ember-500">{{ form.errors.body }}</p>
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm text-white/60">
                    <input v-model="form.pinned" type="checkbox" class="rounded border-white/20 bg-obsidian-900 text-gold-400 focus:ring-gold-400/40" />
                    Закріпити зверху
                </label>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-5 rounded-full border border-gold-400/40 px-6 py-2.5 text-sm font-medium tracking-widest text-gold-200 transition-all hover:border-gold-300 disabled:opacity-40"
                >
                    Надіслати всім учасникам
                </button>
            </form>
        </Transition>

        <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
            <div
                v-for="b in broadcasts.data"
                :key="b.id"
                class="border-b border-white/5 px-6 py-4 last:border-0"
            >
                <div class="flex items-start justify-between gap-3">
                    <h3 class="font-medium text-white">
                        <span v-if="b.pinned" class="mr-2 text-gold-300">📌</span>{{ b.title }}
                    </h3>
                    <span class="shrink-0 text-[11px] text-white/30">{{ fmtDateTime(b.created_at) }}</span>
                </div>
                <p class="mt-1 text-sm leading-relaxed text-white/50">{{ b.body }}</p>
                <p class="mt-2 text-[11px] text-white/30">від {{ b.creator?.name }}</p>
            </div>
            <div v-if="broadcasts.data.length === 0" class="px-6 py-12 text-center text-white/30">
                Розсилок ще не було
            </div>
        </div>

        <div v-if="broadcasts.links?.length > 3" class="mt-6 flex flex-wrap gap-2">
            <Link
                v-for="link in broadcasts.links"
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
