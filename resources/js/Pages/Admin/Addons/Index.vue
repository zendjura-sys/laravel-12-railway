<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    addons: { type: Object, required: true },
    recentAudit: { type: Array, default: () => [] },
});

/* ---------- деплой одним кліком: кнопка лише лишає мітку на сервері,
   сам деплой виконує systemd-таймер від root, не сайт ---------- */
const deployStatus = ref({ status: 'idle' });
const deployLog = ref('');
const deployTriggering = ref(false);
const deployLogEl = ref(null);
let deployPoll = null;

async function fetchDeployStatus() {
    const { data } = await window.axios.get('/admin/deploy/status');
    deployStatus.value = data.data.status;
    deployLog.value = data.data.log;
    nextTick(() => {
        if (deployLogEl.value) deployLogEl.value.scrollTop = deployLogEl.value.scrollHeight;
    });
    if (['pending', 'running'].includes(deployStatus.value.status)) {
        if (!deployPoll) deployPoll = setInterval(fetchDeployStatus, 2000);
    } else if (deployPoll) {
        clearInterval(deployPoll);
        deployPoll = null;
    }
}

async function triggerDeploy() {
    deployTriggering.value = true;
    try {
        const { data } = await window.axios.post('/admin/deploy/trigger');
        pushToast(data.ok, data.message);
        if (data.ok) fetchDeployStatus();
    } catch (e) {
        pushToast(false, e.response?.data?.message || 'Помилка запуску деплою');
    } finally {
        deployTriggering.value = false;
    }
}

onMounted(fetchDeployStatus);
onUnmounted(() => { if (deployPoll) clearInterval(deployPoll); });

const TYPE_META = {
    core: { label: 'Core', hint: '(Core)(x.y.z)Name.zip', desc: 'Оновлення ядра платформи' },
    module: { label: 'Modules', hint: '(Modules)(x.y)Name.zip', desc: 'Бізнес-логіка: власний функціонал цілком' },
    plugin: { label: 'Plugin', hint: '(Plugin)(x.y)Name.zip', desc: 'Опціональні надбудови над модулями' },
    theme: { label: 'Design', hint: '(Design)(x.y)Name.zip', desc: 'Лише ассети — CSS/JS/зображення, без PHP' },
};
const TYPES = ['core', 'module', 'plugin', 'theme'];

const activeTab = ref('module');

/* ---------- сповіщення ---------- */
const toasts = ref([]);
let toastId = 0;
function pushToast(ok, message) {
    const id = ++toastId;
    toasts.value.push({ id, ok, message });
    setTimeout(() => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }, 5000);
}

/* ---------- завантаження архіву ---------- */
const uploading = ref({}); // { [type]: progress 0..100 | null }
const dragOver = ref(null);

async function uploadFile(type, file) {
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.zip')) {
        pushToast(false, 'Очікується ZIP-архів');
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
        const message = e.response?.data?.message || 'Помилка завантаження пакета';
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

/* ---------- дії над встановленим пакетом ---------- */
const busyAddon = ref(null);

async function act(addon, action) {
    busyAddon.value = addon.id;
    try {
        const { data } = await window.axios.post(`/admin/addons/${addon.id}/${action}`);
        pushToast(data.ok, data.message);
        if (data.ok) reloadSilently();
    } catch (e) {
        pushToast(false, e.response?.data?.message || 'Помилка операції');
    } finally {
        busyAddon.value = null;
    }
}

async function confirmDestroy(addon) {
    if (!confirm(`Видалити «${addon.name}» версії ${addon.version}? Це незворотно.`)) {
        return;
    }
    busyAddon.value = addon.id;
    try {
        const { data } = await window.axios.delete(`/admin/addons/${addon.id}`);
        pushToast(data.ok, data.message);
        if (data.ok) reloadSilently();
    } catch (e) {
        pushToast(false, e.response?.data?.message || 'Помилка операції');
    } finally {
        busyAddon.value = null;
    }
}

function reloadSilently() {
    // Пока просто повне перезавантаження — переключити на Inertia
    // router.reload({ only: ['addons'] }) наступною ітерацією, щоб не
    // втрачати активну вкладку/скрол.
    window.location.reload();
}

const statusMeta = {
    active: { label: 'Активний', class: 'bg-emerald-400/15 text-emerald-300 border-emerald-400/30' },
    inactive: { label: 'Вимкнений', class: 'bg-white/5 text-white/40 border-white/10' },
    pending_migration: { label: 'Очікує міграцій', class: 'bg-gold-400/15 text-gold-300 border-gold-400/30' },
    failed: { label: 'Помилка', class: 'bg-ember-500/15 text-ember-500 border-ember-500/30' },
};

function fmtDate(iso) {
    return new Date(iso).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const totalInstalled = computed(() =>
    TYPES.reduce((sum, t) => sum + (props.addons[t]?.length || 0), 0),
);
</script>

<template>
    <Head title="Аддони — Core / Modules / Plugins / Themes" />

    <AdminLayout>
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

        <div class="mb-8">
            <h1 class="font-display text-3xl font-semibold text-white">
                Керування <span class="text-gradient-gold italic">аддонами</span>
            </h1>
            <p class="mt-1 text-sm text-white/40">
                Core Updater · Modules · Plugins · Themes — встановлено {{ totalInstalled }} пакет(ів)
            </p>
        </div>

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
                        <p class="mb-3 text-sm text-white/60">Завантаження пакета…</p>
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
                            Завантажити {{ TYPE_META[activeTab].label }}-пакет
                        </p>
                        <p class="mt-2 text-sm text-white/40">
                            {{ TYPE_META[activeTab].desc }} · формат назви: <code class="text-gold-300/80">{{ TYPE_META[activeTab].hint }}</code>
                        </p>
                        <label
                            class="mt-6 inline-block cursor-pointer rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-8 py-3 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03]"
                        >
                            Вибрати ZIP
                            <input type="file" accept=".zip" class="hidden" @change="onPick(activeTab, $event)" />
                        </label>
                        <p class="mt-3 text-xs text-white/30">або перетягніть файл сюди</p>
                    </template>
                </div>

                <!-- ================= LIST ================= -->
                <div v-if="(addons[activeTab] || []).length === 0" class="rounded-2xl border border-white/5 bg-white/[0.02] p-12 text-center text-white/30">
                    Пакетів цього типу ще не завантажено
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

                        <p class="mt-4 text-[11px] text-white/30">Завантажено {{ fmtDate(addon.created_at) }}</p>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <button
                                v-if="!addon.status || addon.status === 'inactive' || addon.status === 'failed'"
                                :disabled="busyAddon === addon.id"
                                class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-4 py-1.5 text-xs font-medium text-emerald-300 transition-colors hover:bg-emerald-400/20 disabled:opacity-40"
                                @click="act(addon, 'activate')"
                            >
                                Активувати
                            </button>
                            <button
                                v-if="addon.status === 'active'"
                                :disabled="busyAddon === addon.id"
                                class="rounded-full border border-white/15 px-4 py-1.5 text-xs font-medium text-white/60 transition-colors hover:bg-white/5 disabled:opacity-40"
                                @click="act(addon, 'deactivate')"
                            >
                                Деактивувати
                            </button>
                            <button
                                v-if="addon.manifest?.migrations && !addon.migrations_applied"
                                :disabled="busyAddon === addon.id"
                                class="rounded-full border border-gold-400/30 bg-gold-400/10 px-4 py-1.5 text-xs font-medium text-gold-300 transition-colors hover:bg-gold-400/20 disabled:opacity-40"
                                @click="act(addon, 'migrate')"
                            >
                                Застосувати в БД
                            </button>
                            <button
                                v-if="addon.status !== 'active'"
                                :disabled="busyAddon === addon.id"
                                class="rounded-full border border-ember-500/25 px-4 py-1.5 text-xs font-medium text-ember-500/80 transition-colors hover:bg-ember-600/10 disabled:opacity-40"
                                @click="confirmDestroy(addon)"
                            >
                                Видалити
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- ================= ДЕПЛОЙ ================= -->
        <div class="mt-16">
            <h2 class="font-display mb-1 text-xl text-white">Деплой на сервер</h2>
            <p class="mb-4 text-sm text-white/40">
                Підтягує новий код з гілки й перезбирає застосунок. Потрібен лише для нових сторінок/дизайну — оновлення самих аддонів через ZIP тут не завантажуються.
            </p>

            <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-6">
                <div class="flex flex-wrap items-center gap-4">
                    <button
                        :disabled="deployTriggering || ['pending', 'running'].includes(deployStatus.status)"
                        class="rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 px-6 py-3 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03] disabled:pointer-events-none disabled:opacity-40"
                        @click="triggerDeploy"
                    >
                        <span v-if="deployStatus.status === 'running'">Виконується…</span>
                        <span v-else-if="deployStatus.status === 'pending'">Заплановано…</span>
                        <span v-else>🚀 Задеплоїти</span>
                    </button>

                    <span
                        v-if="deployStatus.status === 'success'"
                        class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300"
                    >
                        Успішно · {{ new Date(deployStatus.finished_at).toLocaleString('uk-UA') }}
                    </span>
                    <span
                        v-else-if="deployStatus.status === 'failed'"
                        class="rounded-full border border-ember-500/30 bg-ember-600/10 px-3 py-1 text-xs font-medium text-ember-500"
                    >
                        Помилка (код {{ deployStatus.exit_code }}) · {{ new Date(deployStatus.finished_at).toLocaleString('uk-UA') }}
                    </span>
                    <span
                        v-else-if="deployStatus.status === 'pending' || deployStatus.status === 'running'"
                        class="rounded-full border border-gold-400/30 bg-gold-400/10 px-3 py-1 text-xs font-medium text-gold-300"
                    >
                        {{ deployStatus.status === 'pending' ? 'Очікує на таймер сервера…' : 'Триває…' }}
                    </span>
                </div>

                <pre
                    v-if="deployLog"
                    ref="deployLogEl"
                    class="mt-5 max-h-64 overflow-y-auto whitespace-pre-wrap rounded-xl border border-white/10 bg-obsidian-950 p-4 font-mono text-[11px] leading-relaxed text-white/50"
                >{{ deployLog }}</pre>
            </div>
        </div>

        <!-- ================= AUDIT LOG ================= -->
        <div class="mt-16">
            <h2 class="font-display mb-4 text-xl text-white">Журнал дій</h2>
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
                    Поки що пусто
                </div>
            </div>
        </div>
    </AdminLayout>
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
