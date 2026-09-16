<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
    memberCount: { type: Number, default: 0 },
    telegramBotUrl: { type: String, default: '#' },
});

/* ---------- шапка: прозора -> суцільна під час скролу ---------- */
const scrolled = ref(false);
function onScroll() {
    scrolled.value = window.scrollY > 24;
}

/* ---------- лічильник учасників — рахує один раз, коли з'являється у в'юпорті ---------- */
const memberCountEl = ref(null);
const memberCountValue = ref(0);
let countAnimated = false;
let countObserver;
function animateMemberCount() {
    if (countAnimated) return;
    countAnimated = true;
    const target = props.memberCount;
    const duration = 1400;
    const start = performance.now();
    function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        memberCountValue.value = Math.floor(eased * target);
        if (progress < 1) requestAnimationFrame(tick);
        else memberCountValue.value = target;
    }
    requestAnimationFrame(tick);
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    countObserver = new IntersectionObserver(
        (entries) => entries.forEach((e) => e.isIntersecting && animateMemberCount()),
        { threshold: 0.4 },
    );
    if (memberCountEl.value) countObserver.observe(memberCountEl.value);
});
onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    countObserver?.disconnect();
});

const pillars = [
    {
        title: 'Структура',
        text: 'Чітка ієрархія родини: ролі, зони відповідальності та внутрішній порядок — не хаотичний чат, а організована спільнота.',
    },
    {
        title: 'Рейтинги',
        text: 'Особистий прогрес і ранги учасників з’являться на порталі одразу, як тільки буде активовано модуль Progression — архітектура вже готова прийняти його.',
    },
    {
        title: 'Звітність',
        text: 'Прозора система звітів за операціями та контрактами — кожна дія фіксується і може бути перевірена, без "на слово".',
    },
    {
        title: 'Прогрес',
        text: 'Особисті цілі та цілі родини в одному місці, з реальним трекінгом виконання — не косметика, а робочий інструмент.',
    },
];
</script>

<template>
    <Head title="Monsory Family — Verba Online" />

    <div class="bg-obsidian-950 font-sans text-white/80 antialiased">
        <!-- ================= NAV ================= -->
        <header
            class="fixed inset-x-0 top-0 z-50 transition-all duration-500"
            :class="scrolled ? 'bg-obsidian-950/85 backdrop-blur-md border-b border-white/5 py-3' : 'bg-transparent py-6'"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6">
                <div class="flex items-center gap-3">
                    <span
                        class="flex h-9 w-9 rotate-45 items-center justify-center rounded-md border border-gold-400/40 bg-gradient-to-br from-gold-400/20 to-transparent"
                    >
                        <span class="-rotate-45 font-display text-lg text-gold-300">M</span>
                    </span>
                    <div class="leading-tight">
                        <div class="font-display text-lg tracking-[0.25em] text-white">MONSORY</div>
                        <div class="text-[10px] tracking-[0.35em] text-gold-300/70">CONNECT</div>
                    </div>
                </div>

                <nav class="hidden items-center gap-10 text-sm tracking-wide text-white/60 md:flex">
                    <a href="#structure" class="transition-colors hover:text-gold-300">Структура</a>
                    <a href="#goals" class="transition-colors hover:text-gold-300">Цілі</a>
                    <a href="#legacy" class="transition-colors hover:text-gold-300">Спадщина</a>
                </nav>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('dashboard')"
                        class="hidden text-sm text-white/60 transition-colors hover:text-white sm:block"
                    >
                        Кабінет
                    </Link>
                    <Link
                        v-else-if="canLogin"
                        :href="route('login')"
                        class="group relative overflow-hidden rounded-full border border-gold-400/40 px-5 py-2 text-xs font-medium tracking-widest text-gold-200 transition-all hover:border-gold-300 hover:shadow-gold"
                    >
                        УВІЙТИ ↗
                    </Link>
                </div>
            </div>
        </header>

        <!-- ================= HERO ================= -->
        <!-- Фон — атмосферна сцена на весь екран з важким градієнтом, а не
             вирізаний персонаж поверх тексту: саме той підхід, що прямо
             дозволений для mobile у розділі 13 брифа (background -> visual -> текст). -->
        <section class="relative flex min-h-screen items-center overflow-hidden">
            <div
                class="absolute inset-0 -z-20 bg-cover bg-center"
                style="background-image: url('/images/hero/trio.jpg')"
            ></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-obsidian-950 via-obsidian-950/85 to-obsidian-950/50"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-obsidian-950 via-transparent to-obsidian-950/40"></div>
            <div class="bg-noise absolute inset-0 -z-10"></div>

            <div class="relative mx-auto w-full max-w-7xl px-6 py-32">
                <div class="max-w-2xl">
                    <p v-reveal class="mb-5 text-sm font-medium uppercase tracking-[0.45em] text-gold-300/90">
                        Monsory Family · Est. Legacy · Verba Online
                    </p>
                    <h1 v-reveal:150 class="font-display text-5xl font-semibold leading-[1.05] text-white sm:text-6xl lg:text-7xl">
                        Сила. Порядок.
                        <span class="text-gradient-gold italic">Спадщина.</span>
                    </h1>
                    <p v-reveal:300 class="mt-7 max-w-xl text-lg leading-relaxed text-white/60">
                        Закрита сім'я, де статус підтверджують діями, розвиток
                        вимірюється результатом, а сайт і Telegram працюють як
                        одна система.
                    </p>

                    <div v-reveal:450 class="mt-10 flex flex-wrap items-center gap-5">
                        <a
                            :href="telegramBotUrl"
                            target="_blank"
                            rel="noopener"
                            class="animate-shimmer rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 bg-[length:200%_auto] px-8 py-4 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03]"
                        >
                            Подати заявку
                        </a>
                        <a href="#structure" class="text-sm font-medium uppercase tracking-widest text-white/50 transition-colors hover:text-white">
                            Дізнатись більше ↓
                        </a>
                    </div>

                    <!-- Реальна цифра з БД, без вигаданих KPI поруч -->
                    <div ref="memberCountEl" v-reveal:600 class="mt-14 inline-flex items-baseline gap-3 border-t border-white/10 pt-6">
                        <span class="font-display text-4xl text-gold-300">{{ memberCountValue }}</span>
                        <span class="text-xs uppercase tracking-widest text-white/40">активних учасників</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- бегущая строка-разделитель -->
        <div class="overflow-hidden border-y border-white/5 py-4 opacity-60">
            <div class="animate-marquee flex w-max gap-10 whitespace-nowrap text-sm uppercase tracking-[0.4em] text-white/30">
                <span v-for="n in 2" :key="n" class="flex gap-10">
                    <span>Verba Online</span>
                    <span class="text-gold-400">•</span>
                    <span>Monsory Family</span>
                    <span class="text-gold-400">•</span>
                    <span>Est. Legacy</span>
                    <span class="text-gold-400">•</span>
                    <span>Закритий набір</span>
                    <span class="text-gold-400">•</span>
                </span>
            </div>
        </div>

        <!-- ================= СТРУКТУРА / РЕЙТИНГИ / ЗВІТНІСТЬ / ПРОГРЕС ================= -->
        <section id="structure" class="relative mx-auto max-w-7xl px-6 py-28">
            <div v-reveal class="mx-auto mb-16 max-w-2xl text-center">
                <p class="mb-4 text-sm font-medium uppercase tracking-[0.4em] text-gold-300/90">Як влаштована родина</p>
                <h2 class="font-display text-4xl font-semibold text-white sm:text-5xl">
                    Чотири опори <span class="text-gradient-gold italic">Monsory</span>
                </h2>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="(pillar, i) in pillars"
                    :key="pillar.title"
                    v-reveal="'scale'"
                    :style="{ transitionDelay: `${i * 110}ms` }"
                    class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] p-7 transition-all duration-500 hover:-translate-y-1.5 hover:border-gold-400/30 hover:bg-white/[0.05] hover:shadow-gold"
                >
                    <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-gold-400/10 blur-2xl opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                    <div class="font-display mb-4 text-3xl text-gold-400/80">{{ String(i + 1).padStart(2, '0') }}</div>
                    <h3 class="mb-3 text-lg font-semibold text-white">{{ pillar.title }}</h3>
                    <p class="text-sm leading-relaxed text-white/55">{{ pillar.text }}</p>
                </div>
            </div>
        </section>

        <!-- ================= CINEMATIC: РІЗНІ ЦІЛІ. СПІЛЬНИЙ РЕЗУЛЬТАТ ================= -->
        <section id="goals" class="relative overflow-hidden py-40">
            <div
                class="absolute inset-0 -z-20 bg-cover bg-center"
                style="background-image: url('/images/hero/chase.jpg')"
            ></div>
            <div class="absolute inset-0 -z-10 bg-obsidian-950/85"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-obsidian-950 via-transparent to-obsidian-950/60"></div>

            <div class="relative mx-auto max-w-4xl px-6 text-center">
                <p v-reveal class="mb-6 text-sm font-medium uppercase tracking-[0.4em] text-gold-300/90">
                    Ціль родини
                </p>
                <blockquote v-reveal:150 class="font-display text-3xl italic leading-snug text-white/90 sm:text-4xl">
                    Різні цілі.
                    <span class="text-gradient-gold">Спільний результат.</span>
                </blockquote>
                <p v-reveal:300 class="mx-auto mt-8 max-w-xl text-white/50">
                    У кожного учасника свій шлях і свої задачі всередині Verba
                    Online — але результат родини складається із суми дій
                    кожного, а не з обіцянок.
                </p>
            </div>
        </section>

        <!-- ================= LEGACY BANNER ================= -->
        <!-- Кінематографічний фон, НЕ вирізаний персонаж — саме та заміна,
             про яку йшлося в розділі 14 брифа. -->
        <section id="legacy" class="relative overflow-hidden py-36">
            <div
                class="absolute inset-0 -z-20 bg-cover bg-center"
                style="background-image: url('/images/hero/cta-sunset.jpg')"
            ></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-obsidian-950 via-obsidian-950/80 to-obsidian-950/40"></div>

            <div class="relative mx-auto max-w-3xl px-6 text-center">
                <h2 v-reveal class="font-display text-4xl font-semibold text-white sm:text-5xl">
                    Кожна дія
                    <span class="text-gradient-gold italic">залишає слід.</span>
                </h2>
                <p v-reveal:150 class="mx-auto mt-6 max-w-xl text-white/60">
                    Заповніть заявку в Telegram-боті — ми розглядаємо кожну
                    особисто, а не масово.
                </p>
                <div v-reveal:300 class="mt-10 flex flex-wrap items-center justify-center gap-5">
                    <a
                        :href="telegramBotUrl"
                        target="_blank"
                        rel="noopener"
                        class="animate-shimmer rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 bg-[length:200%_auto] px-10 py-4 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03]"
                    >
                        Подати заявку в Telegram
                    </a>
                    <Link
                        v-if="canRegister && !$page.props.auth?.user"
                        :href="route('register')"
                        class="rounded-full border border-white/15 px-10 py-4 text-sm font-medium uppercase tracking-widest text-white/70 transition-colors hover:border-white/30 hover:text-white"
                    >
                        Створити акаунт на сайті
                    </Link>
                </div>
            </div>
        </section>

        <!-- ================= FOOTER ================= -->
        <footer class="border-t border-white/5 px-6 py-14">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 text-sm text-white/40 sm:flex-row">
                <span class="font-display text-lg tracking-[0.3em] text-gold-300/70">MONSORY CONNECT</span>
                <p>© {{ new Date().getFullYear() }} Monsory Family · Verba Online RP</p>
                <a :href="telegramBotUrl" target="_blank" rel="noopener" class="transition-colors hover:text-gold-300">
                    Telegram
                </a>
            </div>
        </footer>
    </div>
</template>
