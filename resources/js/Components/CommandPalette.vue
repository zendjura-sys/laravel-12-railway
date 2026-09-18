<script setup>
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Search } from '@lucide/vue';

const page = usePage();
const open = ref(false);
const query = ref('');
const activeIndex = ref(0);
const inputEl = ref(null);

/* ---------- список команд: збирається з того, що реально доступне ---------- */
const items = computed(() => {
    const can = page.props.can || {};
    const list = [
        { group: 'Сайт', label: 'Головна', name: 'home', check: () => true },
        { group: 'Сайт', label: 'Кабінет', name: 'dashboard', check: () => true },
        { group: 'Сайт', label: 'Профіль', name: 'profile.edit', check: () => true },
        { group: 'Сайт', label: 'Що нового', name: 'changelog', check: () => true },
        { group: 'Спільнота', label: 'Мої звіти', name: 'reports.index', check: () => true },
        { group: 'Спільнота', label: 'Мій прогрес', name: 'progression.index', check: () => true },
        { group: 'Спільнота', label: 'Рейтинг родини', name: 'progression.leaderboard', check: () => true },
        { group: 'Спільнота', label: 'Зал слави', name: 'progression.hall-of-fame', check: () => true },
        { group: 'Спільнота', label: 'Події родини', name: 'family-events.index', check: () => true },
        { group: 'CMS', label: 'Панель CMS', name: 'admin.dashboard', check: () => true },
        { group: 'CMS', label: 'Аддони', name: 'admin.addons.index', check: () => can.manageAddons },
        { group: 'CMS', label: 'Події родини', name: 'admin.family-events.index', check: () => can.manageEvents },
        { group: 'CMS', label: 'Модерація звітів', name: 'admin.reports.index', check: () => can.manageReports },
        { group: 'CMS', label: 'Прогресія', name: 'admin.progression.index', check: () => can.manageProgression },
        { group: 'CMS', label: 'Права доступу', name: 'admin.roles.index', check: () => can.manageRoles },
        { group: 'CMS', label: 'Учасники', name: 'admin.users.index', check: () => can.manageUsers },
        { group: 'CMS', label: 'Налаштування', name: 'admin.settings.index', check: () => can.manageSettings },
    ];
    return list.filter((i) => route().has(i.name) && i.check());
});

/* ---------- живий пошук учасників (для адмінів — стрибок у Учасники/Звіти) ---------- */
const memberResults = ref([]);
let searchTimer = null;

const canSearchMembers = computed(() => {
    const can = page.props.can || {};
    return (can.manageUsers || can.manageReports) && route().has('reports.members.search');
});

watch(query, (q) => {
    const trimmed = q.trim();
    if (!open.value || trimmed.length < 2 || !canSearchMembers.value) {
        memberResults.value = [];
        return;
    }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
        try {
            const { data } = await window.axios.get(route('reports.members.search'), { params: { q: trimmed } });
            memberResults.value = data.data.members;
        } catch {
            memberResults.value = [];
        }
    }, 250);
});

const memberItems = computed(() => {
    const can = page.props.can || {};
    return memberResults.value.flatMap((m) => {
        const out = [];
        if (can.manageUsers && route().has('admin.users.index')) {
            out.push({ group: 'Учасники', label: `${m.name} — картка`, href: route('admin.users.index', { q: m.name }) });
        }
        if (can.manageReports && route().has('admin.reports.index')) {
            out.push({ group: 'Учасники', label: `${m.name} — звіти`, href: route('admin.reports.index', { q: m.name, status: 'pending' }) });
        }
        return out;
    });
});

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    const base = q ? items.value.filter((i) => i.label.toLowerCase().includes(q)) : items.value;
    return q.length >= 2 ? [...base, ...memberItems.value] : base;
});

watch(filtered, () => (activeIndex.value = 0));

function go(item) {
    if (!item) return;
    open.value = false;
    router.visit(item.href || route(item.name));
}

function onKeydown(e) {
    const isK = e.key.toLowerCase() === 'k' && (e.metaKey || e.ctrlKey);
    if (isK) {
        e.preventDefault();
        open.value = !open.value;
        return;
    }
    if (!open.value) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex.value = Math.min(activeIndex.value + 1, filtered.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        go(filtered.value[activeIndex.value]);
    }
}

watch(open, (v) => {
    if (v) {
        query.value = '';
        activeIndex.value = 0;
        memberResults.value = [];
        nextTick(() => inputEl.value?.focus());
    }
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-[200] bg-obsidian-950/70 backdrop-blur-sm data-[state=open]:animate-in data-[state=open]:fade-in" />
            <DialogContent
                class="glass-panel fixed left-1/2 top-[18%] z-[201] w-[min(560px,92vw)] -translate-x-1/2 overflow-hidden !rounded-2xl p-0 focus:outline-none"
            >
                <DialogTitle class="sr-only">Швидка навігація</DialogTitle>
                <div class="flex items-center gap-3 border-b border-white/10 px-4 py-3">
                    <Search class="h-4 w-4 text-white/30" />
                    <input
                        ref="inputEl"
                        v-model="query"
                        type="text"
                        placeholder="Куди перейти? (Esc — закрити)"
                        class="w-full bg-transparent text-sm text-white placeholder:text-white/30 focus:outline-none"
                    />
                    <kbd class="rounded border border-white/15 px-1.5 py-0.5 text-[10px] text-white/30">ESC</kbd>
                </div>

                <div class="max-h-80 overflow-y-auto p-2">
                    <div v-if="filtered.length === 0" class="px-3 py-6 text-center text-sm text-white/30">
                        Нічого не знайдено
                    </div>
                    <button
                        v-for="(item, i) in filtered"
                        :key="item.href || item.name"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm transition-colors"
                        :class="i === activeIndex ? 'bg-gold-400/10 text-gold-200' : 'text-white/60 hover:bg-white/5 hover:text-white'"
                        @mouseenter="activeIndex = i"
                        @click="go(item)"
                    >
                        <span>{{ item.label }}</span>
                        <span class="text-[10px] uppercase tracking-widest text-white/25">{{ item.group }}</span>
                    </button>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
