<script setup>
import { onMounted, ref } from 'vue';

defineEmits(['select']);

const stickers = ref([]);
const loading = ref(true);
const uploading = ref(false);
const fileInput = ref(null);

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('messenger.stickers.index'));
        stickers.value = data.data.stickers;
    } finally {
        loading.value = false;
    }
}

function pickFile() {
    fileInput.value?.click();
}

async function onFileChosen(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;

    uploading.value = true;
    try {
        const form = new FormData();
        form.append('image', file);
        const { data } = await window.axios.post(route('messenger.stickers.store'), form);
        stickers.value.unshift(data.data.sticker);
    } finally {
        uploading.value = false;
    }
}

async function remove(sticker) {
    stickers.value = stickers.value.filter((s) => s.id !== sticker.id);
    await window.axios.delete(route('messenger.stickers.destroy', sticker.id));
}

onMounted(load);
</script>

<template>
    <div class="glass-panel absolute bottom-full left-0 mb-2 flex h-72 w-72 flex-col rounded-2xl p-3">
        <div class="mb-2 flex shrink-0 items-center justify-between">
            <p class="text-[11px] font-medium uppercase tracking-wide text-white/30">Мої стікери</p>
            <button
                type="button"
                class="text-xs font-medium text-gold-300 transition hover:text-gold-200 disabled:opacity-40"
                :disabled="uploading"
                @click="pickFile"
            >
                {{ uploading ? 'Завантаження…' : '+ Додати' }}
            </button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFileChosen" />
        </div>
        <div class="grid flex-1 grid-cols-4 gap-2 overflow-y-auto">
            <div v-for="s in stickers" :key="s.id" class="group relative">
                <button
                    type="button"
                    class="flex aspect-square w-full items-center justify-center rounded-lg bg-white/5 transition hover:ring-2 hover:ring-gold-400"
                    @click="$emit('select', s.id)"
                >
                    <img :src="s.url" :alt="''" class="h-full w-full object-contain p-1" loading="lazy" />
                </button>
                <button
                    type="button"
                    class="absolute -right-1 -top-1 hidden h-4 w-4 items-center justify-center rounded-full bg-obsidian-950 text-[10px] text-white/60 ring-1 ring-white/20 group-hover:flex hover:text-white"
                    title="Видалити стікер"
                    @click="remove(s)"
                >
                    ×
                </button>
            </div>
        </div>
        <p v-if="!loading && !stickers.length" class="py-6 text-center text-xs text-white/30">
            Ще немає стікерів — додайте перший.
        </p>
    </div>
</template>
