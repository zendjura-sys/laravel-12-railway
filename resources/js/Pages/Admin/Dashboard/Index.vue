<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    stats: { type: Object, required: true },
    health: { type: Object, default: null },
});

function fmtRelative(iso) {
    if (!iso) return 'немає даних';
    const diffMs = Date.now() - new Date(iso).getTime();
    const hours = Math.floor(diffMs / 3_600_000);
    if (hours < 1) return 'щойно';
    if (hours < 24) return `${hours} год тому`;
    return `${Math.floor(hours / 24)} д тому`;
}

const page = usePage();
// roles.manage — право, яке видано винятково ролі admin (SystemPermissionsSeeder),
// тому це найближчий надійний проксі "адмін" тут — глобальні auth-пропси
// самого списку ролей не несуть, лише набір can.*.
const showTwoFactorNudge = computed(() => page.props.can?.manageRoles && !page.props.auth.user.two_factor_enabled);

const sections = [
    { name: 'admin.addons.index', can: 'manageAddons', title: 'Аддони', text: 'Core, модулі, плагіни та теми — завантаження та керування ZIP-пакетами.' },
    { name: 'admin.reports.index', can: 'manageReports', title: 'Модерація звітів', text: 'Розгляд звітів учасників за бізварами та контрактами.' },
    { name: 'admin.progression.index', can: 'manageProgression', title: 'Прогресія', text: 'Досвід, ранги, досягнення та тижневі бонуси родини.' },
    { name: 'admin.members.index', can: 'manageMembers', title: 'Кадровий облік', text: 'Статуси учасників, приватні кадрові нотатки та заявки на відпустку.' },
    { name: 'admin.family-goals.index', can: 'manageGoals', title: 'Цілі родини', text: 'Спільні цілі з прогресом та загальна стрічка активності.' },
    { name: 'admin.broadcasts.index', can: 'manageBroadcasts', title: 'Розсилки', text: 'Оголошення всім учасникам — кожен отримує особисте сповіщення.' },
    { name: 'admin.roles.index', can: 'manageRoles', title: 'Права доступу', text: 'Ролі та права: хто що бачить і чим керує в CMS.' },
    { name: 'admin.users.index', can: 'manageUsers', title: 'Учасники', text: 'Список учасників сайту та призначення їм ролей.' },
    { name: 'admin.telegram.index', can: 'manageTelegram', title: 'Telegram-бот', text: 'Webhook, привʼязані акаунти та статус бота.' },
    { name: 'admin.settings.index', can: 'manageSettings', title: 'Налаштування', text: 'Загальні параметри, інтеграція з Telegram та Discord.' },
];
</script>

<template>
    <Head title="Панель CMS — Monsory Connect" />

    <AdminLayout>
        <div v-reveal class="mb-10">
            <p class="mb-2 text-sm font-medium uppercase tracking-[0.4em] text-gold-300/90">Monsory Connect</p>
            <h1 class="font-display text-4xl font-light text-white">
                Панель <span class="text-gradient-gold italic">керування</span>
            </h1>
            <p class="mt-2 text-white/40">Власна CMS родини Monsory — все в одному місці.</p>
        </div>

        <Link
            v-if="showTwoFactorNudge"
            :href="route('profile.edit')"
            v-reveal
            class="mb-8 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gold-400/30 bg-gold-400/5 px-5 py-4 text-sm text-gold-200 transition-colors hover:border-gold-400/50"
        >
            <span>🔐 У вас адмінський доступ, а двофакторна автентифікація ще вимкнена — увімкніть її в профілі.</span>
            <span class="shrink-0 text-xs uppercase tracking-widest">Перейти →</span>
        </Link>

        <div class="mb-10 grid gap-6 sm:grid-cols-3">
            <div v-reveal="'scale'" v-glow class="glass-panel-gold glass-panel p-6">
                <p class="text-xs uppercase tracking-widest text-white/40">Учасників</p>
                <p class="font-display mt-2 text-4xl text-gold-300">{{ stats.members }}</p>
            </div>
            <div v-reveal:80="'scale'" v-glow class="glass-panel p-6">
                <p class="text-xs uppercase tracking-widest text-white/40">Ролей у системі</p>
                <p class="font-display mt-2 text-4xl text-white">{{ stats.roles }}</p>
            </div>
            <div v-reveal:160="'scale'" v-glow class="glass-panel p-6">
                <p class="text-xs uppercase tracking-widest text-white/40">Активних аддонів</p>
                <p class="font-display mt-2 text-4xl text-white">{{ stats.activeAddons }}</p>
            </div>
        </div>

        <div v-if="health" v-reveal class="mb-10">
            <p class="mb-3 text-xs uppercase tracking-widest text-white/30">Стан сервера</p>
            <div class="grid gap-4 sm:grid-cols-4">
                <Link
                    :href="route().has('admin.failed-jobs.index') ? route('admin.failed-jobs.index') : '#'"
                    v-glow
                    class="glass-panel p-5"
                    :class="health.failedJobs > 0 ? 'border-ember-500/30' : ''"
                >
                    <p class="text-[11px] uppercase tracking-widest text-white/40">Провалені джоби</p>
                    <p class="font-display mt-1 text-2xl" :class="health.failedJobs > 0 ? 'text-ember-500' : 'text-white'">
                        {{ health.failedJobs }}
                    </p>
                </Link>
                <div v-glow class="glass-panel p-5">
                    <p class="text-[11px] uppercase tracking-widest text-white/40">У черзі</p>
                    <p class="font-display mt-1 text-2xl text-white">{{ health.queuedJobs }}</p>
                </div>
                <div v-glow class="glass-panel p-5">
                    <p class="text-[11px] uppercase tracking-widest text-white/40">Диск вільно</p>
                    <p class="font-display mt-1 text-2xl text-white">
                        {{ health.disk ? `${health.disk.freeGb} / ${health.disk.totalGb} ГБ` : '—' }}
                    </p>
                </div>
                <div v-glow class="glass-panel p-5">
                    <p class="text-[11px] uppercase tracking-widest text-white/40">Останній бекап</p>
                    <p class="font-display mt-1 text-2xl" :class="!health.lastBackupAt ? 'text-ember-500' : 'text-white'">
                        {{ fmtRelative(health.lastBackupAt) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <template v-for="(section, i) in sections" :key="section.name">
                <Link
                    v-if="$page.props.can?.[section.can] && route().has(section.name)"
                    :href="route(section.name)"
                    v-reveal="'scale'"
                    :style="{ transitionDelay: `${i * 80}ms` }"
                    v-glow class="group glass-panel relative overflow-hidden p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-gold"
                >
                    <div class="glass-sheen"></div>
                    <h3 class="relative font-semibold text-white">{{ section.title }}</h3>
                    <p class="relative mt-2 text-sm leading-relaxed text-white/50">{{ section.text }}</p>
                    <span class="relative mt-4 inline-block text-xs uppercase tracking-widest text-gold-300/80">Відкрити →</span>
                </Link>
            </template>
        </div>
    </AdminLayout>
</template>
