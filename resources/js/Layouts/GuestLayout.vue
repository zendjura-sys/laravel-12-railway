<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center bg-obsidian-950 px-4 py-10 font-sans text-white/80">
        <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="aurora-orb animate-aurora -left-32 top-1/4 h-[28rem] w-[28rem] bg-gold-500/20"></div>
            <div class="aurora-orb animate-aurora right-[-8rem] bottom-1/4 h-[26rem] w-[26rem] bg-aurora-500/25" style="animation-delay: -8s"></div>
        </div>
        <div class="bg-noise pointer-events-none fixed inset-0 -z-10"></div>

        <Link href="/" class="mb-8 flex items-center gap-3">
            <ApplicationLogo mark class="h-11 w-11 text-xl" />
            <div class="leading-tight">
                <div class="font-display text-lg tracking-[0.25em] text-white">MONSORY</div>
                <div class="text-[10px] tracking-[0.35em] text-gold-300/70">CONNECT</div>
            </div>
        </Link>

        <div v-reveal v-glow class="glass-panel w-full max-w-md px-8 py-9">
            <slot />
        </div>
    </div>
</template>
