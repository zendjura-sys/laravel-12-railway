<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Blocks, IdCard, LayoutDashboard, Menu, ScrollText, Settings, ShieldCheck, TrendingUp, Users, X } from '@lucide/vue';

defineProps({
    title: { type: String, default: '' },
});

const navGroups = [
    {
        label: 'Огляд',
        items: [
            { name: 'admin.dashboard', label: 'Панель CMS', can: null, icon: LayoutDashboard },
        ],
    },
    {
        label: 'Спільнота',
        items: [
            { name: 'admin.reports.index', label: 'Модерація звітів', can: 'manageReports', icon: ScrollText },
            { name: 'admin.progression.index', label: 'Прогресія', can: 'manageProgression', icon: TrendingUp },
            { name: 'admin.members.index', label: 'Кадровий облік', can: 'manageMembers', icon: IdCard },
            { name: 'admin.users.index', label: 'Учасники', can: 'manageUsers', icon: Users },
        ],
    },
    {
        label: 'Система',
        items: [
            { name: 'admin.addons.index', label: 'Аддони', can: 'manageAddons', icon: Blocks },
            { name: 'admin.roles.index', label: 'Права доступу', can: 'manageRoles', icon: ShieldCheck },
            { name: 'admin.settings.index', label: 'Налаштування', can: 'manageSettings', icon: Settings },
        ],
    },
];

const mobileOpen = ref(false);
</script>

<template>
    <div class="relative min-h-screen bg-obsidian-950 font-sans text-white/80 antialiased">
        <div class="pointer-events-none fixed inset-0 -z-30 hidden overflow-hidden lg:block">
            <div class="aurora-orb animate-aurora -left-40 top-10 h-[28rem] w-[28rem] bg-gold-500/15"></div>
            <div class="aurora-orb animate-aurora right-[-10rem] bottom-0 h-[30rem] w-[30rem] bg-aurora-500/20" style="animation-delay: -8s"></div>
        </div>

        <div class="flex">
            <!-- ================= SIDEBAR ================= -->
            <aside
                class="glass-panel fixed inset-y-4 left-4 z-40 w-64 -translate-x-[120%] overflow-y-auto !rounded-2xl px-5 py-6 transition-transform duration-300 lg:translate-x-0"
                :class="mobileOpen && 'translate-x-0'"
            >
                <div class="mb-8 flex items-center justify-between">
                    <Link href="/" class="flex items-center gap-3">
                        <ApplicationLogo mark class="h-9 w-9 text-lg" />
                        <div class="leading-tight">
                            <div class="font-display text-sm tracking-[0.25em] text-white">MONSORY</div>
                            <div class="text-[9px] tracking-[0.35em] text-gold-300/70">CONNECT CMS</div>
                        </div>
                    </Link>
                    <button
                        class="text-white/40 hover:text-white lg:hidden"
                        aria-label="Закрити меню"
                        @click="mobileOpen = false"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <nav class="space-y-6">
                    <div v-for="group in navGroups" :key="group.label">
                        <p class="mb-2 px-2 text-[10px] font-medium uppercase tracking-[0.3em] text-white/30">{{ group.label }}</p>
                        <div class="space-y-1">
                            <template v-for="item in group.items" :key="item.name">
                                <Link
                                    v-if="(item.can === null || $page.props.can?.[item.can]) && route().has(item.name)"
                                    :href="route(item.name)"
                                    class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors"
                                    :class="route().current(item.name.split('.').slice(0, -1).join('.') + '.*')
                                        ? 'bg-gold-400/10 text-gold-200'
                                        : 'text-white/50 hover:bg-white/5 hover:text-white'"
                                >
                                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                                    {{ item.label }}
                                </Link>
                            </template>
                        </div>
                    </div>
                </nav>

                <div class="mt-10 flex items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-[11px] text-white/30">
                    <kbd class="rounded border border-white/15 px-1.5 py-0.5">⌘K</kbd>
                    швидка навігація
                </div>

                <div class="mt-3 flex flex-col gap-2">
                    <Link :href="route('dashboard')" class="text-xs uppercase tracking-widest text-white/30 hover:text-gold-300">
                        ← Кабінет
                    </Link>
                    <Link :href="route('home')" class="text-xs uppercase tracking-widest text-white/30 hover:text-gold-300">
                        ← На головну сайту
                    </Link>
                </div>
            </aside>

            <!-- ================= CONTENT ================= -->
            <!-- Мобільна панель-тригер живе у звичайному потоці (sticky, не
                 fixed) — саме тому вона більше не може перекрити заголовок
                 сторінки: контент під нею відштовхується самою розміткою,
                 а не підбором відступу навмання. -->
            <div class="min-h-screen flex-1 lg:ml-[17rem]">
                <div class="sticky top-0 z-30 flex items-center gap-3 border-b border-white/5 bg-obsidian-950/90 px-6 py-4 backdrop-blur-md lg:hidden">
                    <button class="text-white/70 hover:text-white" aria-label="Відкрити меню" @click="mobileOpen = true">
                        <Menu class="h-5 w-5" />
                    </button>
                    <ApplicationLogo mark class="h-7 w-7 text-sm" />
                    <span class="font-display text-sm tracking-[0.2em] text-white">MONSORY CONNECT</span>
                </div>

                <main class="px-6 py-8 lg:px-10">
                    <header v-if="title" class="mb-8">
                        <h1 class="font-display text-3xl font-semibold text-white">{{ title }}</h1>
                    </header>
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
