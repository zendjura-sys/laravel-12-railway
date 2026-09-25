<script setup>
import { onMounted, ref, watch } from 'vue';

defineEmits(['select']);

const query = ref('');
const gifs = ref([]);
const loading = ref(false);
let timer = null;

async function load(q) {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('messenger.gifs.search'), { params: { q } });
        gifs.value = data.data.gifs;
    } finally {
        loading.value = false;
    }
}

watch(query, (q) => {
    clearTimeout(timer);
    timer = setTimeout(() => load(q), 300);
});

onMounted(() => load(''));
</script>

<template>
    <div class="glass-panel absolute bottom-full left-0 mb-2 flex h-80 w-80 flex-col overflow-hidden rounded-2xl p-3">
        <input
            v-model="query"
            type="text"
            placeholder="Пошук gif…"
            class="mb-2 w-full shrink-0 rounded-lg border-white/10 bg-obsidian-900/60 text-sm text-white placeholder:text-white/30 focus:border-gold-400 focus:ring-gold-400"
        />
        <div class="grid flex-1 grid-cols-3 gap-1.5 overflow-y-auto">
            <button
                v-for="g in gifs"
                :key="g.id"
                type="button"
                class="overflow-hidden rounded-lg bg-white/5 transition hover:ring-2 hover:ring-gold-400"
                @click="$emit('select', g.url)"
            >
                <img :src="g.previewUrl" :alt="''" class="h-full w-full object-cover" loading="lazy" />
            </button>
        </div>
        <p v-if="!loading && !gifs.length" class="py-6 text-center text-xs text-white/30">Нічого не знайдено.</p>
    </div>
</template>
