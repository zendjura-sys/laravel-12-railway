<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Copy, Check } from '@lucide/vue';

const props = defineProps({
    number: { type: String, required: true }, // замаскований рядок для показу ("**********1234")
    numberFull: { type: String, required: true }, // повний номер — лише для копіювання, ніде не показується
    name: { type: String, required: true },
    amount: { type: Number, default: 0 },
});

// Групуємо по 4 символи класичним "**** **** **** 1234" виглядом, рахуючи
// ЗПРАВА, щоб останні 4 реальні цифри завжди були цілою групою (а не
// розрізаною напів-зірочками, напів-цифрами) — довжина рядка тут 14, не
// 16, тому перша група коротша за решту.
const numberGroups = computed(() => {
    const groups = [];
    for (let end = props.number.length; end > 0; end -= 4) {
        groups.unshift(props.number.slice(Math.max(0, end - 4), end));
    }
    return groups;
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
    <!-- Пропорції як у справжньої банківської картки (ISO/IEC 7810 ID-1,
         85.6×53.98мм ≈ 1.586:1) — ширина тягнеться (max-w-sm), висота
         рахується від неї через aspect-ratio, а не від вмісту, тому картка
         завжди виглядає "стандартною", хоч на телефоні, хоч на десктопі. -->
    <div v-reveal v-glow class="glass-panel-gold glass-panel relative mx-auto flex aspect-[85.6/53.98] w-full max-w-sm flex-col overflow-hidden p-4 sm:p-5">
        <div class="glass-sheen"></div>

        <!-- ================= БРЕНД ================= -->
        <div class="relative flex shrink-0 items-start justify-between">
            <div class="flex items-center gap-2">
                <ApplicationLogo mark class="h-7 w-7 text-xs text-gold-300" />
                <div class="leading-tight">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-gold-300/90">Monsory Finance</p>
                    <p class="text-[8px] uppercase tracking-[0.18em] text-white/35">× American Express</p>
                </div>
            </div>
            <span class="font-display text-lg italic tracking-tight text-white/90">VISA</span>
        </div>

        <!-- ================= ЦЕНТР: НОМЕР + БАЛАНС ================= -->
        <!-- Щільно під брендом (mt-3), без flex-1/justify-center — той
             підхід залишав два порожні "коридори" (над і під блоком).
             Порожнеча, що лишається до низу картки, іде в один відступ
             перед нижнім рядком (mt-auto там), а не сюди. -->
        <div class="relative mt-3 flex flex-col gap-1.5 sm:mt-4">
            <!-- Кожна група символів — окремий span, помірний фіксований
                 gap між групами (не justify-between: той розтягував лише
                 4 групи на всю ширину з величезними проміжками). -->
            <div class="flex items-center gap-2">
                <div class="flex min-w-0 flex-1 justify-center gap-3 font-mono text-xl font-bold tracking-[0.15em] text-white sm:gap-4 sm:text-2xl">
                    <span v-for="(g, i) in numberGroups" :key="i">{{ g }}</span>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-full border border-white/15 p-1 text-white/50 transition-colors hover:border-gold-400/40 hover:text-gold-200"
                    :aria-label="copied ? 'Скопійовано' : 'Скопіювати номер картки'"
                    @click="copyNumber"
                >
                    <Check v-if="copied" class="h-3 w-3 text-emerald-400" />
                    <Copy v-else class="h-3 w-3" />
                </button>
                <span v-if="copied" class="text-[9px] text-emerald-400/80">Скопійовано</span>
            </div>

            <div class="min-w-0">
                <p class="text-[9px] uppercase tracking-widest text-white/40">Баланс</p>
                <p class="font-display truncate text-2xl text-gold-200 sm:text-3xl">{{ fmt(amount) }}</p>
            </div>
        </div>

        <!-- ================= НИЗ: УЧАСНИК + ПЕЧАТКА ================= -->
        <div class="relative mt-auto flex shrink-0 items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[9px] uppercase tracking-widest text-white/40">Учасник</p>
                <p class="truncate text-sm font-medium text-white/80">{{ name }}</p>
            </div>

            <!-- Власна печатка Monsory — не чужий товарний знак (як-от
                 центуріон American Express), а оригінальний медальйон із
                 тим самим ромбом-M, що й скрізь на сайті, у тій самій
                 золотій гамі картки. -->
            <svg viewBox="0 0 64 64" class="h-16 w-16 shrink-0 sm:h-20 sm:w-20" aria-hidden="true">
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
