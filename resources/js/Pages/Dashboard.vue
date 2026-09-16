<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const cards = [
    { name: 'reports.index', title: 'Мої звіти', text: 'Подайте звіт за KAPT чи контрактом та слідкуйте за статусом розгляду.' },
    { name: 'progression.index', title: 'Мій прогрес', text: 'XP, ранг, досягнення та історія нарахувань.' },
    { name: 'progression.leaderboard', title: 'Рейтинг родини', text: 'Хто зараз попереду за очками прогресу.' },
];
</script>

<template>
    <Head title="Кабінет" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-display text-2xl font-semibold text-white">
                Кабінет
            </h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="mb-8">
                <p class="text-white/50">
                    Вітаємо, <span class="text-gold-300">{{ $page.props.auth.user.name }}</span> — це ваш особистий кабінет Monsory Connect.
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <template v-for="card in cards" :key="card.name">
                    <Link
                        v-if="route().has(card.name)"
                        :href="route(card.name)"
                        class="group glass-panel relative overflow-hidden p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-gold"
                    >
                        <div class="glass-sheen"></div>
                        <h3 class="relative font-semibold text-white">{{ card.title }}</h3>
                        <p class="relative mt-2 text-sm leading-relaxed text-white/50">{{ card.text }}</p>
                        <span class="relative mt-4 inline-block text-xs uppercase tracking-widest text-gold-300/80">Відкрити →</span>
                    </Link>
                </template>

                <Link
                    v-if="route().has('admin.dashboard') && (
                        $page.props.can?.manageAddons || $page.props.can?.manageReports ||
                        $page.props.can?.manageProgression || $page.props.can?.manageSettings ||
                        $page.props.can?.manageRoles || $page.props.can?.manageUsers
                    )"
                    :href="route('admin.dashboard')"
                    class="group glass-panel-gold glass-panel relative overflow-hidden p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-gold"
                >
                    <div class="glass-sheen"></div>
                    <h3 class="relative font-semibold text-white">Адмін-панель CMS</h3>
                    <p class="relative mt-2 text-sm leading-relaxed text-white/60">Аддони, права доступу, учасники, Telegram і Discord — керування всім сайтом.</p>
                    <span class="relative mt-4 inline-block text-xs uppercase tracking-widest text-gold-300">Відкрити →</span>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
