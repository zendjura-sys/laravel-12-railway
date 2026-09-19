<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AmbientBackground from '@/Components/AmbientBackground.vue';
import SocialIcon from '@/Components/SocialIcon.vue';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
    title: { type: String, required: true },
    tagline: { type: String, default: null },
    about: { type: Array, default: () => [] },
    socialLinks: { type: Array, default: () => [] },
});

const SOCIAL_LABELS = {
    telegram: 'Telegram',
    discord: 'Discord',
    tiktok: 'TikTok',
    youtube: 'YouTube',
    instagram: 'Instagram',
    twitter: 'X (Twitter)',
    vk: 'VK',
    website: 'Посилання',
};

const year = computed(() => new Date().getFullYear());
</script>

<template>
    <Head :title="title" />

    <div class="relative min-h-screen font-sans text-white/80 antialiased">
        <AmbientBackground mobile intensity="soft" />

        <header class="relative px-4 pb-6 pt-8 sm:px-6">
            <div class="mx-auto flex max-w-4xl items-center justify-between">
                <a href="/" class="flex items-center gap-2.5">
                    <ApplicationLogo mark class="h-8 w-8 text-base" />
                    <span class="font-display text-base tracking-[0.2em] text-white">MONSORY</span>
                </a>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('dashboard')"
                        class="glass-pill btn-ghost px-4 py-2 text-[11px] font-medium tracking-[0.2em] text-white/80"
                    >
                        КАБІНЕТ
                    </Link>
                    <Link
                        v-else-if="canLogin"
                        :href="route('login')"
                        class="glass-pill btn-ghost px-4 py-2 text-[11px] font-medium tracking-[0.2em] text-gold-200"
                    >
                        УВІЙТИ
                    </Link>
                </div>
            </div>
        </header>

        <main class="relative px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <p class="mb-3 text-xs font-medium uppercase tracking-[0.4em] text-gold-300/90">Monsory</p>
                <h1 class="font-display text-4xl font-light text-white sm:text-5xl">{{ title }}</h1>
                <p v-if="tagline" class="mt-4 text-lg text-white/50">{{ tagline }}</p>

                <div v-if="about.length > 0" class="glass-panel mt-10 space-y-4 p-6 text-left sm:p-8">
                    <p v-for="(paragraph, i) in about" :key="i" class="text-sm leading-relaxed text-white/60">
                        {{ paragraph }}
                    </p>
                </div>

                <div v-if="!$page.props.auth?.user" class="mt-10 flex flex-wrap items-center justify-center gap-3">
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="btn-gold inline-flex items-center justify-center px-6 py-3 text-xs font-semibold uppercase tracking-[0.18em]"
                    >
                        Зареєструватись
                    </Link>
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="glass-pill btn-ghost px-6 py-3 text-xs font-medium uppercase tracking-[0.18em] text-white/70"
                    >
                        Увійти
                    </Link>
                </div>
            </div>
        </main>

        <footer class="relative px-4 pb-10 pt-16 sm:px-6">
            <div class="mx-auto max-w-2xl">
                <hr class="hairline mb-8" />
                <div class="flex flex-col items-center gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs tracking-wide text-white/25">© {{ year }} Monsory</p>

                    <div v-if="socialLinks.length > 0" class="flex flex-wrap gap-2">
                        <a
                            v-for="link in socialLinks"
                            :key="link.platform"
                            :href="link.url"
                            target="_blank"
                            rel="noopener"
                            :title="SOCIAL_LABELS[link.platform] || link.platform"
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 text-white/50 transition-colors duration-300 hover:border-gold-400/40 hover:text-gold-300"
                        >
                            <SocialIcon :platform="link.platform" />
                        </a>
                    </div>

                    <a href="/" class="text-xs text-white/30 transition-colors hover:text-gold-300">
                        На головний сайт родини →
                    </a>
                </div>
            </div>
        </footer>
    </div>
</template>
