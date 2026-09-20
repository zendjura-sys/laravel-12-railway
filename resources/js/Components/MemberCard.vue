<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Copy, Check } from '@lucide/vue';

const props = defineProps({
    number: { type: String, required: true }, // замаскований рядок для показу ("**********1234")
    numberFull: { type: String, required: true }, // повний номер — лише для копіювання, ніде не показується
    name: { type: String, required: true },
    amount: { type: Number, default: 0 },
});

function fmt(v) {
    return new Intl.NumberFormat('uk-UA').format(v ?? 0) + '₴';
}

const copied = ref(false);
let copiedTimer = null;

async function copyNumber() {
    try {
        await navigator.clipboard.writeText(props.numberFull);
    } catch {
        // Старі браузери / небезпечний контекст без Clipboard API —
        // текстове поле поза екраном і document.execCommand як запасний шлях.
        const el = document.createElement('textarea');
        el.value = props.numberFull;
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
    copied.value = true;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => { copied.value = false; }, 1800);
}
</script>

<template>
    <div v-reveal v-glow class="glass-panel-gold glass-panel relative mx-auto w-full max-w-sm overflow-hidden p-6 sm:p-7">
        <div class="glass-sheen"></div>

        <!-- ================= БРЕНД ================= -->
        <div class="relative flex items-start justify-between">
            <div class="flex items-center gap-2">
                <ApplicationLogo mark class="h-8 w-8 text-sm text-gold-300" />
                <div class="leading-tight">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-gold-300/90">Monsory Finance</p>
                    <p class="text-[9px] uppercase tracking-[0.2em] text-white/35">× American Express</p>
                </div>
            </div>
            <span class="font-display text-xl italic tracking-tight text-white/90">VISA</span>
        </div>

        <!-- ================= НОМЕР ================= -->
        <div class="relative mt-7 flex items-center gap-3">
            <p class="font-mono text-xl tracking-[0.25em] text-white sm:text-2xl">{{ number }}</p>
            <button
                type="button"
                class="shrink-0 rounded-full border border-white/15 p-1.5 text-white/50 transition-colors hover:border-gold-400/40 hover:text-gold-200"
                :aria-label="copied ? 'Скопійовано' : 'Скопіювати номер картки'"
                @click="copyNumber"
            >
                <Check v-if="copied" class="h-3.5 w-3.5 text-emerald-400" />
                <Copy v-else class="h-3.5 w-3.5" />
            </button>
            <span v-if="copied" class="text-[10px] text-emerald-400/80">Скопійовано</span>
        </div>

        <!-- ================= БАЛАНС ================= -->
        <div class="relative mt-5">
            <p class="text-[10px] uppercase tracking-widest text-white/40">Баланс</p>
            <p class="font-display text-2xl text-gold-200">{{ fmt(amount) }}</p>
        </div>

        <!-- ================= НИЗ: УЧАСНИК + ПЕЧАТКА ================= -->
        <div class="relative mt-4 flex items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] uppercase tracking-widest text-white/40">Учасник</p>
                <p class="truncate font-medium text-white">{{ name }}</p>
            </div>

            <!-- Власна печатка Monsory — не чужий товарний знак (як-от
                 центуріон American Express), а оригінальний медальйон із
                 тим самим ромбом-M, що й скрізь на сайті, у тій самій
                 золотій гамі картки. -->
            <svg viewBox="0 0 64 64" class="h-10 w-10 shrink-0 sm:h-11 sm:w-11" aria-hidden="true">
                <defs>
                    <linearGradient id="monsory-seal-gold" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="rgb(var(--gold-200))" />
                        <stop offset="50%" stop-color="rgb(var(--gold-400))" />
                        <stop offset="100%" stop-color="rgb(var(--gold-600))" />
                    </linearGradient>
                </defs>
                <circle cx="32" cy="32" r="29.5" fill="none" stroke="url(#monsory-seal-gold)" stroke-width="1" opacity="0.55" />
                <circle cx="32" cy="32" r="25" fill="none" stroke="url(#monsory-seal-gold)" stroke-width="1.5" stroke-dasharray="0.5 3.6" stroke-linecap="round" opacity="0.8" />
                <rect x="21" y="21" width="22" height="22" rx="3" transform="rotate(45 32 32)" fill="none" stroke="url(#monsory-seal-gold)" stroke-width="1.75" />
                <text x="32" y="33" text-anchor="middle" dominant-baseline="central" font-family="Montserrat, sans-serif" font-size="17" font-weight="700" fill="url(#monsory-seal-gold)">M</text>
            </svg>
        </div>
    </div>
</template>
