<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    addons: { type: Object, required: true },
    recentAudit: { type: Array, default: () => [] },
});

const TYPE_META = {
    core: { label: 'Core', hint: '(Core)(x.y.z)Name.zip', desc: 'Обновления ядра платформы' },
    module: { label: 'Modules', hint: '(Modules)(x.y)Name.zip', desc: 'Бизнес-логика: свой функционал целиком' },
    plugin: { label: 'Plugin', hint: '(Plugin)(x.y)Name.zip', desc: 'Опциональные надстройки над модулями' },
    theme: { label: 'Design', hint: '(Design)(x.y)Name.zip', desc: 'Только ассеты — CSS/JS/изображения, без PHP' },
};
const TYPES = ['core', 'module', 'plugin', 'theme'];

const activeTab = ref('module');

/* ---------- уведомления ---------- */
const toasts = ref([]);
let toastId = 0;
function pushToast(ok, message) {
    const id = ++toastId;
    toasts.value.push({ id, ok, message });
    setTimeout(() => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }, 5000);
}

/* ---------- загрузка архива ---------- */
const uploading = ref({}); // { [type]: progress 0..100 | null }
const dragOver = ref(null);

async function uploadFile(type, file) {
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.zip')) {
        pushToast(false, 'Ожидается ZIP-архив');
        return;
    }

    const form = new FormData();
    form.append('package', file);
    uploading.value = { ...uploading.value, [type]: 0 };

    try {
        const { data } = await window.axios.post(`/admin/addons/${type}/upload`, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (evt) => {
                if (evt.total) {
                    uploading.value = { ...uploading.value, [type]: Math.round((evt.loaded / evt.total) * 100) };
                }
            },
        });
        pushToast(data.ok, data.message);
        if (data.ok) reloadSilently();
    } catch (e) {
        const message = e.response?.data?.message || 'Ошибка загрузки пакета';
        pushToast(false, message);
    } finally {
        const next = { ...uploading.value };
        delete next[type];
        uploading.value = next;
    }
}

function onDrop(type, e) {
    dragOver.value = null;
    uploadFile(type, e.dataTransfer.files[0]);
}

function onPick(type, e) {
    uploadFile(type, e.target.files[0]);
    e.target.value = '';
}

/* ---------- действия над установленным пакетом ---------- */
const busyAddon = ref(null);

async function act(addon, action) {
    busyAddon.value = addon.id;
    try {
        const { data } = await window.axios.post(`/admin/addons/${addon.id}/${action}`);
        pushToast(data.ok, data.message);
        if (data.ok) reloadSilently();
    } catch (e) {
        pushToast(false, e.response?.data?.message || 'Ошибка операции');
    } finally {
        busyAddon.value = null;
    }
}

async function confirmDestroy(addon) {
    if (!confirm(`Удалить «${addon.name}» версии ${addon.version}? Это необратимо.`)) {
        return;
    }
    busyAddon.value = addon.id;
    try {
        const { data } = await window.axios.delete(`/admin/addons/${addon.id}`);
        pushToast(data.ok, data.message);
        if (data.ok) reloadSilently();
    } catch (e) {
        pushToast(false, e.response?.data?.message || 'Ошибка операции');
    } finally {
        busyAddon.value = null;
    }
}

function reloadSilently() {
    // Пока просто полная перезагрузка — переключить на Inertia router.reload({ only: ['addons'] })
    // при следующей итерации, чтобы не терять активную вкладку/скролл.
    window.location.reload();
}

const statusMeta = {
    active: { label: 'Активен', class: 'bg-emerald-400/15 text-emerald-300 border-emerald-400/30' },
    inactive: { label: 'Отключен', class: 'bg-white/5 text-white/40 border-white/10' },
    pending_migration: { label: 'Ждёт миграций', class: 'bg-gold-400/15 text-gold-300 border-gold-400/30' },
    failed: { label: 'Ошибка', class: 'bg-ember-500/15 text-ember-500 border-ember-500/30' },
};

function fmtDate(iso) {
    return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const totalInstalled = computed(() =>
    TYPES.reduce((sum, t) => sum + (props.addons[t]?.length || 0), 0),
);
</script>

<template>
    <Head title="Аддоны — Core / Modules / Plugins / Themes" />

    <div class="min-h-screen bg-obsidian-950 font-sans text-white/80">
        <!-- ================= TOASTS ================= -->
        <div class="pointer-events-none fixed right-6 top-6 z-[100] flex w-full max-w-sm flex-col gap-3">
            <TransitionGroup name="toast">
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-2xl backdrop-blur-md"
                    :class="t.ok
                        ? 'border-emerald-400/30 bg-emerald-950/80 text-emerald-200'
                        : 'border-ember-500/30 bg-ember-600/20 text-ember-500'"
                >
                    {{ t.message }}
                </div>
            </TransitionGroup>
        </div>

        <!-- ================= HEADER ================= -->
        <header class="border-b border-white/5 bg-obsidian-900/60 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-6">
                <div>
                    <Link :href="route('dashboard')" class="text-xs uppercase tracking-widest text-white/40 hover:text-gold-300">
                        ← Кабинет
                    </Link>
                    <h1 class="font-display mt-2 text-3xl font-semibold text-white">
                        Управление <span class="text-gradient-gold italic">аддонами</span>
                    </h1>
                    <p class="mt-1 text-sm text-white/40">
                        Core Updater · Modules · Plugins · Themes — {{ totalInstalled }} пакетов установлено
                    </p>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-7xl px-6 py-10">
            <!-- ================= TABS ================= -->
            <div class="mb-8 flex flex-wrap gap-2">
                <button
                    v-for="type in TYPES"
                    :key="type"
                    class="relative rounded-full border px-5 py-2.5 text-sm font-medium tracking-wide transition-all duration-300"
                    :class="activeTab === type
                        ? 'border-gold-400/50 bg-gold-400/10 text-gold-200 shadow-gold'
                        : 'border-white/10 text-white/50 hover:border-white/25 hover:text-white'"
                    @click="activeTab = type"
                >
                    {{ TYPE_META[type].label }}
                    <span
                        class="ml-2 rounded-full bg-white/10 px-1.5 py-0.5 text-[10px] text-white/50"
                    >{{ addons[type]?.length || 0 }}</span>
                </button>
            </div>

            <Transition name="fade-tab" mode="out-in">
                <div :key="activeTab" class="space-y-8">
                    <!-- ================= UPLOAD ZONE ================= -->
                    <div
                        class="group relative overflow-hidden rounded-2xl border-2 border-dashed p-10 text-center transition-all duration-300"
                        :class="dragOver === activeTab
                            ? 'border-gold-400/60 bg-gold-400/5'
                            : 'border-white/10 hover:border-white/20'"
                        @dragover.prevent="dragOver = activeTab"
                        @dragleave.prevent="dragOver = null"
                        @drop.prevent="onDrop(activeTab, $event)"
                    >
                        <div v-if="uploading[activeTab] !== undefined" class="mx-auto max-w-sm">
                            <p class="mb-3 text-sm text-white/60">Загрузка пакета…</p>
                            <div class="h-2 overflow-hidden rounded-full bg-white/10">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 transition-all duration-200"
                                    :style="{ width: uploading[activeTab] + '%' }"
                                ></div>
                            </div>
                            <p class="mt-2 text-xs text-white/40">{{ uploading[activeTab] }}%</p>
                        </div>
                        <template v-else>
                            <p class="font-display text-2xl text-white">
                                Загрузить {{ TYPE_META[activeTab].label }}-пакет
                            </p>
                            <p class="mt-2 text-sm text-white/40">
                                {{ TYPE_META[activeTab].desc }} · формат имени: <code class="text-gold-300/80">{{ TYPE_META[activeTab].hint }}</code>
                            </p>
                            <label
                                class="mt-6 inline-block cursor-pointer rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-8 py-3 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03]"
                            >
                                Выбрать ZIP
                                <input type="file" accept=".zip" class="hidden" @change="onPick(activeTab, $event)" />
                            </label>
                            <p class="mt-3 text-xs text-white/30">или перетащите файл сюда</p>
                        </template>
                    </div>

                    <!-- ================= LIST ================= -->
                    <div v-if="(addons[activeTab] || []).length === 0" class="rounded-2xl border border-white/5 bg-white/[0.02] p-12 text-center text-white/30">
                        Пакетов этого типа пока не загружено
                    </div>

                    <div v-else class="grid gap-4 sm:grid-cols-2">
                        <div
                            v-for="addon in addons[activeTab]"
                            :key="addon.id"
                            class="group rounded-2xl border border-white/10 bg-white/[0.03] p-6 transition-all duration-300 hover:border-gold-400/20 hover:bg-white/[0.05]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-white">{{ addon.name }}</h3>
                                    <p class="mt-0.5 text-xs text-white/40">{{ addon.slug }} · v{{ addon.version }}</p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-medium uppercase tracking-wide"
                                    :class="statusMeta[addon.status]?.class"
                                >
                                    {{ statusMeta[addon.status]?.label }}
                                </span>
                            </div>

                            <p v-if="addon.manifest?.description" class="mt-3 text-sm leading-relaxed text-white/50">
                                {{ addon.manifest.description }}
                            </p>

                            <p class="mt-4 text-[11px] text-white/30">Загружен {{ fmtDate(addon.created_at) }}</p>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <button
                                    v-if="!addon.status || addon.status === 'inactive' || addon.status === 'failed'"
                                    :disabled="busyAddon === addon.id"
                                    class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-4 py-1.5 text-xs font-medium text-emerald-300 transition-colors hover:bg-emerald-400/20 disabled:opacity-40"
                                    @click="act(addon, 'activate')"
                                >
                                    Активировать
                                </button>
                                <button
                                    v-if="addon.status === 'active'"
                                    :disabled="busyAddon === addon.id"
                                    class="rounded-full border border-white/15 px-4 py-1.5 text-xs font-medium text-white/60 transition-colors hover:bg-white/5 disabled:opacity-40"
                                    @click="act(addon, 'deactivate')"
                                >
                                    Деактивировать
                                </button>
                                <button
                                    v-if="addon.manifest?.migrations && !addon.migrations_applied"
                                    :disabled="busyAddon === addon.id"
                                    class="rounded-full border border-gold-400/30 bg-gold-400/10 px-4 py-1.5 text-xs font-medium text-gold-300 transition-colors hover:bg-gold-400/20 disabled:opacity-40"
                                    @click="act(addon, 'migrate')"
                                >
                                    Применить в БД
                                </button>
                                <button
                                    v-if="addon.status !== 'active'"
                                    :disabled="busyAddon === addon.id"
                                    class="rounded-full border border-ember-500/25 px-4 py-1.5 text-xs font-medium text-ember-500/80 transition-colors hover:bg-ember-600/10 disabled:opacity-40"
                                    @click="confirmDestroy(addon)"
                                >
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ================= AUDIT LOG ================= -->
            <div class="mt-16">
                <h2 class="font-display mb-4 text-xl text-white">Журнал действий</h2>
                <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
                    <div
                        v-for="log in recentAudit"
                        :key="log.id"
                        class="flex items-center justify-between border-b border-white/5 px-5 py-3 text-sm last:border-0"
                    >
                        <span class="text-white/60">
                            <span class="text-gold-300/80">{{ log.user?.name || 'система' }}</span>
                            — {{ log.action }}
                            <span v-if="log.addon" class="text-white/40">({{ log.addon.name }})</span>
                        </span>
                        <span class="text-xs text-white/30">{{ fmtDate(log.created_at) }}</span>
                    </div>
                    <div v-if="recentAudit.length === 0" class="px-5 py-6 text-center text-sm text-white/30">
                        Пока пусто
                    </div>
                </div>
            </div>
        </div>
    </div>
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
.fade-tab-enter-active,
.fade-tab-leave-active {
    transition: all 0.3s ease;
}
.fade-tab-enter-from {
    opacity: 0;
    transform: translateY(10px);
}
.fade-tab-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
